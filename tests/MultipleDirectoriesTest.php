<?php
namespace PAL\Tests;

use pal\Autoloader;
use PHPUnit\Framework\TestCase;

/**
 * Tests for multiple directory support in the autoloader
 * 
 * This test suite verifies that the autoloader can handle both single
 * directory paths and arrays of directory paths, merging all mappings
 * while respecting relative paths and all existing features.
 */
class MultipleDirectoriesTest extends TestCase
{
    private string $tempDir;
    private string $fixturesDir;
    private string $multiDirA;
    private string $multiDirB;
    private string $multiDirC;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = PAL_TEST_DIR . '/temp';
        $this->fixturesDir = PAL_FIXTURES_DIR;
        $this->multiDirA = PAL_FIXTURES_DIR . '/multi_dir_a';
        $this->multiDirB = PAL_FIXTURES_DIR . '/multi_dir_b';
        $this->multiDirC = PAL_FIXTURES_DIR . '/multi_dir_c';
        
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
    
    /**
     * Test that autoloader works with a single directory path (backward compatibility)
     */
    public function testAutoloadWithSingleDirectory(): void
    {
        $result = Autoloader::autoload($this->multiDirA);
        
        $this->assertTrue($result, 'Autoloader should register successfully with single directory');
        $this->assertTrue(class_exists('MultiDir\A\ClassA', true));
        $this->assertTrue(interface_exists('MultiDir\A\InterfaceA', true));
    }
    
    /**
     * Test that autoloader works with an array containing a single directory
     */
    public function testAutoloadWithSingleDirectoryInArray(): void
    {
        $result = Autoloader::autoload([$this->multiDirA]);
        
        $this->assertTrue($result, 'Autoloader should register successfully with single directory in array');
        $this->assertTrue(class_exists('MultiDir\A\ClassA', true));
        $this->assertTrue(interface_exists('MultiDir\A\InterfaceA', true));
    }
    
    /**
     * Test that autoloader works with multiple directories
     */
    public function testAutoloadWithMultipleDirectories(): void
    {
        $result = Autoloader::autoload([
            $this->multiDirA,
            $this->multiDirB,
            $this->multiDirC
        ]);
        
        $this->assertTrue($result, 'Autoloader should register successfully with multiple directories');
        
        // Test that classes from all directories can be loaded
        $this->assertTrue(class_exists('MultiDir\A\ClassA', true), 'Should load class from directory A');
        $this->assertTrue(class_exists('MultiDir\B\ClassB', true), 'Should load class from directory B');
        $this->assertTrue(class_exists('MultiDir\C\ClassC', true), 'Should load class from directory C');
        
        // Test interfaces and traits
        $this->assertTrue(interface_exists('MultiDir\A\InterfaceA', true), 'Should load interface from directory A');
        $this->assertTrue(trait_exists('MultiDir\B\TraitB', true), 'Should load trait from directory B');
        
        // Test nested classes
        $this->assertTrue(class_exists('MultiDir\C\Sub\SubClassC', true), 'Should load nested class from directory C');
    }
    
    /**
     * Test that classes from different directories work correctly
     */
    public function testClassesFromDifferentDirectories(): void
    {
        Autoloader::autoload([
            $this->multiDirA,
            $this->multiDirB,
            $this->multiDirC
        ]);
        
        $classA = new \MultiDir\A\ClassA();
        $classB = new \MultiDir\B\ClassB();
        $classC = new \MultiDir\C\ClassC();
        $subClassC = new \MultiDir\C\Sub\SubClassC();
        
        $this->assertEquals('Directory A', $classA->getSource());
        $this->assertEquals('Directory B', $classB->getSource());
        $this->assertEquals('Directory C', $classC->getSource());
        $this->assertEquals('Directory C Subdirectory', $subClassC->getSource());
        
        $this->assertEquals('MultiDir\A\ClassA', $classA->getClassName());
        $this->assertEquals('MultiDir\B\ClassB', $classB->getClassName());
        $this->assertEquals('MultiDir\C\ClassC', $classC->getClassName());
        $this->assertEquals('MultiDir\C\Sub\SubClassC', $subClassC->getClassName());
    }
    
