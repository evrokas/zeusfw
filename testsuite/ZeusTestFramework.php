<?php
/**
 * Zeus Test Framework
 * 
 * YAML-based test suite for Zeus Template System and other modules.
 * 
 * @author Evangelos Rokas
 * @version 1.0
 * @date January 2026
 */

class ZeusTestFramework {
    
    private $testSuites = [];
    private $totalTests = 0;
    private $passedTests = 0;
    private $failedTests = 0;
    private $skippedTests = 0;
    private $startTime;
    private $verbose = false;
    private $more_options = [];
    private $tempFiles = [];
    private $tempDirs = [];
    private $basePath;
    
    public function __construct($verbose = false, $more_options = []) {
        $this->verbose = $verbose;
        $this->more_options = $more_options;
        $this->startTime = microtime(true);
        $this->basePath = dirname(__FILE__);
    }
    
    public function loadTestSuite($yamlFile) {
        if (!file_exists($yamlFile)) {
            throw new Exception("Test suite file not found: $yamlFile");
        }
        $config = yaml_parse_file($yamlFile);
        if (!$config) {
            throw new Exception("Failed to parse YAML file: $yamlFile");
        }
        $this->testSuites[] = ['file' => $yamlFile, 'config' => $config];
        return $this;
    }
    
    public function loadTestSuitesFromDirectory($directory) {
        if (!is_dir($directory)) {
            throw new Exception("Test suite directory not found: $directory");
        }
        foreach (glob($directory . '/*.yaml') as $file) {
            $this->loadTestSuite($file);
        }
        return $this;
    }
    
    public function run() {
        $this->log("===========================================", 'header');
        $this->log("Zeus Test Framework", 'header');
        $this->log("===========================================\n", 'header');
        
        foreach ($this->testSuites as $suite) {
            $this->runTestSuite($suite);
        }
        
        $this->printSummary();
        return $this->failedTests === 0;
    }
    
    private function runTestSuite($suite) {
        $config = $suite['config'];
        $suiteName = $config['suite']['name'] ?? basename($suite['file'], '.yaml');
        $suiteDesc = $config['suite']['description'] ?? '';
        
        $this->log("\n============================================================", 'suite');
        $this->log("Test Suite: $suiteName", 'suite');
        if ($suiteDesc) $this->log("Description: $suiteDesc", 'info');
        $this->log("============================================================\n", 'suite');
        
        if (isset($config['setup'])) $this->runSetup($config['setup']);
        $sharedData = $config['data'] ?? [];
        if (isset($config['tests'])) {
            foreach ($config['tests'] as $testName => $testConfig) {
                $this->runTest($testName, $testConfig, $sharedData);
            }
        }
        if (isset($config['teardown'])) $this->runTeardown($config['teardown']);
        $this->cleanup();
    }
    
    private function runSetup($setup) {
        if ($this->verbose) $this->log("Running setup...", 'info');
        
        if (isset($setup['directories'])) {
            foreach ($setup['directories'] as $dir) {
                $path = $this->basePath . '/' . $dir;
                if (!is_dir($path)) {
                    mkdir($path, 0755, true);
                    $this->tempDirs[] = $path;
                }
            }
        }
        
        if (isset($setup['files'])) {
            foreach ($setup['files'] as $fileConfig) {
                $path = $this->basePath . '/' . $fileConfig['path'];
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                    $this->tempDirs[] = $dir;
                }
                file_put_contents($path, $fileConfig['content'] ?? '');
                $this->tempFiles[] = $path;
            }
        }
        
