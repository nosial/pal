<?php
namespace PAL\Tests;

use pal\Autoloader;
use PHPUnit\Framework\TestCase;

class GeneratedAutoloaderTest extends TestCase
{
    private string $tempDir;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = PAL_TEST_DIR . '/temp';
        
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }
    
    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
        
        parent::tearDown();
    }
    
    public function testGeneratedAutoloaderCanLoadClasses(): void
    {
        // Create test classes
        $testDir = $this->tempDir . '/generated_test';
        mkdir($testDir, 0755, true);
        
        $phpContent = '<?php
        namespace GeneratedTest;
        
        class GeneratedClass {
            public function getMessage() {
                return "Generated autoloader works!";
            }
        }
        
        interface GeneratedInterface {
            public function doSomething();
        }';
        
        file_put_contents($testDir . '/GeneratedClass.php', $phpContent);
        
        // Generate autoloader code
        $autoloaderCode = Autoloader::generateAutoloader($testDir);
        $this->assertIsString($autoloaderCode);
        
        // Save the generated autoloader
        $autoloaderFile = $this->tempDir . '/generated_autoloader.php';
        file_put_contents($autoloaderFile, $autoloaderCode);
        
        // Include the generated autoloader in a separate process to avoid conflicts
        $testScript = $this->tempDir . '/test_generated.php';
        $testCode = '<?php
        require_once ' . var_export($autoloaderFile, true) . ';
        
        // Test if classes can be loaded
        $class_exists = class_exists("GeneratedTest\\\\GeneratedClass", true);
        $interface_exists = interface_exists("GeneratedTest\\\\GeneratedInterface", true);
        
        if ($class_exists && $interface_exists) {
            $instance = new GeneratedTest\\GeneratedClass();
            echo $instance->getMessage();
            exit(0);
        } else {
            exit(1);
        }';
        
        file_put_contents($testScript, $testCode);
        
        // Execute the test script
        ob_start();
        $exitCode = 0;
        $output = '';
        
        exec("php " . escapeshellarg($testScript), $output, $exitCode);
        ob_end_clean();
        
        $this->assertEquals(0, $exitCode, 'Generated autoloader should work correctly');
        $this->assertContains('Generated autoloader works!', $output);
    }
    
    public function testGeneratedAutoloaderWithOptions(): void
    {
        $testDir = $this->tempDir . '/options_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/OptionsClass.php', '<?php
        namespace Options;
        class OptionsClass {
            public function test() { return "options"; }
        }');
        
        // Generate with custom options
        $autoloaderCode = Autoloader::generateAutoloader($testDir, [
            'case_sensitive' => true,
            'prepend' => false
        ]);
        
        $this->assertIsString($autoloaderCode);
        $this->assertStringContainsString('_case_insensitive\'] = false', $autoloaderCode);
        $this->assertStringContainsString('spl_autoload_register', $autoloaderCode);
    }
    
    public function testGeneratedAutoloaderMetadata(): void
    {
        $testDir = $this->tempDir . '/metadata_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/MetaClass.php', '<?php class MetaClass { }');
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir);
        
        // Check for metadata in generated code
        $this->assertStringContainsString('Generated Standalone Autoloader', $autoloaderCode);
        $this->assertStringContainsString('Total classes: 1', $autoloaderCode);
        $this->assertStringContainsString('@generated', $autoloaderCode);
        $this->assertStringContainsString(date('Y-m-d'), $autoloaderCode);
    }
    
    public function testGeneratedAutoloaderNoDuplicateRegistration(): void
    {
        $testDir = $this->tempDir . '/duplicate_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/DupeClass.php', '<?php class DupeClass { }');
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir);
        
        // Should contain duplicate prevention logic
        $this->assertStringContainsString("if (!defined('", $autoloaderCode);
        $this->assertStringContainsString("define('", $autoloaderCode);
    }
    
    public function testGeneratedAutoloaderErrorHandling(): void
    {
        $testDir = $this->tempDir . '/error_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/ErrorClass.php', '<?php class ErrorClass { }');
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir);
        
        // Should contain error handling
        $this->assertStringContainsString('try', $autoloaderCode);
        $this->assertStringContainsString('catch', $autoloaderCode);
        $this->assertStringContainsString('trigger_error', $autoloaderCode);
    }
    
    /**
     * Helper method to recursively remove a directory
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        // Handle symlinks specially
        if (is_link($dir)) {
            unlink($dir);
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            // Restore permissions for cleanup
            if (PHP_OS_FAMILY !== 'Windows') {
                @chmod($path, 0755);
            }
            
            if (is_link($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        // Restore directory permissions before removal
        if (PHP_OS_FAMILY !== 'Windows') {
            @chmod($dir, 0755);
        }
        
        rmdir($dir);
    }
}
