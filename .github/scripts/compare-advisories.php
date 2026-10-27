<?php
/**
 * Compare both advisory sources against the accepted baseline. COMCL-1549.
 *
 * Usage: php compare-advisories.php <composer-audit.json> <repo-advisories.json> <baseline.json>
 *
 * Exits 1 if anything is found that is not accepted, if a repository could not be polled, or
 * if an affected-version range could not be parsed. An unparseable range is deliberately a
 * failure rather than a pass: this check exists because silence was mistaken for safety.
 */
[$self, $auditPath, $repoPath, $baselinePath] = array_pad($argv, 4, NULL);
if (!$auditPath || !$repoPath || !$baselinePath) {
  fwrite(STDERR, "usage: php compare-advisories.php <composer-audit.json> <repo-advisories.json> <baseline.json>\n");
  exit(2);
}

$baseline = json_decode(file_get_contents($baselinePath), TRUE);
if (!is_array($baseline)) { fwrite(STDERR, "baseline is not valid JSON\n"); exit(2); }
$accepted = ($baseline['accepted'] ?? []) + ($baseline['accepted_repo_level'] ?? []);
$unpollable = $baseline['accepted_unpollable'] ?? [];

$found = [];

// composer sometimes prefixes the JSON with a notice (e.g. "could not detect the root
// package version"). Start at the first brace rather than failing the whole run on it.
$raw = file_get_contents($auditPath);
$brace = strpos($raw, '{');
$audit = $brace === FALSE ? NULL : json_decode(substr($raw, $brace), TRUE);
if (!is_array($audit) || !array_key_exists('advisories', $audit)) {
  fwrite(STDERR, "composer audit produced no parsable output\n");
  exit(1);
}
foreach ($audit['advisories'] as $package => $rows) {
  foreach ($rows as $row) {
    $id = ($row['cve'] ?? '') ?: ($row['advisoryId'] ?? '');
    $found[$id] = sprintf('%-28s %-8s %s  [packagist]',
      $package, strtoupper(($row['severity'] ?? '') ?: '?'), $row['title'] ?? '');
  }
}

$repo = json_decode(file_get_contents($repoPath), TRUE);
if (!is_array($repo)) { fwrite(STDERR, "repo-advisories.php produced no parsable output\n"); exit(1); }
foreach ($repo['findings'] ?? [] as $id => $row) {
  $found[$id] = sprintf('%-28s %-8s %s  [repo-level, %s]',
    $row['package'], strtoupper($row['severity']), $row['title'], $row['ghsa']);
}

printf("Polled %d packages across %d repositories.\n",
  $repo['polled_packages'] ?? 0, $repo['polled_repos'] ?? 0);

$fail = FALSE;

foreach ($repo['packages_without_github_source'] ?? [] as $p) {
  printf("::warning::%s has no GitHub source, so repository-level advisories were not polled for it.\n", $p);
}
foreach ($repo['repos_unreachable'] ?? [] as $r => $why) {
  if (isset($unpollable[$r])) {
    printf("::warning::%s could not be polled (%s) - known and accepted: %s\n", $r, $why, $unpollable[$r]);
    continue;
  }
  printf("::error::%s could not be polled (%s) - this run did not fully cover it.\n", $r, $why);
  $fail = TRUE;
}
foreach ($repo['unknown_ranges'] ?? [] as $u) {
  if (isset($accepted[$u['id']])) { continue; }
  printf("::error::%s %s: could not parse the affected range \"%s\" - treating as UNKNOWN, not clean.\n",
    $u['package'], $u['id'], $u['range']);
  $fail = TRUE;
}

$new = array_diff_key($found, $accepted);
$stale = array_diff_key($accepted, $found);

printf("%d advisories on the released package, %d accepted in the baseline.\n", count($found), count($accepted));

foreach ($stale as $id => $why) {
  printf("::warning::%s is in the baseline but no longer reported - prune it.\n", $id);
}
if ($new) {
  foreach ($new as $id => $what) { printf("::error::NEW advisory %s  %s\n", $id, $what); }
  fwrite(STDERR, "\n" . count($new) . " advisory/advisories are not in the baseline.\n"
    . "Assess reachability, then either bump the library or add it to\n$baselinePath with the reason.\n");
  $fail = TRUE;
}
elseif (!$fail) {
  echo "No new advisories.\n";
}
exit($fail ? 1 : 0);
