<?php

date_default_timezone_set('UTC');

require dirname(__DIR__) . '/vendor/autoload.php';

if (!function_exists('gzopen')) {
    fwrite(STDERR, "zlib extension (gzopen) is required for PclZip tests.\n");
    exit(1);
}

require __DIR__ . '/PclZipTestCaseTrait.php';
if (PHP_VERSION_ID < 70100) {
    require __DIR__ . '/PclZipTestCasePhpunit5.php';
} else {
    require __DIR__ . '/PclZipTestCasePhpunit8.php';
}

require __DIR__ . '/callbacks.php';
