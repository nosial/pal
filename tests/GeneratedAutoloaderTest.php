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
        
        // Save the generated autoloader in the same directory as the classes
        $autoloaderFile = $testDir . '/autoloader.php';
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

    public function testGeneratedAutoloaderWithRelativePaths(): void
    {
        // Create test directory structure
        $testDir = $this->tempDir . '/relative_test';
        mkdir($testDir, 0755, true);
        
        $subDir = $testDir . '/SubPackage';
        mkdir($subDir, 0755, true);
        
        // Create test classes
        $mainClassContent = '<?php
        namespace RelativeTest;
        
        class MainClass {
            public function getMessage() {
                return "Main class loaded with relative paths!";
            }
        }';
        
        $subClassContent = '<?php
        namespace RelativeTest\\SubPackage;
        
        class SubClass {
            public function getMessage() {
                return "Sub class loaded with relative paths!";
            }
        }';
        
        file_put_contents($testDir . '/MainClass.php', $mainClassContent);
        file_put_contents($subDir . '/SubClass.php', $subClassContent);
        
        // Generate autoloader with relative paths (default behavior)
        $autoloaderCode = Autoloader::generateAutoloader($testDir);
        $this->assertIsString($autoloaderCode);
        
        // Verify that the generated code contains __DIR__ instead of absolute paths
        $this->assertStringContainsString('__DIR__', $autoloaderCode);
        $this->assertStringNotContainsString($testDir, $autoloaderCode);
        
        // Save the generated autoloader in the same directory where the classes are
        $autoloaderFile = $testDir . '/autoloader.php';
        file_put_contents($autoloaderFile, $autoloaderCode);
        
        // Test the autoloader in a separate process
        $testScript = $this->tempDir . '/test_relative.php';
        $testCode = '<?php
        require_once ' . var_export($autoloaderFile, true) . ';
        
        // Test if classes can be loaded
        $main_exists = class_exists("RelativeTest\\\\MainClass", true);
        $sub_exists = class_exists("RelativeTest\\\\SubPackage\\\\SubClass", true);
        
        if ($main_exists && $sub_exists) {
            $mainInstance = new RelativeTest\\MainClass();
            $subInstance = new RelativeTest\\SubPackage\\SubClass();
            echo $mainInstance->getMessage() . "|" . $subInstance->getMessage();
            exit(0);
        } else {
            exit(1);
        }';
        
        file_put_contents($testScript, $testCode);
        
        // Execute the test script
        ob_start();
        $exitCode = 0;
        $output = [];
        
        exec("php " . escapeshellarg($testScript), $output, $exitCode);
        ob_end_clean();
        
        $this->assertEquals(0, $exitCode, 'Relative path autoloader should work correctly');
        $this->assertStringContainsString('Main class loaded with relative paths!', implode('', $output));
        $this->assertStringContainsString('Sub class loaded with relative paths!', implode('', $output));
    }

    public function testGeneratedAutoloaderWithAbsolutePaths(): void
    {
        // Create test directory structure
        $testDir = $this->tempDir . '/absolute_test';
        mkdir($testDir, 0755, true);
        
        // Create test class
        $classContent = '<?php
        namespace AbsoluteTest;
        
        class AbsoluteClass {
            public function getMessage() {
                return "Absolute path works!";
            }
        }';
        
        file_put_contents($testDir . '/AbsoluteClass.php', $classContent);
        
        // Generate autoloader with absolute paths
        $autoloaderCode = Autoloader::generateAutoloader($testDir, ['relative' => false]);
        $this->assertIsString($autoloaderCode);
        
        // Verify that the generated code contains absolute paths and no __DIR__
        $this->assertStringNotContainsString('__DIR__', $autoloaderCode);
        
        // Normalize paths for cross-platform comparison
        $normalizedTestDir = str_replace('\\', '/', $testDir);
        $normalizedAutoloaderCode = str_replace('\\', '/', $autoloaderCode);
        $this->assertStringContainsString($normalizedTestDir, $normalizedAutoloaderCode);
        
        // Save the generated autoloader in the same directory where the classes are
        $autoloaderFile = $testDir . '/autoloader.php';
        file_put_contents($autoloaderFile, $autoloaderCode);
        
        // Test the autoloader
        $testScript = $this->tempDir . '/test_absolute.php';
        $testCode = '<?php
        require_once ' . var_export($autoloaderFile, true) . ';
        
        if (class_exists("AbsoluteTest\\\\AbsoluteClass", true)) {
            $instance = new AbsoluteTest\\AbsoluteClass();
            echo $instance->getMessage();
            exit(0);
        } else {
            exit(1);
        }';
        
        file_put_contents($testScript, $testCode);
        
        // Execute the test script
        ob_start();
        $exitCode = 0;
        $output = [];
        
        exec("php " . escapeshellarg($testScript), $output, $exitCode);
        ob_end_clean();
        
        $this->assertEquals(0, $exitCode, 'Absolute path autoloader should work correctly');
        $this->assertStringContainsString('Absolute path works!', implode('', $output));
    }

    public function testRelativePathAutoloaderMovedCompletely(): void
    {
        // Create test directory structure
        $originalDir = $this->tempDir . '/original_moveable_test';
        mkdir($originalDir, 0755, true);
        
        // Create test class
        $classContent = '<?php
        namespace MoveableTest;
        
        class MoveableClass {
            public function getMessage() {
                return "Moved autoloader works!";
            }
        }';
        
        file_put_contents($originalDir . '/MoveableClass.php', $classContent);
        
        // Generate autoloader with relative paths
        $autoloaderCode = Autoloader::generateAutoloader($originalDir);
        $this->assertIsString($autoloaderCode);
        
        // Save the autoloader in the original directory
        $originalAutoloaderFile = $originalDir . '/autoloader.php';
        file_put_contents($originalAutoloaderFile, $autoloaderCode);
        
        // Move the ENTIRE directory structure to a new location
        $movedDir = $this->tempDir . '/moved_complete';
        rename($originalDir, $movedDir);
        
        // Test that the autoloader works from the new location
        $testScript = $this->tempDir . '/test_moved.php';
        $testCode = '<?php
        require_once ' . var_export($movedDir . '/autoloader.php', true) . ';
        
        if (class_exists("MoveableTest\\\\MoveableClass", true)) {
            $instance = new MoveableTest\\MoveableClass();
            echo $instance->getMessage();
            exit(0);
        } else {
            exit(1);
        }';
        
        file_put_contents($testScript, $testCode);
        
        // Execute the test script
        ob_start();
        $exitCode = 0;
        $output = [];
        
        exec("php " . escapeshellarg($testScript), $output, $exitCode);
        ob_end_clean();
        
        $this->assertEquals(0, $exitCode, 'Completely moved relative path autoloader should work correctly');
        $this->assertStringContainsString('Moved autoloader works!', implode('', $output));
    }

    public function testRelativePathCalculation(): void
    {
        // Test with complex directory structure
        $baseDir = $this->tempDir . '/complex_test';
        $libDir = $baseDir . '/lib';
        $deepDir = $libDir . '/deep/nested';
        
        mkdir($deepDir, 0755, true);
        
        // Create classes at different levels
        $baseClassContent = '<?php
        namespace ComplexTest;
        class BaseClass { public function test() { return "base"; } }';
        
        $libClassContent = '<?php
        namespace ComplexTest\\Lib;
        class LibClass { public function test() { return "lib"; } }';
        
        $deepClassContent = '<?php
        namespace ComplexTest\\Lib\\Deep\\Nested;
        class DeepClass { public function test() { return "deep"; } }';
        
        file_put_contents($baseDir . '/BaseClass.php', $baseClassContent);
        file_put_contents($libDir . '/LibClass.php', $libClassContent);
        file_put_contents($deepDir . '/DeepClass.php', $deepClassContent);
        
        // Generate autoloader with relative paths
        $autoloaderCode = Autoloader::generateAutoloader($baseDir);
        $this->assertIsString($autoloaderCode);
        
        // Verify relative path structure
        $this->assertStringContainsString('__DIR__ . \'/BaseClass.php\'', $autoloaderCode);
        $this->assertStringContainsString('__DIR__ . \'/lib/LibClass.php\'', $autoloaderCode);
        $this->assertStringContainsString('__DIR__ . \'/lib/deep/nested/DeepClass.php\'', $autoloaderCode);
        
        // Test functionality
        $autoloaderFile = $baseDir . '/autoloader.php';
        file_put_contents($autoloaderFile, $autoloaderCode);
        
        $testScript = $this->tempDir . '/test_complex.php';
        $testCode = '<?php
        require_once ' . var_export($autoloaderFile, true) . ';
        
        $results = [];
        if (class_exists("ComplexTest\\\\BaseClass", true)) {
            $instance = new ComplexTest\\BaseClass();
            $results[] = $instance->test();
        }
        if (class_exists("ComplexTest\\\\Lib\\\\LibClass", true)) {
            $instance = new ComplexTest\\Lib\\LibClass();
            $results[] = $instance->test();
        }
        if (class_exists("ComplexTest\\\\Lib\\\\Deep\\\\Nested\\\\DeepClass", true)) {
            $instance = new ComplexTest\\Lib\\Deep\\Nested\\DeepClass();
            $results[] = $instance->test();
        }
        
        if (count($results) === 3) {
            echo implode("|", $results);
            exit(0);
        } else {
            exit(1);
        }';
        
        file_put_contents($testScript, $testCode);
        
        // Execute the test script
        ob_start();
        $exitCode = 0;
        $output = [];
        
        exec("php " . escapeshellarg($testScript), $output, $exitCode);
        ob_end_clean();
        
        $this->assertEquals(0, $exitCode, 'Complex relative path autoloader should work correctly');
        $this->assertStringContainsString('base|lib|deep', implode('', $output));
    }
}
