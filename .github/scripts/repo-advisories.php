<?php
/**
 * Poll GitHub REPOSITORY-LEVEL security advisories for every package in a built core.
 *
 * COMCL-1549. `composer audit` reads Packagist, which mirrors GitHub's *global* advisory
 * database. Maintainers can publish an advisory on their own repository's Security tab, and
 * until GitHub reviews it into the global database `composer audit` cannot see it at all.
 * That gap has already bitten us: the three PhpSpreadsheet CVEs on COMCL-1548 and both
 * masterminds/html5 GHSAs on COMCL-1549 - five advisories, none visible to `composer audit`.
 *
 * Usage: php repo-advisories.php <installed.json> <out.json>
 *
 * Exit code is always 0; the caller decides what to do with the findings. Nothing here may
 * report "clean" when it actually means "could not ask" - a package whose range cannot be
 * parsed, or whose repository could not be reached, is reported as such.
 */

function normalise(string $v): array {
  $v = ltrim(trim($v), 'vV');
  $v = explode('+', $v)[0];
  if (!preg_match('/^(\d+(?:\.\d+)*)(?:[-.]?(a|alpha|b|beta|rc|p|pl|patch)\.?(\d*))?$/i', $v, $m)) {
    preg_match_all('/\d+/', $v, $n);
    return [array_map('intval', $n[0] ?: ['0']), 3, 0];
  }
  $nums = array_map('intval', explode('.', $m[1]));
  $rank = ['a' => 0, 'alpha' => 0, 'b' => 1, 'beta' => 1, 'rc' => 2][strtolower($m[2] ?? '')] ?? 3;
  return [$nums, $rank, (int) ($m[3] ?? 0)];
}

function compareVersions(string $a, string $b): int {
  [$na, $ra, $xa] = normalise($a);
  [$nb, $rb, $xb] = normalise($b);
  $len = max(count($na), count($nb));
  $na = array_pad($na, $len, 0);
  $nb = array_pad($nb, $len, 0);
  if ($na !== $nb) { return $na < $nb ? -1 : 1; }
  if ($ra !== $rb) { return $ra < $rb ? -1 : 1; }
  if ($xa !== $xb) { return $xa < $xb ? -1 : 1; }
  return 0;
}

/** @return bool|null  null means the range could not be parsed - never treat that as clean. */
function inRange(string $version, string $range) {
  $range = trim($range);
  if ($range === '') { return NULL; }
  $satisfied = TRUE;
  foreach (explode(',', $range) as $clause) {
    $clause = trim($clause);
    if ($clause === '') { continue; }
    // A bare version with no operator means that exact version, e.g. "3.0.56".
    if (preg_match('/^\d[\w.\-+]*$/', $clause)) {
      if (compareVersions($version, $clause) !== 0) { $satisfied = FALSE; }
      continue;
    }
    if (!preg_match('/^(<=|>=|==|<|>|=)\s*(.+)$/', $clause, $m)) { return NULL; }
    $c = compareVersions($version, trim($m[2]));
    $ok = match ($m[1]) {
      '<'  => $c < 0,
      '<=' => $c <= 0,
      '>'  => $c > 0,
      '>=' => $c >= 0,
      '=', '==' => $c === 0,
    };
    if (!$ok) { $satisfied = FALSE; }
  }
  return $satisfied;
}

/**
 * One API call. Returns ['ok' => decoded] or ['error' => reason].
 *
 * Never a bare NULL: the caller must be able to tell "no advisories" from "could not ask".
 * Conflating those is exactly the failure this check exists to prevent.
 */
function api(string $path): array {
  $token = getenv('GITHUB_TOKEN') ?: '';
  $ctx = stream_context_create(['http' => [
    'method' => 'GET',
    'header' => implode("\r\n", array_filter([
      'User-Agent: compucorp-civicrm-core-audit',
      'Accept: application/vnd.github+json',
      'X-GitHub-Api-Version: 2022-11-28',
      $token !== '' ? "Authorization: Bearer $token" : NULL,
    ])),
    'ignore_errors' => TRUE,
    'timeout' => 30,
  ]]);
  $body = @file_get_contents("https://api.github.com$path", FALSE, $ctx);
  $status = 0;
  foreach ($http_response_header ?? [] as $h) {
    if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) { $status = (int) $m[1]; }
  }
  if ($status !== 200 || $body === FALSE) {
    return ['error' => $status ? "HTTP $status" : 'no response'];
  }
  $json = json_decode($body, TRUE);
  if (!is_array($json)) { return ['error' => 'unparsable response']; }
  return ['ok' => $json];
}

