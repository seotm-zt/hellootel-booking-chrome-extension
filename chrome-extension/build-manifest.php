<?php
/**
 * Composes dist/<edition>/manifest.json out of manifest.base.json + editions/<edition>.json.
 *
 * The two domain lists in a Chrome manifest are NOT the same list:
 *   content_scripts[0].matches — pages we inject the parser into (operator portals only)
 *   host_permissions           — the above PLUS the API host the service worker talks to
 * Hence "sites" in the edition file and "host_permissions_extra" in the base file.
 *
 * Usage: php build-manifest.php <edition> <out-file>
 */

if ($argc < 3) {
    fwrite(STDERR, "usage: php build-manifest.php <edition> <out-file>\n");
    exit(2);
}

[$_, $edition, $outFile] = $argv;
$dir = __DIR__;

$read = function (string $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "not found: $path\n");
        exit(2);
    }
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data)) {
        fwrite(STDERR, "invalid JSON: $path\n");
        exit(2);
    }
    return $data;
};

$base = $read("$dir/manifest.base.json");
$ed   = $read("$dir/editions/$edition.json");

foreach (['name', 'description', 'sites'] as $key) {
    if (empty($ed[$key])) {
        fwrite(STDERR, "editions/$edition.json: missing \"$key\"\n");
        exit(2);
    }
}

// Chrome Web Store caps the extension name at 45 characters.
if (mb_strlen($ed['name']) > 45) {
    fwrite(STDERR, "name is " . mb_strlen($ed['name']) . " chars, Chrome Web Store allows 45\n");
    exit(2);
}

$extra = $base['host_permissions_extra'] ?? [];
unset($base['host_permissions_extra']);

$m = [
    'manifest_version' => $base['manifest_version'],
    'name'             => $ed['name'],
    'version'          => $base['version'],
    'description'      => $ed['description'],
] + $base;

$m['host_permissions']              = array_values(array_unique(array_merge($extra, $ed['sites'])));
$m['content_scripts'][0]['matches'] = array_values($ed['sites']);
$m['action']['default_title']       = $ed['name'];

// Key order Chrome's docs use, so a generated manifest reads like a hand-written one.
$order = ['manifest_version', 'name', 'version', 'description', 'background',
          'permissions', 'icons', 'host_permissions', 'action', 'content_scripts'];
$out = [];
foreach ($order as $k) {
    if (array_key_exists($k, $m)) { $out[$k] = $m[$k]; unset($m[$k]); }
}
$out += $m;

file_put_contents(
    $outFile,
    json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);
