<?php
/**
 * ArenaNexus 2026: Standalone Test Runner
 * Executes all unit and integration tests using PHPUnit.
 */
echo "=== ArenaNexus 2026: Running Automated Tests ===\n";

$phpunitPath = __DIR__ . '/../vendor/bin/phpunit';
$configPath = __DIR__ . '/../phpunit.xml';

if (!file_exists($phpunitPath)) {
    echo "[ERROR] PHPUnit binary not found in vendor directory. Did you run 'composer install'?\n";
    exit(1);
}

$command = escapeshellcmd($phpunitPath) . ' --configuration ' . escapeshellarg($configPath);
passthru($command, $returnCode);

if ($returnCode === 0) {
    echo "\n[SUCCESS] All tests passed successfully.\n";
    exit(0);
} else {
    echo "\n[FAILURE] Test suite encountered errors.\n";
    exit(1);
}