/** Every page, so a repo with more advisories than fit on one is not silently truncated. */
function allAdvisories(string $repo): array {
  $collected = [];
  for ($page = 1; $page <= 10; $page++) {
    $res = api("/repos/$repo/security-advisories?per_page=100&page=$page");
    if (isset($res['error'])) { return $res; }
    $collected = array_merge($collected, $res['ok']);
    if (count($res['ok']) < 100) { return ['ok' => $collected]; }
    usleep(50000);
  }
  return ['error' => 'more than 10 pages of advisories'];
}

[$script, $installedPath, $outPath] = array_pad($argv, 3, NULL);
if (!$installedPath || !$outPath) {
  fwrite(STDERR, "usage: php repo-advisories.php <installed.json> <out.json>\n");
  exit(2);
}

$installed = json_decode(file_get_contents($installedPath), TRUE);
$packages = $installed['packages'] ?? $installed;

$targets = [];
$noSource = [];
foreach ($packages as $p) {
  $url = $p['source']['url'] ?? $p['dist']['url'] ?? '';
  if (preg_match('#github\.com[/:]([^/]+)/([^/.]+)#', $url, $m)) {
    $targets[$p['name']] = ['version' => $p['version'], 'repo' => $m[1] . '/' . $m[2]];
  }
  else {
    $noSource[] = $p['name'] . ' ' . $p['version'];
  }
}

$cache = [];
$findings = [];
$unknown = [];
$unreachable = [];

foreach ($targets as $name => $info) {
  $repo = $info['repo'];
  if (!array_key_exists($repo, $cache)) {
    $cache[$repo] = allAdvisories($repo);
    usleep(50000);
  }
  $res = $cache[$repo];
  if (isset($res['error'])) {
    $unreachable[$repo] = (string) $res['error'];
    continue;
  }

  foreach ($res['ok'] as $a) {
    if (($a['state'] ?? '') !== 'published') { continue; }
    // A withdrawn advisory keeps state=published; reporting it would be a false alarm.
    if (!empty($a['withdrawn_at'])) { continue; }
    $id = ($a['cve_id'] ?? '') ?: ($a['ghsa_id'] ?? '');
    if ($id === '') { continue; }
    foreach ($a['vulnerabilities'] ?? [] as $v) {
      if (($v['package']['ecosystem'] ?? '') !== 'composer') { continue; }
      if (strcasecmp($v['package']['name'] ?? '', $name) !== 0) { continue; }
      $range = $v['vulnerable_version_range'] ?? '';
      $hit = inRange($info['version'], $range);
      $row = [
        'id' => $id,
        'ghsa' => $a['ghsa_id'] ?? '',
        'package' => $name,
        'installed' => $info['version'],
        'range' => $range,
        'patched' => $v['patched_versions'] ?? '',
        'severity' => strtolower($a['severity'] ?? '?'),
        'title' => $a['summary'] ?? '',
      ];
      if ($hit === NULL) { $unknown[$id . '|' . $range] = $row; }
      elseif ($hit) { $findings[$id] = $row; }
    }
  }
}

$out = [
  'polled_packages' => count($targets),
  'polled_repos' => count($cache) - count($unreachable),
  'packages_without_github_source' => $noSource,
  'repos_unreachable' => (object) $unreachable,
  'findings' => (object) $findings,
  'unknown_ranges' => array_values($unknown),
];
file_put_contents($outPath, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
printf(
  "Polled %d packages across %d repositories: %d match, %d unparseable range(s), %d repo(s) unreachable, %d package(s) with no GitHub source.\n",
  count($targets), count($cache) - count($unreachable), count($findings), count($unknown),
  count($unreachable), count($noSource)
);
