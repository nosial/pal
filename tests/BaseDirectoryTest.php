<?php
namespace PAL\Tests;

use pal\Autoloader;
use PHPUnit\Framework\TestCase;

class BaseDirectoryTest extends TestCase
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
    
    /**
     * Helper method to recursively remove a directory
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
    
    public function testBaseDirectoryWithAbsolutePath(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        $baseDirectory = '/my/app';
        
        $autoloaderCode = Autoloader::generateAutoloader($simpleDir, [
            'relative' => true,
            'base_directory' => $baseDirectory,
            'include_static' => false
        ]);
        
        $this->assertIsString($autoloaderCode, 'Autoloader code should be generated');
        
        // Check that the base directory is used in paths instead of __DIR__
        $this->assertStringNotContainsString('__DIR__', $autoloaderCode, 
            'Generated code should not contain __DIR__ when base_directory is specified');
        
        // Check that paths start with the base directory
        $this->assertStringContainsString("'/my/app/", $autoloaderCode, 
            'Generated code should contain paths starting with /my/app/');
    }
    
    public function testBaseDirectoryWithTrailingSlash(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        $baseDirectory = '/my/app/';
        
        $autoloaderCode = Autoloader::generateAutoloader($simpleDir, [
            'relative' => true,
            'base_directory' => $baseDirectory,
            'include_static' => false
        ]);
        
        $this->assertIsString($autoloaderCode, 'Autoloader code should be generated');
        
        // Check that paths are normalized (no double slashes)
        $this->assertStringNotContainsString("'/my/app//", $autoloaderCode, 
            'Generated code should not contain double slashes in paths');
        
        // Check that paths start with the normalized base directory
        $this->assertStringContainsString("'/my/app/", $autoloaderCode, 
            'Generated code should contain paths starting with /my/app/');
    }
    
    public function testBaseDirectoryWithoutLeadingSlash(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        $baseDirectory = 'my/app';
        
        $autoloaderCode = Autoloader::generateAutoloader($simpleDir, [
            'relative' => true,
            'base_directory' => $baseDirectory,
            'include_static' => false
        ]);
        
        $this->assertIsString($autoloaderCode, 'Autoloader code should be generated');
        
        // Check that paths start with the base directory as provided
        $this->assertStringContainsString("'my/app/", $autoloaderCode, 
            'Generated code should contain paths starting with my/app/');
    }
    
    public function testBaseDirectoryWithComplexPath(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        $baseDirectory = '/var/www/html/my-application';
        
        $autoloaderCode = Autoloader::generateAutoloader($simpleDir, [
            'relative' => true,
            'base_directory' => $baseDirectory,
            'include_static' => false
        ]);
        
        $this->assertIsString($autoloaderCode, 'Autoloader code should be generated');
        $this->assertStringContainsString("'/var/www/html/my-application/", $autoloaderCode, 
            'Generated code should contain the complex base directory path');
    }
    
    public function testBaseDirectoryWithStaticFiles(): void
    {
        $staticDir = $this->fixturesDir . '/static';
        $baseDirectory = '/my/app';
        
        $autoloaderCode = Autoloader::generateAutoloader($staticDir, [
            'relative' => true,
            'base_directory' => $baseDirectory,
            'include_static' => true
        ]);
        
        $this->assertIsString($autoloaderCode, 'Autoloader code should be generated');
        
        // Check that static files also use the base directory
        $this->assertStringContainsString("'/my/app/", $autoloaderCode, 
            'Generated code should contain static file paths starting with /my/app/');
        
        // Verify static files are included
        $this->assertStringContainsString('static_files', $autoloaderCode,
            'Generated code should include static files array');
    }
    
    public function testBaseDirectoryWithNestedStructure(): void
    {
        $nestedDir = $this->fixturesDir . '/nested';
        $baseDirectory = '/app/src';
        
        $autoloaderCode = Autoloader::generateAutoloader($nestedDir, [
            'relative' => true,
            'base_directory' => $baseDirectory,
            'include_static' => false
        ]);
        
        $this->assertIsString($autoloaderCode, 'Autoloader code should be generated');
        
        // Check that nested paths include the base directory
        $this->assertStringContainsString("'/app/src/", $autoloaderCode, 
            'Generated code should contain nested paths with base directory');
        
        // Check that the deep class is mapped correctly
        $this->assertStringContainsString('DeepClass', $autoloaderCode,
            'Generated code should contain the DeepClass mapping');
    }
    
    public function testRelativeWithoutBaseDirectoryUsesDir(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        
        $autoloaderCode = Autoloader::generateAutoloader($simpleDir, [
            'relative' => true,
            'include_static' => false
        ]);
        
        $this->assertIsString($autoloaderCode, 'Autoloader code should be generated');
        
        // When base_directory is not specified, __DIR__ should be used
        $this->assertStringContainsString('__DIR__', $autoloaderCode, 
            'Generated code should contain __DIR__ when base_directory is not specified');
    }
    
    public function testRelativeFalseIgnoresBaseDirectory(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        $baseDirectory = '/my/app';
        
        $autoloaderCode = Autoloader::generateAutoloader($simpleDir, [
            'relative' => false,
            'base_directory' => $baseDirectory,
            'include_static' => false
        ]);
        
        $this->assertIsString($autoloaderCode, 'Autoloader code should be generated');
        
        // When relative is false, base_directory should be ignored
        $this->assertStringNotContainsString('/my/app/', $autoloaderCode, 
            'Generated code should not use base_directory when relative is false');
        
        // Should contain absolute paths instead
        $this->assertStringContainsString($this->fixturesDir, $autoloaderCode,
            'Generated code should contain absolute paths when relative is false');
    }
    
    public function testBaseDirectoryWithBackslashes(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        $baseDirectory = 'C:\\my\\app';
        
        $autoloaderCode = Autoloader::generateAutoloader($simpleDir, [
            'relative' => true,
            'base_directory' => $baseDirectory,
            'include_static' => false
        ]);
        
        $this->assertIsString($autoloaderCode, 'Autoloader code should be generated');
        
        // Backslashes should be normalized to forward slashes
        $this->assertStringContainsString("'C:/my/app/", $autoloaderCode, 
            'Generated code should normalize backslashes to forward slashes');
        
        // Check that the Windows-style path was properly normalized (no backslashes in output)
        $this->assertStringNotContainsString("'C:\\\\my", $autoloaderCode,
            'Generated code should not contain backslash-based paths');
    }
    
    public function testGenerateAutoloaderArrayWithBaseDirectory(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        $baseDirectory = '/my/app';
        
        $result = Autoloader::generateAutoloaderArray($simpleDir, [
            'relative' => true,
            'base_directory' => $baseDirectory,
            'include_static' => false
        ]);
        
        $this->assertIsArray($result, 'generateAutoloaderArray should return an array');
        
        // generateAutoloaderArray returns the raw mapping directly (not wrapped in an array)
        // The mapping contains actual file paths for runtime use, not the custom base_directory
        $this->assertNotEmpty($result, 'Mapping should not be empty');
        
        // Verify it contains class names as keys and file paths as values
        foreach ($result as $className => $filePath) {
            $this->assertIsString($className, 'Class name should be a string');
            $this->assertIsString($filePath, 'File path should be a string');
            $this->assertFileExists($filePath, 'Mapped file should exist');
        }
    }
    
    public function testBaseDirectoryPathValidation(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        
        // Test with various base directory formats
        $testPaths = [
            '/absolute/path',
            'relative/path',
            '/path/with/trailing/',
            'path/no/leading/slash',
            '/path/../with/../dots',
        ];
        
        foreach ($testPaths as $basePath) {
            $autoloaderCode = Autoloader::generateAutoloader($simpleDir, [
                'relative' => true,
                'base_directory' => $basePath,
                'include_static' => false
            ]);
            
            $this->assertIsString($autoloaderCode,
                "Autoloader code should be generated with base_directory: $basePath");
        }
    }
    
    public function testBaseDirectoryDoesNotAffectClassNames(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        $baseDirectory = '/my/app';
        
        $autoloaderCode = Autoloader::generateAutoloader($simpleDir, [
            'relative' => true,
            'base_directory' => $baseDirectory,
            'include_static' => false
        ]);
        
        $this->assertIsString($autoloaderCode, 'Autoloader code should be generated');
        
        // Class names should remain unchanged
        $this->assertStringContainsString('SimpleClass', $autoloaderCode,
            'Class names should be preserved');
        $this->assertStringContainsString('TestNamespace', $autoloaderCode,
            'Namespaces should be preserved');
    }
    
    public function testEmptyBaseDirectoryBehavesLikeNotSet(): void
    {
        $simpleDir = $this->fixturesDir . '/simple';
        
        $autoloaderWithEmpty = Autoloader::generateAutoloader($simpleDir, [
            'relative' => true,
            'base_directory' => '',
            'include_static' => false
        ]);
        
        $autoloaderWithoutOption = Autoloader::generateAutoloader($simpleDir, [
            'relative' => true,
            'include_static' => false
        ]);
        
        $this->assertIsString($autoloaderWithEmpty, 'Autoloader with empty base_directory should generate');
        $this->assertIsString($autoloaderWithoutOption, 'Autoloader without base_directory should generate');
        
        // Both should use __DIR__
        $this->assertStringContainsString('__DIR__', $autoloaderWithEmpty,
            'Empty base_directory should fall back to __DIR__');
        $this->assertStringContainsString('__DIR__', $autoloaderWithoutOption,
            'No base_directory should use __DIR__');
    }
}
