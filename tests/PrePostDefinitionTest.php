<?php
namespace PAL\Tests;

use pal\Autoloader;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for pre_definition and post_definition options in the autoloader generator
 */
class PrePostDefinitionTest extends TestCase
{
    private string $tempDir;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = PAL_TEST_DIR . '/temp_pre_post';
        
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
    
    /**
     * Test that pre_definition code is inserted before the define() call
     */
    public function testPreDefinitionIsInsertedBeforeDefine(): void
    {
        $testDir = $this->tempDir . '/pre_def_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/TestClass.php', '<?php class TestPreDef { }');
        
        $preCode = 'echo "Before define";';
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir, [
            'pre_definition' => $preCode
        ]);
        
        $this->assertIsString($autoloaderCode);
        $this->assertStringContainsString('// Custom pre-definition code', $autoloaderCode);
        $this->assertStringContainsString('echo "Before define";', $autoloaderCode);
        
        // Verify pre_definition comes before define()
        $prePos = strpos($autoloaderCode, 'echo "Before define"');
        $definePos = strpos($autoloaderCode, "define('pal_");
        $this->assertLessThan($definePos, $prePos, 'Pre-definition code should appear before define()');
    }
    
    /**
     * Test that post_definition code is inserted after spl_autoload_register()
     */
    public function testPostDefinitionIsInsertedAfterRegister(): void
    {
        $testDir = $this->tempDir . '/post_def_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/TestClass.php', '<?php class TestPostDef { }');
        
        $postCode = 'echo "After register";';
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir, [
            'post_definition' => $postCode
        ]);
        
        $this->assertIsString($autoloaderCode);
        $this->assertStringContainsString('// Custom post-definition code', $autoloaderCode);
        $this->assertStringContainsString('echo "After register";', $autoloaderCode);
        
        // Verify post_definition comes after spl_autoload_register()
        $registerPos = strpos($autoloaderCode, 'spl_autoload_register');
        $postPos = strpos($autoloaderCode, 'echo "After register"');
        $this->assertGreaterThan($registerPos, $postPos, 'Post-definition code should appear after spl_autoload_register()');
    }
    
    /**
     * Test that both pre and post definitions work together
     */
    public function testBothPreAndPostDefinition(): void
    {
        $testDir = $this->tempDir . '/both_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/TestClass.php', '<?php class TestBoth { }');
        
        $preCode = '$before = true;';
        $postCode = '$after = true;';
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir, [
            'pre_definition' => $preCode,
            'post_definition' => $postCode
        ]);
        
        $this->assertIsString($autoloaderCode);
        $this->assertStringContainsString('$before = true;', $autoloaderCode);
        $this->assertStringContainsString('$after = true;', $autoloaderCode);
        
        // Verify order: pre_definition < define < spl_autoload_register < post_definition
        $prePos = strpos($autoloaderCode, '$before = true');
        $definePos = strpos($autoloaderCode, "define('pal_");
        $registerPos = strpos($autoloaderCode, 'spl_autoload_register');
        $postPos = strpos($autoloaderCode, '$after = true');
        
        $this->assertLessThan($definePos, $prePos);
        $this->assertLessThan($registerPos, $definePos);
        $this->assertLessThan($postPos, $registerPos);
    }
    
    /**
     * Test that PHP opening tags are removed from pre_definition
     */
    public function testPhpOpeningTagRemovalInPreDefinition(): void
    {
        $testDir = $this->tempDir . '/php_tag_pre_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/TestClass.php', '<?php class TestPhpTag { }');
        
        $preCode = '<?php echo "test";';
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir, [
            'pre_definition' => $preCode
        ]);
        
        $this->assertIsString($autoloaderCode);
        // Should not have duplicate PHP tags
        $this->assertStringNotContainsString('<?php echo "test";', $autoloaderCode);
        $this->assertStringContainsString('echo "test";', $autoloaderCode);
    }
    
    /**
     * Test that PHP uppercase opening tags are removed
     */
    public function testPhpUppercaseTagRemoval(): void
    {
        $testDir = $this->tempDir . '/php_uppercase_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/TestClass.php', '<?php class TestUppercase { }');
        
        $preCode = '<?PHP $var = 1;';
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir, [
            'pre_definition' => $preCode
        ]);
        
        $this->assertIsString($autoloaderCode);
        $this->assertStringNotContainsString('<?PHP', $autoloaderCode);
        $this->assertStringContainsString('$var = 1;', $autoloaderCode);
    }
    
    /**
     * Test that PHP closing tags are removed
     */
    public function testPhpClosingTagRemoval(): void
    {
        $testDir = $this->tempDir . '/php_close_tag_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/TestClass.php', '<?php class TestCloseTag { }');
        
        $postCode = '$var = 1; ?>';
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir, [
            'post_definition' => $postCode
        ]);
        
        $this->assertIsString($autoloaderCode);
        $this->assertStringContainsString('$var = 1;', $autoloaderCode);
        // Verify closing tag is removed (but not breaking the structure)
        $lines = explode("\n", $autoloaderCode);
        $foundPostCode = false;
        foreach ($lines as $line) {
            if (strpos($line, '$var = 1;') !== false) {
                $foundPostCode = true;
                $this->assertStringNotContainsString('?>', $line, 'Closing PHP tag should be removed');
            }
        }
        $this->assertTrue($foundPostCode, 'Post definition code should be present');
    }
    
    /**
     * Test multi-line code in pre_definition
     */
    public function testMultiLinePreDefinition(): void
    {
        $testDir = $this->tempDir . '/multiline_pre_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/TestClass.php', '<?php class TestMultiline { }');
        
        $preCode = '$var1 = 1;