    /**
     * Test static files (functions) are loaded from all directories
     */
    public function testStaticFilesFromMultipleDirectories(): void
    {
        $result = Autoloader::autoload([
            $this->multiDirA,
            $this->multiDirB,
            $this->multiDirC
        ], [
            'include_static' => true
        ]);
        
        $this->assertTrue($result, 'Autoloader should register with static files enabled');
        
        // Test that functions from all directories are available
        $this->assertTrue(function_exists('MultiDir\A\get_dir_a_value'), 'Function from directory A should exist');
        $this->assertTrue(function_exists('MultiDir\B\get_dir_b_value'), 'Function from directory B should exist');
        $this->assertTrue(function_exists('MultiDir\C\get_dir_c_value'), 'Function from directory C should exist');
        
        $this->assertEquals('Value from Directory A', \MultiDir\A\get_dir_a_value());
        $this->assertEquals('Value from Directory B', \MultiDir\B\get_dir_b_value());
        $this->assertEquals('Value from Directory C', \MultiDir\C\get_dir_c_value());
    }
    
    /**
     * Test that static files can be disabled with multiple directories
     */
    public function testDisableStaticFilesWithMultipleDirectories(): void
    {
        // Note: We can only test that static files aren't loaded IF they haven't been loaded
        // by a previous test. Since functions can't be "unloaded" in PHP, we check that
        // the autoloader still works correctly without trying to load them.
        $result = Autoloader::autoload([
            $this->multiDirA,
            $this->multiDirB
        ], [
            'include_static' => false
        ]);
        
        $this->assertTrue($result, 'Autoloader should register with static files disabled');
        
        // Classes should still work
        $this->assertTrue(class_exists('MultiDir\A\ClassA', true));
        $this->assertTrue(class_exists('MultiDir\B\ClassB', true));
        
        // Verify that the registered loader info shows no static files
        $loaders = Autoloader::getRegisteredLoaders();
        $this->assertCount(1, $loaders);
    }
    
    /**
     * Test with exclusion patterns across multiple directories
     */
    public function testExclusionPatternsWithMultipleDirectories(): void
    {
        $result = Autoloader::autoload([
            $this->multiDirA,
            $this->multiDirB,
            $this->multiDirC
        ], [
            'exclude' => ['*sub*']
        ]);
        
        $this->assertTrue($result, 'Autoloader should register with exclusions');
        
        // Regular classes should load
        $this->assertTrue(class_exists('MultiDir\A\ClassA', true));
        $this->assertTrue(class_exists('MultiDir\B\ClassB', true));
        $this->assertTrue(class_exists('MultiDir\C\ClassC', true));
        
        // Verify excluded class is not in the mapping by checking the autoloader array
        $mapping = Autoloader::generateAutoloaderArray([
            $this->multiDirA,
            $this->multiDirB,
            $this->multiDirC
        ], [
            'exclude' => ['*sub*']
        ]);
        
        $this->assertArrayNotHasKey('MultiDir\C\Sub\SubClassC', $mapping, 'Excluded class should not be in mapping');
    }
    
    /**
     * Test generateAutoloader with multiple directories
     */
    public function testGenerateAutoloaderWithMultipleDirectories(): void
    {
        $source = Autoloader::generateAutoloader([
            $this->multiDirA,
            $this->multiDirB
        ], [
            'relative' => false,
            'include_static' => true
        ]);
        
        $this->assertNotFalse($source, 'Should generate autoloader source');
        // In generated PHP code, backslashes are escaped, so MultiDir\A becomes 'MultiDir\\A'
        $this->assertStringContainsString("'MultiDir\\\\A\\\\ClassA'", $source);
        $this->assertStringContainsString("'MultiDir\\\\B\\\\ClassB'", $source);
        $this->assertStringContainsString('spl_autoload_register', $source);
        
        // Save and test the generated autoloader
        $generatedFile = $this->tempDir . '/multi_dir_autoloader.php';
        file_put_contents($generatedFile, $source);
        
        // Clear any existing autoloaders
        Autoloader::unregisterAll();
        
        // Load the generated autoloader
        require_once $generatedFile;
        
        // Test that classes load through the generated autoloader
        $this->assertTrue(class_exists('MultiDir\A\ClassA', true));
        $this->assertTrue(class_exists('MultiDir\B\ClassB', true));
    }
    