        if (isset($setup['initialize'])) {
            foreach ($setup['initialize'] as $init) {
                $this->initializeModule($init);
            }
        }
    }
    
    private function initializeModule($init) {
        $type = $init['type'] ?? '';
        
        switch ($type) {
            case 'renderer':
                $templatePath = $this->basePath . '/' . ($init['template_path'] ?? 'test_templates');
                $cachePath = $this->basePath . '/' . ($init['cache_path'] ?? 'test_cache');
                if (!is_dir($cachePath)) { mkdir($cachePath, 0755, true); $this->tempDirs[] = $cachePath; }
                Renderer::init($templatePath, $init['enable_cache'] ?? false, $cachePath, 
                    $init['enable_comments'] ?? false, $init['comment_type'] ?? 'html');
                break;
            case 'filter':
                TemplateFilter::init();
                break;
            case 'suggestion':
                if (isset($init['extension'])) TemplateSuggestion::setExtension($init['extension']);
                if (isset($init['separator'])) TemplateSuggestion::setSeparator($init['separator']);
                break;
        }
    }
    
    private function runTest($testName, $testConfig, $sharedData) {
        $this->totalTests++;
        
        if (isset($testConfig['skip']) && $testConfig['skip']) {
            $this->skippedTests++;
            $this->log("⊘ SKIPPED: $testName", 'skip');
            if ($this->verbose) $this->log("  Reason: " . ($testConfig['skip_reason'] ?? 'No reason'), 'info');
            return;
        }
        
        $this->log("Running: $testName", 'test');
        if ($this->verbose && isset($testConfig['description'])) {
            $this->log("  " . $testConfig['description'], 'info');
        }
        
        try {
            // Handle inline template definition
            if (isset($testConfig['template'])) {
                $templateName = $testConfig['template']['name'];
                $templateContent = $testConfig['template']['content'];
                $templatePath = $this->basePath . '/test_templates/' . $templateName;
                
                // Create template file
                $dir = dirname($templatePath);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                file_put_contents($templatePath, $templateContent);
                $this->tempFiles[] = $templatePath;
                
                // Re-scan templates to pick up the new file
                Renderer::scanTemplates();
                
                // Build action from template config
                $action = [
                    'type' => 'render',
                    'template' => $templateName,
                    'data' => $testConfig['data'] ?? []
                ];
                $result = $this->executeAction($action, $sharedData);
            } else {
                $result = $this->executeAction($testConfig['action'] ?? [], $sharedData);
            }
            $allPassed = true;
            $failedAssertions = [];
            
            foreach ($testConfig['assertions'] ?? [] as $assertion) {
                $assertionResult = $this->runAssertion($assertion, $result);
                if (!$assertionResult['passed']) {
                    $allPassed = false;
                    $failedAssertions[] = $assertionResult;
                }
            }
            
            if ($allPassed) {
                $this->passedTests++;
                $this->log("✓ PASSED: $testName", 'pass');
            } else {
                $this->failedTests++;
                $this->log("✗ FAILED: $testName", 'fail');
                foreach ($failedAssertions as $f) $this->log("  - " . $f['message'], 'fail');

                if(in_array('quit-early', $this->more_options)) {
                    echo "Quit early option is set. Stop test...";    
                    exit(-1);
                }
            }
        } catch (Exception $e) {
            $this->failedTests++;
            $this->log("✗ FAILED: $testName", 'fail');
            $this->log("  Exception: " . $e->getMessage(), 'fail');

            if(in_array('quit-early', $this->more_options)) {
                echo "Quit early option is set. Stop test...";    
                exit(-1);
            }
        }
    }
    
    private function executeAction($action, $sharedData) {
        $type = $action['type'] ?? '';
        
        switch ($type) {
            case 'render':
                return Renderer::render($action['template'], 
                    $this->mergeData($action['data'] ?? [], $sharedData));
            case 'render_raw':
                return Renderer::renderRaw($action['template'],
                    $this->mergeData($action['data'] ?? [], $sharedData));
            case 'function_call':
                return call_user_func_array($action['function'], $action['args'] ?? []);
            case 'method_call':
                return call_user_func_array([$action['class'], $action['method']], $action['args'] ?? []);
            case 'file_operation':
                return $this->executeFileOperation($action);
            case 'filter':
                return TemplateFilter::apply($action['name'], $action['value'], $action['args'] ?? []);
            case 'suggestion':
                $method = $action['method'] ?? 'fromKeywords';
                return call_user_func_array(['TemplateSuggestion', $method], $action['args'] ?? []);
            default:
                return null;
        }
    }
    
    private function executeFileOperation($action) {
        $op = $action['operation'] ?? '';
        $file = $this->basePath . '/' . ($action['file'] ?? '');
        
        switch ($op) {
            case 'exists': return file_exists($file);
            case 'read': return file_exists($file) ? file_get_contents($file) : null;
            case 'create':
                file_put_contents($file, $action['content'] ?? '');
                $this->tempFiles[] = $file;
                return true;
            default: return null;
        }
    }
    
    private function runAssertion($assertion, $result) {
        $type = $assertion['type'] ?? 'equals';
        $expected = $assertion['expected'] ?? null;
        $message = $assertion['message'] ?? '';
        
        switch ($type) {
            case 'equals':
                $passed = $result === $expected;
                $msg = $message ?: "Expected '$expected', got '$result'";
                break;
            case 'contains':
                $passed = strpos($result, $expected) !== false;
                $msg = $message ?: "String should contain '$expected'";
                break;
            case 'not_contains':
                $passed = strpos($result, $expected) === false;
                $msg = $message ?: "String should not contain '$expected'";
                break;
            case 'matches':
                $pattern = $assertion['pattern'] ?? $expected;
                $passed = preg_match($pattern, $result) === 1;
                $msg = $message ?: "Should match pattern '$pattern'";
                break;
            case 'not_matches':
                $pattern = $assertion['pattern'] ?? $expected;
                $passed = preg_match($pattern, $result) !== 1;
                $msg = $message ?: "Should not match pattern '$pattern'";
                break;
            case 'true':
                $passed = $result === true;
                $msg = $message ?: "Expected true";
                break;
            case 'false':
                $passed = $result === false;
                $msg = $message ?: "Expected false";
                break;
            case 'empty':
                $passed = empty($result);
                $msg = $message ?: "Expected empty value";
                break;
            case 'not_empty':
                $passed = !empty($result);
                $msg = $message ?: "Expected non-empty value";
                break;
            case 'count':
                $passed = is_array($result) && count($result) === $expected;
                $msg = $message ?: "Expected count $expected, got " . (is_array($result) ? count($result) : 'non-array');
                break;
            case 'type':
                $passed = gettype($result) === $expected || (is_object($result) && get_class($result) === $expected);
                $msg = $message ?: "Expected type '$expected', got " . gettype($result);
                break;
            case 'file_exists':
                $file = $this->basePath . '/' . $expected;
                $passed = file_exists($file);
                $msg = $message ?: "File should exist: $expected";
                break;
            case 'file_contains':
                $file = $this->basePath . '/' . $assertion['file'];
                $passed = file_exists($file) && strpos(file_get_contents($file), $expected) !== false;
                $msg = $message ?: "File should contain '$expected'";
                break;
            default:
                $passed = false;
                $msg = "Unknown assertion type: $type";
        }
        
        return ['passed' => $passed, 'message' => $msg];
    }
    
    private function mergeData($testData, $sharedData) {
        return array_merge($sharedData, $testData);
    }
    
    private function runTeardown($teardown) {
        if ($this->verbose) $this->log("Running teardown...", 'info');
        
        if (isset($teardown['execute'])) {
            foreach ($teardown['execute'] as $action) {
                if (($action['type'] ?? '') === 'clear_cache') {
                    Renderer::clearCache();
                }
            }
        }
    }
    
    private function cleanup() {
        foreach (array_reverse($this->tempFiles) as $file) {
            if (file_exists($file)) @unlink($file);
        }
        foreach (array_reverse($this->tempDirs) as $dir) {
            if (is_dir($dir)) @rmdir($dir);
        }
        $this->tempFiles = [];
        $this->tempDirs = [];
    }
    
    private function printSummary() {
        $duration = round(microtime(true) - $this->startTime, 3);
        
        $this->log("\n============================================================", 'header');
        $this->log("Test Summary", 'header');
        $this->log("============================================================", 'header');
        $this->log("Total Tests: " . $this->totalTests, 'info');
        $this->log("Passed: " . $this->passedTests, 'pass');
        $this->log("Failed: " . $this->failedTests, ($this->failedTests > 0 ? 'fail' : 'info'));
        $this->log("Skipped: " . $this->skippedTests, 'skip');
        $this->log("Duration: {$duration}s", 'info');
        $this->log("============================================================\n", 'header');
        
        if ($this->failedTests === 0) {
            $this->log("✓ All tests passed!", 'pass');
        } else {
            $this->log("✗ Some tests failed!", 'fail');
        }
    }
    
    private function log($message, $type = 'info') {
        $colors = [
            'header' => "\033[1;36m",
            'suite'  => "\033[1;35m",
            'pass'   => "\033[0;32m",
            'fail'   => "\033[0;31m",
            'skip'   => "\033[0;33m",
            'test'   => "\033[0;37m",
            'info'   => "\033[0;90m",
        ];
        $reset = "\033[0m";
        
        $color = $colors[$type] ?? $colors['info'];
        echo $color . $message . $reset . "\n";
    }
    
    public function getResults() {
        return [
            'total' => $this->totalTests,
            'passed' => $this->passedTests,
            'failed' => $this->failedTests,
            'skipped' => $this->skippedTests
        ];
    }
}
