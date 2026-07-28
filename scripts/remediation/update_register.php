#!/usr/bin/env php
<?php
/**
 * Update a row in CHIPPER_FARMS_REMEDIATION_STATUS.csv.
 *
 * Usage:
 *   php scripts/remediation/update_register.php CF-P1-021 \
 *       "Implementation Status=Implemented" "Root Cause=..." ...
 *
 * Column names must match the header exactly. Unknown columns are rejected
 * rather than silently ignored, so a typo can never quietly drop evidence.
 */

$file = dirname(__DIR__, 2) . '/CHIPPER_FARMS_REMEDIATION_STATUS.csv';

$argvLocal = $argv;
array_shift($argvLocal);
$id = array_shift($argvLocal);

if ($id === null || $argvLocal === []) {
    fwrite(STDERR, "usage: update_register.php <FINDING-ID> \"Column=Value\" ...\n");
    exit(1);
}

$fh = fopen($file, 'r');
$rows = [];
while (($r = fgetcsv($fh)) !== false) {
    if (count(array_filter($r, fn ($v) => $v !== '' && $v !== null)) === 0) {
        continue;
    }
    $rows[] = $r;
}
fclose($fh);

$header = $rows[0];
$idIdx  = array_search('Finding ID', $header, true);

$updates = [];
foreach ($argvLocal as $pair) {
    $pos = strpos($pair, '=');
    if ($pos === false) {
        fwrite(STDERR, "bad argument (expected Column=Value): {$pair}\n");
        exit(1);
    }
    $col = substr($pair, 0, $pos);
    $val = substr($pair, $pos + 1);
    $ci  = array_search($col, $header, true);
    if ($ci === false) {
        fwrite(STDERR, "unknown column: {$col}\n");
        exit(1);
    }
    $updates[$ci] = $val;
}

$found = false;
foreach ($rows as $i => $row) {
    if ($i === 0 || ($row[$idIdx] ?? null) !== $id) {
        continue;
    }
    $found = true;
    foreach ($updates as $ci => $val) {
        $rows[$i][$ci] = $val;
    }
}

if (! $found) {
    fwrite(STDERR, "finding not found: {$id}\n");
    exit(1);
}

$o = fopen($file, 'w');
foreach ($rows as $row) {
    fputcsv($o, $row);
}
fclose($o);

echo "updated {$id}: " . count($updates) . " field(s)\n";
