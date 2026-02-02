#!/usr/bin/env php
<?php
/**
 * Zeus Test Runner
 * 
 * Command-line test runner for Zeus Template System test suites.
 * 
 * Usage:
 *   php run-tests.php                    Run all test suites
 *   php run-tests.php --suite=zeus-core  Run specific suite
 *   php run-tests.php --verbose          Run with verbose output
 *   php run-tests.php --help             Show help
 * 
 * @author Evangelos Rokas
 * @version 1.0
 * @date January 2026
 */

// Parse command line options
$options = getopt('', ['suite:', 'verbose', 'quit-early', 'help', 'list']);

if (isset($options['help'])) {
    echo <<<HELP
Zeus Test Runner

Usage:
  php run-tests.php [options]

Options:
  --suite=NAME    Run a specific test suite (without .yaml extension)
  --verbose       Show detailed output for each test
  --quitearly    Quit tests run as soon as first test fails
  --list          List available test suites
  --help          Show this help message

Examples:
  php run-tests.php                    # Run all tests
  php run-tests.php --suite=zeus-core  # Run specific suite
  php run-tests.php --verbose          # Run with detailed output

HELP;
    exit(0);
}

// Determine paths
$testingDir = dirname(__FILE__);
$srcDir = dirname($testingDir) . '/core/templates';
$suitesDir = $testingDir . '/';

// Load source files
require_once $srcDir . '/TemplateFilter.php';
require_once $srcDir . '/TemplateSuggestion.php';
require_once $srcDir . '/ZETEMTemplate.php';
require_once $testingDir . '/ZeusTestFramework.php';

// Check for YAML extension
if (!function_exists('yaml_parse_file')) {
    echo "\033[0;31mError: PHP YAML extension is required.\n";
    echo "Install with: sudo apt-get install php-yaml\033[0m\n";
    exit(1);
}

// List suites if requested
if (isset($options['list'])) {
    echo "Available test suites:\n";
    foreach (glob($suitesDir . '/*.yaml') as $file) {
        echo "  - " . basename($file, '.yaml') . "\n";
    }
    exit(0);
}

$more_options = [];
if(isset($options['quit-early']))$more_options[] = 'quit-early';

// Initialize framework
$verbose = isset($options['verbose']);
$framework = new ZeusTestFramework($verbose, $more_options);

// Load test suites
try {
    if (isset($options['suite'])) {
        $suiteFile = $suitesDir . '/' . $options['suite'] . '.yaml';
        if (!file_exists($suiteFile)) {
            echo "\033[0;31mError: Test suite not found: {$options['suite']}\033[0m\n";
            echo "Use --list to see available suites.\n";
            exit(1);
        }
        $framework->loadTestSuite($suiteFile);
    } else {
        $framework->loadTestSuitesFromDirectory($suitesDir);
    }
} catch (Exception $e) {
    echo "\033[0;31mError loading test suites: " . $e->getMessage() . "\033[0m\n";
    exit(1);
}

// Run tests
$success = $framework->run();

// Exit with appropriate code
exit($success ? 0 : 1);