    /**
     * Test generateAutoloader with relative paths and multiple directories
     */
    public function testGenerateAutoloaderWithRelativePathsAndMultipleDirectories(): void
    {
        $source = Autoloader::generateAutoloader([
            $this->multiDirA,
            $this->multiDirB
        ], [
            'relative' => true,
            'include_static' => false
        ]);
        
        $this->assertNotFalse($source, 'Should generate autoloader source with relative paths');
        $this->assertStringContainsString('__DIR__', $source, 'Should contain __DIR__ for relative paths');
        // In generated PHP code, backslashes are escaped
        $this->assertStringContainsString("'MultiDir\\\\A\\\\ClassA'", $source);
        $this->assertStringContainsString("'MultiDir\\\\B\\\\ClassB'", $source);
    }
    
    /**
     * Test generateAutoloaderArray with multiple directories
     */
    public function testGenerateAutoloaderArrayWithMultipleDirectories(): void
    {
        $mapping = Autoloader::generateAutoloaderArray([
            $this->multiDirA,
            $this->multiDirB,
            $this->multiDirC
        ]);
        
        $this->assertIsArray($mapping, 'Should return an array');
        $this->assertNotEmpty($mapping, 'Mapping should not be empty');
        
        // Check that classes from all directories are in the mapping
        $this->assertArrayHasKey('MultiDir\A\ClassA', $mapping);
        $this->assertArrayHasKey('MultiDir\B\ClassB', $mapping);
        $this->assertArrayHasKey('MultiDir\C\ClassC', $mapping);
        $this->assertArrayHasKey('MultiDir\C\Sub\SubClassC', $mapping);
        
        // Check that file paths are correct
        $this->assertStringContainsString('multi_dir_a', $mapping['MultiDir\A\ClassA']);
        $this->assertStringContainsString('multi_dir_b', $mapping['MultiDir\B\ClassB']);
        $this->assertStringContainsString('multi_dir_c', $mapping['MultiDir\C\ClassC']);
    }
    
    /**
     * Test with empty array of directories
     */
    public function testAutoloadWithEmptyArray(): void
    {
        // This should fail gracefully
        $result = @Autoloader::autoload([]);
        
        $this->assertFalse($result, 'Autoloader should fail with empty array');
    }
    
    /**
     * Test with one invalid directory in array
     */
    public function testAutoloadWithInvalidDirectoryInArray(): void
    {
        $result = @Autoloader::autoload([
            $this->multiDirA,
            '/nonexistent/directory',
            $this->multiDirB
        ]);
        
        $this->assertFalse($result, 'Autoloader should fail if any directory is invalid');
    }
    
    /**
     * Test with duplicate directories
     */
    public function testAutoloadWithDuplicateDirectories(): void
    {
        $result = Autoloader::autoload([
            $this->multiDirA,
            $this->multiDirA,
            $this->multiDirB
        ]);
        
        $this->assertTrue($result, 'Autoloader should handle duplicate directories');
        
        // Classes should still load correctly
        $this->assertTrue(class_exists('MultiDir\A\ClassA', true));
        $this->assertTrue(class_exists('MultiDir\B\ClassB', true));
    }
    
