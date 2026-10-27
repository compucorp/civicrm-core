<?php
/**
 * Pick the NEWEST published package for this line. COMCL-1549.
 *
 * Usage: php pick-release.php <releases.json> <base_version>
 * Prints the asset download URL on stdout, or exits 1.
 *
 * The GitHub releases API is NOT reliably ordered newest-first - it orders by the
 * underlying tag, not by publish time. Taking the first match audited the wrong tarball:
 * on 6.4.1 it picked 4dad44 (published 09:37) over 37cbe8 (09:49), i.e. the build from
 * before the library bumps, which would have reported every advisory we had just fixed as
 * new and turned the check red every week. Sort explicitly.
 */
[$self, $path, $base] = array_pad($argv, 3, NULL);
if (!$path || !$base) {
  fwrite(STDERR, "usage: php pick-release.php <releases.json> <base_version>\n");
  exit(2);
}
$releases = json_decode(file_get_contents($path), TRUE);
if (!is_array($releases)) { fwrite(STDERR, "::error::releases payload is not valid JSON\n"); exit(1); }
// An API error decodes to an array too - {"message":"API rate limit exceeded..."} - so
// is_array() is not enough. Without this the job blames a missing release for a refused
// request, which sends whoever reads it looking in entirely the wrong place.
if (!array_is_list($releases)) {
  $msg = $releases['message'] ?? 'unexpected object instead of a list of releases';
  fwrite(STDERR, "::error::GitHub did not return a release list: $msg\n");
  exit(1);
}

$prefix = $base . '+patch.';
$candidates = [];
foreach ($releases as $r) {
  if (strpos($r['tag_name'] ?? '', $prefix) !== 0) { continue; }
  if (!empty($r['draft']) || !empty($r['prerelease'])) { continue; }
  foreach ($r['assets'] ?? [] as $a) {
    if (substr($a['name'] ?? '', -7) === '.tar.gz') {
      $candidates[] = [
        'tag' => $r['tag_name'],
        'when' => $r['published_at'] ?? $r['created_at'] ?? '',
        'url' => $a['browser_download_url'],
      ];
      break;
    }
  }
}
if (!$candidates) {
  fwrite(STDERR, "::error::No released package found for $base\n");
  exit(1);
}
usort($candidates, fn($x, $y) => strcmp($y['when'], $x['when']));
$pick = $candidates[0];
fwrite(STDERR, sprintf("Auditing release %s (published %s), newest of %d for this line\n",
  $pick['tag'], $pick['when'], count($candidates)));
echo $pick['url'];
