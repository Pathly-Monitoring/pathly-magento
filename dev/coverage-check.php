<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

/*
 * Fails unless the Cobertura report shows 100 % of lines and 100 % of
 * branches (PHPUnit with --path-coverage). A class without executable line
 * (constants only) is skipped.
 * Usage: php dev/coverage-check.php build/cobertura.xml
 */
$file = $argv[1] ?? 'build/cobertura.xml';
$xml = is_file($file) ? simplexml_load_file($file) : false;
if ($xml === false) {
    fwrite(STDERR, "Coverage report not found: $file\n");
    exit(1);
}
$failed = false;
foreach ($xml->xpath('//class') as $class) {
    $lines = $class->lines->line ?? [];
    if (count($lines) === 0) {
        continue;
    }
    $lineRate = (float) $class['line-rate'];
    $branchRate = (float) $class['branch-rate'];
    if ($lineRate < 1 || $branchRate < 1) {
        $failed = true;
        $missing = [];
        foreach ($lines as $line) {
            $partial = isset($line['condition-coverage']) && strpos((string) $line['condition-coverage'], '100%') !== 0;
            if ((int) $line['hits'] === 0 || $partial) {
                $missing[] = (string) $line['number'];
            }
        }
        printf(
            "%s: lines %.2f %%, branches %.2f %% (lines %s)\n",
            $class['filename'],
            $lineRate * 100,
            $branchRate * 100,
            implode(', ', $missing)
        );
    }
}
$total = $xml->attributes();
printf("Total: lines %.2f %%, branches %.2f %%\n", (float) $total['line-rate'] * 100, (float) $total['branch-rate'] * 100);
if ((float) $total['line-rate'] < 1 || (float) $total['branch-rate'] < 1) {
    $failed = true;
}
exit($failed ? 1 : 0);
