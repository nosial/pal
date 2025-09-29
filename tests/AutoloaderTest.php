<?php
namespace PAL\Tests;

use pal\Autoloader;
use PHPUnit\Framework\TestCase;

class AutoloaderTest extends TestCase
{
    private string $tempDir;
    private string $fixturesDir;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = PAL_TEST_DIR . '/temp';
        $this->fixturesDir = PAL_FIXTURES_DIR;
        
        // Clean up any registered autoloaders from previous tests
        Autoloader::unregisterAll();
        Autoloader::clearCache();
        
        // Ensure temp directory exists
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }
    
    protected function tearDown(): void
    {
        // Clean up after each test
        Autoloader::unregisterAll();
        Autoloader::clearCache();
        
        // Clean up temp files
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
        
        parent::tearDown();
    }
    
    public function testPhpVersionCheck(): void
    {
        // Since we're running the tests, PHP version should be valid
        $this->assertTrue(PHP_VERSION_ID >= 80300, 'PHP version should be 8.3+');
    }
    
    public function testAutoloadWithValidDirectory(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        $result = Autoloader::autoload($simpleDir);
        
        $this->assertTrue($result, 'Autoloader should register successfully');
        
        // Test that classes can be loaded
        $this->assertTrue(class_exists('TestNamespace\SimpleClass', true));
        $this->assertTrue(interface_exists('TestNamespace\SimpleInterface', true));
        $this->assertTrue(trait_exists('TestNamespace\SimpleTrait', true));
        
        // Test enum if PHP version supports it
        if (PHP_VERSION_ID >= 80100) {
            $this->assertTrue(enum_exists('TestNamespace\SimpleEnum', true));
        }
    }
    
    public function testAutoloadWithInvalidDirectory(): void
    {
        // Test that invalid directory returns false (warning is expected but we don't test for it)
        $result = @Autoloader::autoload('/nonexistent/directory');
        $this->assertFalse($result, 'Autoloader should fail for nonexistent directory');
    }
    
    public function testAutoloadWithOptions(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        $result = Autoloader::autoload($simpleDir, [
            'extensions' => ['php'],
            'case_sensitive' => false,
            'prepend' => true
        ]);
        
        $this->assertTrue($result, 'Autoloader should register with options');
        $this->assertTrue(class_exists('TestNamespace\SimpleClass', true));
    }
    
    public function testAutoloadWithExclusions(): void
    {
        $result = Autoloader::autoload($this->fixturesDir, [
            'exclude' => ['broken/*', 'temp/*']
        ]);
        
        $this->assertTrue($result, 'Autoloader should register with exclusions');
        
        // Should be able to load non-excluded classes
        $this->assertTrue(class_exists('TestNamespace\SimpleClass', true));
        $this->assertTrue(class_exists('GlobalClass', true));
    }
    
    public function testGenerateAutoloader(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        $generatedCode = Autoloader::generateAutoloader($simpleDir);
        
        $this->assertIsString($generatedCode, 'Should generate autoloader code');
        $this->assertStringContainsString('<?php', $generatedCode);
        $this->assertStringContainsString('TestNamespace\\\\SimpleClass', $generatedCode);
        $this->assertStringContainsString('spl_autoload_register', $generatedCode);
    }
    
    public function testGenerateAutoloaderWithOptions(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        $generatedCode = Autoloader::generateAutoloader($simpleDir, [
            'namespace' => 'Custom',
            'class_name' => 'CustomAutoloader',
            'case_sensitive' => true,
            'prepend' => true
        ]);
        
        $this->assertIsString($generatedCode, 'Should generate autoloader code with options');
        $this->assertStringContainsString('<?php', $generatedCode);
        $this->assertStringContainsString('TestNamespace\\\\SimpleClass', $generatedCode);
    }
    
    public function testGenerateAutoloaderWithInvalidDirectory(): void
    {
        // Test that invalid directory returns false (warning is expected but we don't test for it)
        $result = @Autoloader::generateAutoloader('/nonexistent/directory');
        $this->assertFalse($result, 'Should return false for invalid directory');
    }
    
    public function testGenerateAutoloaderArray(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        $mapping = Autoloader::generateAutoloaderArray($simpleDir);
        
        $this->assertIsArray($mapping, 'Should return mapping array');
        $this->assertArrayHasKey('TestNamespace\\SimpleClass', $mapping);
        $this->assertArrayHasKey('TestNamespace\\SimpleInterface', $mapping);
        $this->assertArrayHasKey('TestNamespace\\SimpleTrait', $mapping);
    }
    
    public function testGenerateAutoloaderArrayWithInvalidDirectory(): void
    {
        // Test that invalid directory returns false (warning is expected but we don't test for it)
        $result = @Autoloader::generateAutoloaderArray('/nonexistent/directory');
        $this->assertFalse($result, 'Should return false for invalid directory');
    }
    
    public function testGetRegisteredLoaders(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        
        // Initially should be empty
        $loaders = Autoloader::getRegisteredLoaders();
        $this->assertIsArray($loaders);
        $this->assertEmpty($loaders);
        
        // Register an autoloader
        Autoloader::autoload($simpleDir);
        
        // Should now have one loader
        $loaders = Autoloader::getRegisteredLoaders();
        $this->assertCount(1, $loaders);
        $this->assertArrayHasKey('directory', $loaders[0]);
        $this->assertArrayHasKey('class_count', $loaders[0]);
        $this->assertGreaterThan(0, $loaders[0]['class_count']);
    }
    
    public function testClearCache(): void
    {
        // This method doesn't return anything, just ensure it doesn't throw
        Autoloader::clearCache();
        $this->assertTrue(true, 'clearCache should execute without error');
    }
    
    public function testUnregisterAll(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        
        // Register some autoloaders
        Autoloader::autoload($simpleDir);
        Autoloader::autoload($this->fixturesDir . '/global');
        
        $loaders = Autoloader::getRegisteredLoaders();
        $this->assertGreaterThan(0, count($loaders));
        
        // Unregister all
        $count = Autoloader::unregisterAll();
        $this->assertGreaterThan(0, $count);
        
        // Should be empty now
        $loaders = Autoloader::getRegisteredLoaders();
        $this->assertEmpty($loaders);
    }
    
    public function testMultipleNamespaces(): void
    {
        // Load directory with multiple namespaces
        $result = Autoloader::autoload($this->fixturesDir);
        $this->assertTrue($result);
        
        // Test classes from different namespaces
        $this->assertTrue(class_exists('TestNamespace\\SimpleClass', true));
        $this->assertTrue(class_exists('Deep\\Nested\\Namespace\\DeepClass', true));
        $this->assertTrue(class_exists('MultipleClasses\\FirstClass', true));
        $this->assertTrue(class_exists('MultipleClasses\\SecondClass', true));
        $this->assertTrue(class_exists('GlobalClass', true));
    }
    
    public function testCaseInsensitiveLoading(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        Autoloader::autoload($simpleDir, ['case_sensitive' => false]);
        
        // Test case insensitive loading
        $this->assertTrue(class_exists('testnamespace\\simpleclass', true));
        $this->assertTrue(class_exists('TESTNAMESPACE\\SIMPLECLASS', true));
    }
    
    public function testCaseSensitiveLoading(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        Autoloader::autoload($simpleDir, ['case_sensitive' => true]);
        
        // Test case sensitive loading - correct case should work
        $this->assertTrue(class_exists('TestNamespace\\SimpleClass', true));
        
        // For case sensitive mode, we test by checking if the autoloader mapping
        // contains exact case matches only
        $mapping = Autoloader::generateAutoloaderArray($simpleDir, ['case_sensitive' => true]);
        $this->assertArrayHasKey('TestNamespace\\SimpleClass', $mapping);
        
        // The mapping should not contain lowercase versions
        $hasExactCase = false;
        $hasWrongCase = false;
        
        foreach (array_keys($mapping) as $className) {
            if ($className === 'TestNamespace\\SimpleClass') {
                $hasExactCase = true;
            }
            if (strtolower($className) === 'testnamespace\\simpleclass' && $className !== 'TestNamespace\\SimpleClass') {
                $hasWrongCase = true;
            }
        }
        
        $this->assertTrue($hasExactCase, 'Should have exact case match');
        $this->assertFalse($hasWrongCase, 'Should not have wrong case variants in case-sensitive mode');
    }
    
    public function testGlobalFunctionAutoload(): void
    {
        // Test the global autoload function
        $result = \pal\autoload($this->fixturesDir . '/simple');
        $this->assertTrue($result);
        $this->assertTrue(class_exists('TestNamespace\\SimpleClass', true));
    }
    
    public function testGlobalFunctionGenerateAutoloader(): void
    {
        // Test the global generate_autoloader function
        $code = \pal\generate_autoloader($this->fixturesDir . '/simple');
        $this->assertIsString($code);
        $this->assertStringContainsString('TestNamespace\\\\SimpleClass', $code);
    }
    
    public function testGlobalFunctionGenerateAutoloaderArray(): void
    {
        // Test the global generate_autoloader_array function
        $mapping = \pal\generate_autoloader_array($this->fixturesDir . '/simple');
        $this->assertIsArray($mapping);
        $this->assertArrayHasKey('TestNamespace\\SimpleClass', $mapping);
    }
    
    public function testLoadClassInstance(): void
    {
        Autoloader::autoload($this->fixturesDir . '/simple');
        
        // Create instances to ensure classes are properly loaded
        $instance = new \TestNamespace\SimpleClass();
        $this->assertInstanceOf(\TestNamespace\SimpleClass::class, $instance);
        $this->assertEquals('TestNamespace\\SimpleClass', $instance->getClassName());
    }
    
    public function testTraitUsage(): void
    {
        Autoloader::autoload($this->fixturesDir . '/simple');
        
        // Create a class that uses the trait
        $traitUser = new class {
            use \TestNamespace\SimpleTrait;
        };
        
        $this->assertEquals('TestNamespace\\SimpleTrait', $traitUser->getTraitName());
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

    /**
     * Check if a path is absolute (cross-platform)
     */
    private function isAbsolutePath(string $path): bool
    {
        // Unix/Linux: starts with /
        if (substr($path, 0, 1) === '/') {
            return true;
        }
        
        // Windows: starts with drive letter like C:\ or C:/
        if (PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Za-z]:[\/\\\\]/', $path)) {
            return true;
        }
        
        return false;
    }

    public function testInMemoryAutoloadingNotAffectedByRelativeOption(): void
    {
        // Create test directory and class
        $testDir = $this->tempDir . '/inmemory_test';
        mkdir($testDir, 0755, true);
        
        $classContent = '<?php
        namespace InMemoryTest;
        
        class InMemoryClass {
            public function getMessage() {
                return "In-memory autoloading works!";
            }
        }';
        
        file_put_contents($testDir . '/InMemoryClass.php', $classContent);
        
        // Register autoloader with relative option (should not affect in-memory behavior)
        $result = Autoloader::autoload($testDir, ['relative' => true]);
        $this->assertTrue($result, 'Autoloader should register successfully');
        
        // Test that in-memory autoloading works normally
        $this->assertTrue(class_exists('InMemoryTest\\InMemoryClass'), 'Class should be autoloaded normally');
        
        $instance = new \InMemoryTest\InMemoryClass();
        $this->assertEquals('In-memory autoloading works!', $instance->getMessage());
        
        // Clean up
        Autoloader::unregisterAll();
    }

    public function testGenerateAutoloaderArrayNotAffectedByRelativeOption(): void
    {
        // Create test directory
        $testDir = $this->tempDir . '/array_test';
        mkdir($testDir, 0755, true);
        
        $classContent = '<?php
        namespace ArrayTest;
        class ArrayClass {}';
        
        file_put_contents($testDir . '/ArrayClass.php', $classContent);
        
        // Generate array mapping (should always return absolute paths regardless of options)
        $mapping = Autoloader::generateAutoloaderArray($testDir, ['relative' => true]);
        
        $this->assertIsArray($mapping);
        $this->assertArrayHasKey('ArrayTest\\ArrayClass', $mapping);
        
        // The mapping should always contain absolute paths for runtime use
        $filePath = $mapping['ArrayTest\\ArrayClass'];
        $this->assertTrue($this->isAbsolutePath($filePath), 'Path should be absolute');

        $this->assertStringContainsString(str_replace('\\', '/', $testDir), str_replace('\\', '/', $filePath));
        $this->assertTrue(file_exists($filePath));
    }
    
    public function testAutoloadWithIncludeStaticDisabled(): void
    {
        $staticDir = $this->fixturesDir . '/static_disabled';
        
        // Record initial function state
        $initialFunctionState = function_exists('pal_test_disabled_function');
        $initialMathState = function_exists('pal_test_disabled_math');
        
        $result = Autoloader::autoload($staticDir, [
            'include_static' => false
        ]);
        
        $this->assertTrue($result, 'Autoloader should register successfully');
        
        // Static functions should NOT be loaded when include_static is false
        // The function state should remain the same as before autoloading
        $this->assertEquals($initialFunctionState, function_exists('pal_test_disabled_function'), 
            'Function existence should not change when include_static is false');
        $this->assertEquals($initialMathState, function_exists('pal_test_disabled_math'),
            'Function existence should not change when include_static is false');
    }
    
    public function testAutoloadWithIncludeStaticEnabled(): void
    {
        $staticDir = $this->fixturesDir . '/static';
        
        // Record initial function state
        $initialFunctionOneState = function_exists('pal_test_function_one');
        $initialNamespacedState = function_exists('TestNamespace\\pal_test_namespaced_function');
        
        $result = Autoloader::autoload($staticDir, [
            'include_static' => true
        ]);
        
        $this->assertTrue($result, 'Autoloader should register successfully');
        
        // Static functions should now be available (or remain available if already loaded)
        $this->assertTrue(function_exists('pal_test_function_one'));
        $this->assertTrue(function_exists('pal_test_function_two'));
        $this->assertTrue(function_exists('pal_test_function_three'));
        $this->assertTrue(function_exists('TestNamespace\\pal_test_namespaced_function'));
        $this->assertTrue(function_exists('TestNamespace\\pal_test_math_function'));
        
        // Test that the functions work
        $this->assertEquals('function_one_result', pal_test_function_one());
        $this->assertEquals(10, pal_test_function_two(5));
        $this->assertTrue(pal_test_function_three());
        $this->assertEquals('namespaced_function_result', \TestNamespace\pal_test_namespaced_function());
        $this->assertEquals(7, \TestNamespace\pal_test_math_function(3, 4));
        
        // Mixed file function should NOT be auto-included (it contains a class)
        $this->assertFalse(function_exists('mixed_function'));
    }
    
    public function testGenerateAutoloaderWithIncludeStatic(): void
    {
        $staticDir = $this->fixturesDir . '/static';
        
        $generated = Autoloader::generateAutoloader($staticDir, [
            'include_static' => true,
            'relative' => false
        ]);
        
        $this->assertIsString($generated);
        $this->assertStringContainsString('Static files:', $generated);
        $this->assertStringContainsString('Include static: true', $generated);
        $this->assertStringContainsString('static_files', $generated);
        $this->assertStringContainsString('functions.php', $generated);
        $this->assertStringContainsString('namespaced_functions.php', $generated);
        
        // Check that mixed.php is in the mapping (for the class) but NOT in the static files array
        $this->assertStringContainsString('MixedClass', $generated); // Class should be in mapping
        // But static_files array should only contain functions.php and namespaced_functions.php
        $staticFilesSection = strstr($generated, '// Static files to include');
        $staticFilesSection = strstr($staticFilesSection, '];', true); // Get until end of array
        $this->assertStringNotContainsString('mixed.php', $staticFilesSection); // Should not be in static files
        $this->assertStringNotContainsString('constants.php', $staticFilesSection); // Should not be in static files
        
        // Write to file and test execution
        $autoloaderFile = $this->tempDir . '/test_static_autoloader.php';
        file_put_contents($autoloaderFile, $generated);
        
        // Include the generated autoloader in a separate process to test
        $this->assertTrue(file_exists($autoloaderFile));
    }
    
    public function testGenerateAutoloaderWithIncludeStaticDisabled(): void
    {
        $staticDir = $this->fixturesDir . '/static';
        
        $generated = Autoloader::generateAutoloader($staticDir, [
            'include_static' => false,
            'relative' => false
        ]);
        
        $this->assertIsString($generated);
        $this->assertStringContainsString('Include static: false', $generated);
        $this->assertStringContainsString('static_files', $generated);
        
        // Should contain the static files array but not include them
        $this->assertStringContainsString('[]', $generated); // Empty static files array
    }
    
    public function testParseStaticFileDetection(): void
    {
        $staticDir = $this->fixturesDir . '/static';
        
        // Test static function files (should be detected as static)
        $functionsFile = $staticDir . '/functions.php';
        $namespacedFile = $staticDir . '/namespaced_functions.php';
        
        // Test mixed file (should NOT be detected as static)
        $mixedFile = $staticDir . '/mixed.php';
        $constantsFile = $staticDir . '/constants.php';
        
        // Use reflection to access private method
        $method = new \ReflectionMethod('pal\Autoloader', 'parseStaticFile');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invokeArgs(null, [$functionsFile]));
        $this->assertTrue($method->invokeArgs(null, [$namespacedFile]));
        $this->assertFalse($method->invokeArgs(null, [$mixedFile]));
        $this->assertFalse($method->invokeArgs(null, [$constantsFile]));
    }
}
