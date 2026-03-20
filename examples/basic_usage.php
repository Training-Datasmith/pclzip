<?php

declare(strict_types=1);

require_once __DIR__ . '/../pclzip.lib.php';

// --- Example 1: Create a ZIP archive ---
// PclZip accepts a comma-separated list of files/directories by default.
// Replace paths with real files to test.

echo "// Create a ZIP archive:\n";
echo <<<'EXAMPLE'
$zip = new PclZip('/tmp/my_archive.zip');

// Add files — returns entry count on success, 0 on failure
$result = $zip->create('file1.txt,file2.txt', PCLZIP_OPT_REMOVE_ALL_PATH);
if ($result === 0) {
    die('Error: ' . $zip->errorInfo(true));
}
echo "Created archive with {$result} entries.\n";
EXAMPLE;
echo "\n\n";

// --- Example 2: List archive contents ---
echo "// List entries in an existing ZIP:\n";
echo <<<'EXAMPLE'
$zip = new PclZip('/tmp/my_archive.zip');
$list = $zip->listContent();

foreach ($list as $entry) {
    echo $entry['filename']
        . ' (' . $entry['size'] . ' bytes'
        . ', compressed: ' . $entry['compressed_size'] . " bytes)\n";
}
EXAMPLE;
echo "\n\n";

// --- Example 3: Extract all files ---
echo "// Extract all entries to a directory:\n";
echo <<<'EXAMPLE'
$zip = new PclZip('/tmp/my_archive.zip');

$result = $zip->extract(PCLZIP_OPT_PATH, '/tmp/extracted/');
if ($result === 0) {
    die('Extraction failed: ' . $zip->errorInfo(true));
}
echo "Extracted " . count($result) . " files.\n";
EXAMPLE;
echo "\n\n";

// --- Example 4: Add files with path rewriting ---
echo "// Add files while stripping a path prefix:\n";
echo <<<'EXAMPLE'
$zip = new PclZip('/tmp/project.zip');

// Store /var/www/project/src/index.php as src/index.php in the archive
$result = $zip->create(
    '/var/www/project/src/index.php',
    PCLZIP_OPT_REMOVE_PATH, '/var/www/project'
);
EXAMPLE;
echo "\n\n";

// --- Example 5: Use a pre-extract callback to filter entries ---
echo "// Extract only .php files using a pre-extract callback:\n";
echo <<<'EXAMPLE'
$zip = new PclZip('/tmp/my_archive.zip');

$result = $zip->extract(
    PCLZIP_OPT_PATH, '/tmp/php_only/',
    PCLZIP_CB_PRE_EXTRACT, function (int $reason, array &$entry): int {
        // Return 1 to extract, 0 to skip
        return str_ends_with($entry['filename'], '.php') ? 1 : 0;
    }
);
EXAMPLE;
echo "\n";
