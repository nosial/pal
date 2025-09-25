<?php
namespace PAL\Tests;

use pal\Autoloader;
use PHPUnit\Framework\TestCase;

class AutoloaderEdgeCasesTest extends TestCase
{
    private string $tempDir;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = PAL_TEST_DIR . '/temp';
        
        Autoloader::unregisterAll();
        Autoloader::clearCache();
        
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }
    
    protected function tearDown(): void
    {
        Autoloader::unregisterAll();
        Autoloader::clearCache();
        
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
        
        parent::tearDown();
    }
    
    public function testEmptyDirectory(): void
    {
        $emptyDir = $this->tempDir . '/empty';
        mkdir($emptyDir, 0755, true);
        
        $result = Autoloader::autoload($emptyDir);
        $this->assertFalse($result, 'Should return false for empty directory');
    }
    
    public function testDirectoryWithNonPhpFiles(): void
    {
        $testDir = $this->tempDir . '/nonphp';
        mkdir($testDir, 0755, true);
        
        // Create non-PHP files
        file_put_contents($testDir . '/readme.txt', 'This is a text file');
        file_put_contents($testDir . '/config.json', '{"test": true}');
        file_put_contents($testDir . '/style.css', 'body { color: red; }');
        
        $result = Autoloader::autoload($testDir);
        $this->assertFalse($result, 'Should return false for directory with no PHP files');
    }
    
    public function testDirectoryWithCustomExtensions(): void
    {
        $testDir = $this->tempDir . '/custom_ext';
        mkdir($testDir, 0755, true);
        
        // Create file with custom extension
        $phpContent = '<?php class CustomExtClass { }';
        file_put_contents($testDir . '/CustomExtClass.inc', $phpContent);
        
        // Test with default extensions (should not find the file)
        $result = Autoloader::autoload($testDir);
        $this->assertFalse($result);
        
        // Test with custom extensions (should find the file)
        $result = Autoloader::autoload($testDir, ['extensions' => ['inc']]);
        $this->assertTrue($result);
        $this->assertTrue(class_exists('CustomExtClass', true));
    }
    
    public function testUnreadableDirectory(): void
    {
        $testDir = $this->tempDir . '/unreadable';
        mkdir($testDir, 0755, true);
        
        // Create a PHP file first
        file_put_contents($testDir . '/TestClass.php', '<?php class TestClass { }');
        
        // Make directory unreadable (skip on Windows where chmod doesn't work the same way)
        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($testDir, 0000);
            
            // Test that unreadable directory returns false (warning is expected but we don't test for it)
            $result = @Autoloader::autoload($testDir);
            $this->assertFalse($result, 'Should return false for unreadable directory');
            
            // Restore permissions for cleanup
            chmod($testDir, 0755);
        } else {
            $this->markTestSkipped('Cannot test unreadable directories on Windows');
        }
    }
    
    public function testSymlinkHandling(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Symlink tests not reliable on Windows');
        }
        
        $originalDir = $this->tempDir . '/original';
        $linkDir = $this->tempDir . '/linked';
        
        mkdir($originalDir, 0755, true);
        file_put_contents($originalDir . '/LinkedClass.php', '<?php class LinkedClass { }');
        
        // Create symlink
        symlink($originalDir, $linkDir);
        
        // Test with symlink following disabled (default)
        $result = Autoloader::autoload($this->tempDir, ['follow_symlinks' => false]);
        $this->assertTrue($result);
        
        // Test with symlink following enabled
        $result2 = Autoloader::autoload($this->tempDir, ['follow_symlinks' => true]);
        $this->assertTrue($result2);
    }
    
    public function testAnonymousClassHandling(): void
    {
        $testDir = $this->tempDir . '/anonymous';
        mkdir($testDir, 0755, true);
        
        // Create file with anonymous class
        $phpContent = '<?php
        namespace Test;
        
        class RegularClass {
            public function createAnonymous() {
                return new class {
                    public function test() {
                        return "anonymous";
                    }
                };
            }
        }';
        
        file_put_contents($testDir . '/AnonymousTest.php', $phpContent);
        
        $result = Autoloader::autoload($testDir);
        $this->assertTrue($result);
        
        // Should be able to load the regular class, not the anonymous one
        $this->assertTrue(class_exists('Test\\RegularClass', true));
        
        $instance = new \Test\RegularClass();
        $anonymous = $instance->createAnonymous();
        $this->assertEquals('anonymous', $anonymous->test());
    }
    
    public function testClassConstantHandling(): void
    {
        $testDir = $this->tempDir . '/class_constant';
        mkdir($testDir, 0755, true);
        
        // Create file that uses ::class constant
        $phpContent = '<?php
        namespace Test;
        
        class ConstantTest {
            public function getClassName() {
                return self::class;
            }
            
            public function getOtherClassName() {
                return \stdClass::class;
            }
        }';
        
        file_put_contents($testDir . '/ConstantTest.php', $phpContent);
        
        $result = Autoloader::autoload($testDir);
        $this->assertTrue($result);
        $this->assertTrue(class_exists('Test\\ConstantTest', true));
        
        $instance = new \Test\ConstantTest();
        $this->assertEquals('Test\\ConstantTest', $instance->getClassName());
    }
    
    public function testUseStatementHandling(): void
    {
        $testDir = $this->tempDir . '/use_statements';
        mkdir($testDir, 0755, true);
        
        // Create file with various use statements
        $phpContent = '<?php
        namespace Test;
        
        use Exception;
        use DateTime as DT;
        use function array_map;
        use const PHP_VERSION;
        
        class UseTest {
            public function test() {
                return new Exception("test");
            }
        }';
        
        file_put_contents($testDir . '/UseTest.php', $phpContent);
        
        $mapping = Autoloader::generateAutoloaderArray($testDir);
        $this->assertIsArray($mapping);
        $this->assertArrayHasKey('Test\\UseTest', $mapping);
    }
    
    public function testNestedNamespaces(): void
    {
        $testDir = $this->tempDir . '/nested_ns';
        mkdir($testDir . '/deep/deeper', 0755, true);
        
        // Create deeply nested class
        $phpContent = '<?php
        namespace Very\\Deep\\Nested\\Namespace\\Structure;
        
        class DeepClass {
            public function getDepth() {
                return 5;
            }
        }';
        
        file_put_contents($testDir . '/deep/deeper/DeepClass.php', $phpContent);
        
        $result = Autoloader::autoload($testDir);
        $this->assertTrue($result);
        $this->assertTrue(class_exists('Very\\Deep\\Nested\\Namespace\\Structure\\DeepClass', true));
    }
    
    public function testBracketedNamespaces(): void
    {
        $testDir = $this->tempDir . '/bracketed_ns';
        mkdir($testDir, 0755, true);
        
        // Create file with bracketed namespace
        $phpContent = '<?php
        namespace BracketedNamespace {
            class BracketedClass {
                public function test() {
                    return "bracketed";
                }
            }
        }
        
        namespace AnotherNamespace {
            class AnotherClass {
                public function test() {
                    return "another";
                }
            }
        }';
        
        file_put_contents($testDir . '/BracketedTest.php', $phpContent);
        
        $mapping = Autoloader::generateAutoloaderArray($testDir);
        $this->assertIsArray($mapping);
        $this->assertArrayHasKey('BracketedNamespace\\BracketedClass', $mapping);
        $this->assertArrayHasKey('AnotherNamespace\\AnotherClass', $mapping);
    }
    
    public function testMultiplePrepends(): void
    {
        $dir1 = $this->tempDir . '/dir1';
        $dir2 = $this->tempDir . '/dir2';
        
        mkdir($dir1, 0755, true);
        mkdir($dir2, 0755, true);
        
        file_put_contents($dir1 . '/TestClass1.php', '<?php class TestClass1 { }');
        file_put_contents($dir2 . '/TestClass2.php', '<?php class TestClass2 { }');
        
        // Register multiple autoloaders with prepend
        $result1 = Autoloader::autoload($dir1, ['prepend' => true]);
        $result2 = Autoloader::autoload($dir2, ['prepend' => true]);
        
        $this->assertTrue($result1);
        $this->assertTrue($result2);
        
        $loaders = Autoloader::getRegisteredLoaders();
        $this->assertCount(2, $loaders);
        
        $this->assertTrue(class_exists('TestClass1', true));
        $this->assertTrue(class_exists('TestClass2', true));
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
                chmod($path, 0755);
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
            chmod($dir, 0755);
        }
        
        rmdir($dir);
    }
}