$var2 = 2;
$var3 = 3;';
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir, [
            'pre_definition' => $preCode
        ]);
        
        $this->assertIsString($autoloaderCode);
        $this->assertStringContainsString('$var1 = 1;', $autoloaderCode);
        $this->assertStringContainsString('$var2 = 2;', $autoloaderCode);
        $this->assertStringContainsString('$var3 = 3;', $autoloaderCode);
    }
    
    /**
     * Test proper indentation of pre_definition code
     */
    public function testPreDefinitionIndentation(): void
    {
        $testDir = $this->tempDir . '/indent_pre_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/TestClass.php', '<?php class TestIndent { }');
        
        $preCode = 'if (true) {
    echo "indented";
}';
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir, [
            'pre_definition' => $preCode
        ]);
        
        $this->assertIsString($autoloaderCode);
        
        // The code should be indented with 4 spaces (1 level)
        $this->assertStringContainsString('    if (true) {', $autoloaderCode);
        $this->assertStringContainsString('        echo "indented";', $autoloaderCode);
        $this->assertStringContainsString('    }', $autoloaderCode);
    }
    
    /**
     * Test proper indentation of post_definition code
     */
    public function testPostDefinitionIndentation(): void
    {
        $testDir = $this->tempDir . '/indent_post_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/TestClass.php', '<?php class TestIndentPost { }');
        
        $postCode = 'foreach ($items as $item) {
    process($item);
}';
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir, [
            'post_definition' => $postCode
        ]);
        
        $this->assertIsString($autoloaderCode);
        
        // The code should be indented with 4 spaces (1 level)
        $this->assertStringContainsString('    foreach ($items as $item) {', $autoloaderCode);
        $this->assertStringContainsString('        process($item);', $autoloaderCode);
        $this->assertStringContainsString('    }', $autoloaderCode);
    }
    
    /**
     * Test that empty pre_definition is handled gracefully
     */
    public function testEmptyPreDefinition(): void
    {
        $testDir = $this->tempDir . '/empty_pre_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/TestClass.php', '<?php class TestEmpty { }');
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir, [
            'pre_definition' => ''
        ]);
        
        $this->assertIsString($autoloaderCode);
        $this->assertStringNotContainsString('// Custom pre-definition code', $autoloaderCode);
    }
    
    /**
     * Test that empty post_definition is handled gracefully
     */
    public function testEmptyPostDefinition(): void
    {
        $testDir = $this->tempDir . '/empty_post_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/TestClass.php', '<?php class TestEmptyPost { }');
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir, [
            'post_definition' => ''
        ]);
        
        $this->assertIsString($autoloaderCode);
        $this->assertStringNotContainsString('// Custom post-definition code', $autoloaderCode);
    }
    
    /**
     * Test that the generated autoloader with pre/post definition is syntactically valid
     */
    public function testGeneratedCodeSyntax(): void
    {
        $testDir = $this->tempDir . '/syntax_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/TestClass.php', '<?php class TestSyntax { }');
        
        $preCode = '<?php $pre_var = "before";';
        $postCode = '$post_var = "after"; ?>';
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir, [
            'pre_definition' => $preCode,
            'post_definition' => $postCode
        ]);
        
        $this->assertIsString($autoloaderCode);
        
        // Save to temp file and check syntax
        $tempFile = $this->tempDir . '/test_syntax_autoloader.php';
        file_put_contents($tempFile, $autoloaderCode);
        
        // Use php -l to check syntax
        exec("php -l " . escapeshellarg($tempFile) . " 2>&1", $output, $exitCode);
        
        $this->assertEquals(0, $exitCode, 'Generated autoloader should have valid PHP syntax. Output: ' . implode("\n", $output));
    }
    
    /**
     * Test functional execution with pre_definition
     */
    public function testFunctionalPreDefinition(): void
    {
        $testDir = $this->tempDir . '/functional_pre_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/FuncPreClass.php', '<?php
        namespace FuncPre;
        class FuncPreClass {
            public function test() { return "works"; }
        }');
        
        $preCode = 'define("PRE_TEST_CONSTANT", "pre_value");';
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir, [
            'pre_definition' => $preCode
        ]);
        
        $this->assertIsString($autoloaderCode);
        
        // Save and execute
        $autoloaderFile = $testDir . '/autoloader.php';
        file_put_contents($autoloaderFile, $autoloaderCode);
        
        $testScript = $this->tempDir . '/test_func_pre.php';
        $testCode = '<?php
        require_once ' . var_export($autoloaderFile, true) . ';
        
        // Check if pre_definition was executed
        if (defined("PRE_TEST_CONSTANT") && PRE_TEST_CONSTANT === "pre_value") {
            echo "PRE_OK";
        }
        
        // Check if class loading still works
        if (class_exists("FuncPre\\\\FuncPreClass")) {
            $obj = new FuncPre\\FuncPreClass();
            if ($obj->test() === "works") {
                echo "|CLASS_OK";
            }
        }';
        
        file_put_contents($testScript, $testCode);
        
        exec("php " . escapeshellarg($testScript), $output, $exitCode);
        $result = implode('', $output);
        
        $this->assertEquals('PRE_OK|CLASS_OK', $result, 'Pre-definition should execute and classes should still load');
    }
    
    /**
     * Test functional execution with post_definition
     */
    public function testFunctionalPostDefinition(): void
    {
        $testDir = $this->tempDir . '/functional_post_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/FuncPostClass.php', '<?php
        namespace FuncPost;
        class FuncPostClass {
            public function test() { return "works"; }
        }');
        
        $postCode = 'define("POST_TEST_CONSTANT", "post_value");';
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir, [
            'post_definition' => $postCode
        ]);
        
        $this->assertIsString($autoloaderCode);
        
        // Save and execute
        $autoloaderFile = $testDir . '/autoloader.php';
        file_put_contents($autoloaderFile, $autoloaderCode);
        
        $testScript = $this->tempDir . '/test_func_post.php';
        $testCode = '<?php
        require_once ' . var_export($autoloaderFile, true) . ';
        
        // Check if post_definition was executed
        if (defined("POST_TEST_CONSTANT") && POST_TEST_CONSTANT === "post_value") {
            echo "POST_OK";
        }
        
        // Check if class loading still works
        if (class_exists("FuncPost\\\\FuncPostClass")) {
            $obj = new FuncPost\\FuncPostClass();
            if ($obj->test() === "works") {
                echo "|CLASS_OK";
            }
        }';
        
        file_put_contents($testScript, $testCode);
        
        exec("php " . escapeshellarg($testScript), $output, $exitCode);
        $result = implode('', $output);
        
        $this->assertEquals('POST_OK|CLASS_OK', $result, 'Post-definition should execute and classes should still load');
    }
    
    /**
     * Test that code with PHP tags in middle is handled correctly
     */
    public function testPhpTagsInMiddleOfCode(): void
    {
        $testDir = $this->tempDir . '/php_middle_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/TestClass.php', '<?php class TestMiddle { }');
        
        // This is an edge case - code with PHP opening tag in the middle (which shouldn't normally happen)
        $preCode = '<?php