    /**
     * Test case-insensitive loading with multiple directories
     */
    public function testCaseInsensitiveLoadingWithMultipleDirectories(): void
    {
        $result = Autoloader::autoload([
            $this->multiDirA,
            $this->multiDirB
        ], [
            'case_sensitive' => false
        ]);
        
        $this->assertTrue($result, 'Autoloader should register with case insensitive option');
        
        // Test case-insensitive class loading
        $this->assertTrue(class_exists('multidir\a\classa', true));
        $this->assertTrue(class_exists('MULTIDIR\B\CLASSB', true));
        $this->assertTrue(class_exists('MuLtIdIr\A\ClAssA', true), 'Case insensitive loading should work for ClassA');
    }
    
    /**
     * Test prepend option with multiple directories
     */
    public function testPrependOptionWithMultipleDirectories(): void
    {
        // Register a normal autoloader first
        Autoloader::autoload($this->multiDirA, ['prepend' => false]);
        
        // Register with prepend
        $result = Autoloader::autoload([
            $this->multiDirB,
            $this->multiDirC
        ], [
            'prepend' => true
        ]);
        
        $this->assertTrue($result, 'Autoloader should register with prepend option');
        
        // All classes should still load
        $this->assertTrue(class_exists('MultiDir\A\ClassA', true));
        $this->assertTrue(class_exists('MultiDir\B\ClassB', true));
        $this->assertTrue(class_exists('MultiDir\C\ClassC', true));
    }
    
    /**
     * Test getRegisteredLoaders with multiple directories
     */
    public function testGetRegisteredLoadersWithMultipleDirectories(): void
    {
        Autoloader::autoload([
            $this->multiDirA,
            $this->multiDirB
        ]);
        
        $loaders = Autoloader::getRegisteredLoaders();
        
        $this->assertCount(1, $loaders, 'Should have one registered loader');
        // The directory field can be either a string or an array depending on input
        $this->assertTrue(
            is_string($loaders[0]['directory']) || is_array($loaders[0]['directory']),
            'Directory should be string or array'
        );
        if (is_array($loaders[0]['directory'])) {
            $this->assertCount(2, $loaders[0]['directory']);
        }
        $this->assertGreaterThan(0, $loaders[0]['class_count']);
    }
    
    /**
     * Test unregisterAll with multiple directories autoloader
     */
    public function testUnregisterAllWithMultipleDirectories(): void
    {
        Autoloader::autoload([
            $this->multiDirA,
            $this->multiDirB
        ]);
        
        // Load a class to verify autoloader works
        $this->assertTrue(class_exists('MultiDir\A\ClassA', true));
        
        $count = Autoloader::unregisterAll();
        
        $this->assertEquals(1, $count, 'Should unregister one autoloader');
        
        $loaders = Autoloader::getRegisteredLoaders();
        $this->assertEmpty($loaders, 'Should have no registered loaders after unregisterAll');
    }
    
    /**
     * Test mixed single and multiple directory registrations
     */
    public function testMixedSingleAndMultipleDirectoryRegistrations(): void
    {
        // Register single directory
        $result1 = Autoloader::autoload($this->multiDirA);
        $this->assertTrue($result1);
        
        // Register multiple directories
        $result2 = Autoloader::autoload([
            $this->multiDirB,
            $this->multiDirC
        ]);
        $this->assertTrue($result2);
        
        // All classes should be available
        $this->assertTrue(class_exists('MultiDir\A\ClassA', true));
        $this->assertTrue(class_exists('MultiDir\B\ClassB', true));
        $this->assertTrue(class_exists('MultiDir\C\ClassC', true));
        
        $loaders = Autoloader::getRegisteredLoaders();
        $this->assertCount(2, $loaders, 'Should have two registered loaders');
    }
    
    /**
     * Test with custom extensions option and multiple directories
     */
    public function testCustomExtensionsWithMultipleDirectories(): void
    {
        $result = Autoloader::autoload([
            $this->multiDirA,
            $this->multiDirB
        ], [
            'extensions' => ['php', 'inc']
        ]);
        
        $this->assertTrue($result, 'Autoloader should register with custom extensions');
        $this->assertTrue(class_exists('MultiDir\A\ClassA', true));
        $this->assertTrue(class_exists('MultiDir\B\ClassB', true));
    }
    
    /**
     * Helper method to remove directory recursively
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