$var1 = 1;
$var2 = 2;';
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir, [
            'pre_definition' => $preCode
        ]);
        
        $this->assertIsString($autoloaderCode);
        $this->assertStringContainsString('$var1 = 1;', $autoloaderCode);
        $this->assertStringContainsString('$var2 = 2;', $autoloaderCode);
        
        // Ensure the code is still syntactically valid
        $tempFile = $this->tempDir . '/test_middle_autoloader.php';
        file_put_contents($tempFile, $autoloaderCode);
        
        exec("php -l " . escapeshellarg($tempFile) . " 2>&1", $output, $exitCode);
        $this->assertEquals(0, $exitCode, 'Generated code should be syntactically valid');
    }
    
    /**
     * Test with complex multi-line code that has nested structures
     */
    public function testComplexNestedCode(): void
    {
        $testDir = $this->tempDir . '/complex_test';
        mkdir($testDir, 0755, true);
        
        file_put_contents($testDir . '/TestClass.php', '<?php class TestComplex { }');
        
        $preCode = '<?php
if (!defined("COMPLEX_TEST")) {
    define("COMPLEX_TEST", true);
    
    function complexFunc() {
        return [
            "key1" => "value1",
            "key2" => "value2"
        ];
    }
}
?>';
        
        $autoloaderCode = Autoloader::generateAutoloader($testDir, [
            'pre_definition' => $preCode
        ]);
        
        $this->assertIsString($autoloaderCode);
        $this->assertStringContainsString('if (!defined("COMPLEX_TEST"))', $autoloaderCode);
        $this->assertStringContainsString('function complexFunc()', $autoloaderCode);
        
        // Verify syntax
        $tempFile = $this->tempDir . '/test_complex_autoloader.php';
        file_put_contents($tempFile, $autoloaderCode);
        
        exec("php -l " . escapeshellarg($tempFile) . " 2>&1", $output, $exitCode);
        $this->assertEquals(0, $exitCode, 'Complex nested code should generate valid syntax');
    }
    
    /**
     * Helper method to recursively remove a directory
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        if (is_link($dir)) {
            unlink($dir);
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
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
        
        if (PHP_OS_FAMILY !== 'Windows') {
            @chmod($dir, 0755);
        }
        
        rmdir($dir);
    }
}
