<?php
    namespace pal;
    
    use Exception;
    use FilesystemIterator;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;
    use RuntimeException;
    use SplFileInfo;
    
    class Autoloader
    {
        const int T_TRAIT_53 = 355;
        const int PHP_VERSION_ID_MIN = 80300;

        /**
         * Array of registered autoloader instances
         *
         * Contains information about each registered autoloader including:
         * - directory: The base directory path
         * - callback: The autoloader callback function
         * - mapping: Array mapping class names to file paths
         * 
         * @var array<string, array{directory: string, callback: callable, mapping: array<string, string>}>
         */
        private static array $registeredLoaders = [];
        
        /**
         * Cache for class-to-file mappings to improve performance
         * 
         * @var array<string, array<string, string>>
         */
        private static array $cachedMappings = [];
        
        /**
         * Flag to track if PHP version has been validated
         * 
         * @var bool
         */
        private static bool $phpVersionChecked = false;

        /**
         * Registers an autoloader for a specified directory
         *
         * Scans the given directory for PHP files, generates a mapping of class
         * names to file paths, and registers an autoloader that will load classes
         * on demand. Supports various configuration options.
         *
         * @param string $directoryPath The path to the PHP source files
         * @param array{
         *     extensions?: string[],
         *     exclude?: string[],
         *     case_sensitive?: bool,
         *     follow_symlinks?: bool,
         *     prepend?: bool
         * } $options Configuration options for the autoloader
         * @return bool True if the autoloader was registered successfully, false otherwise
         */
        public static function autoload(string $directoryPath, array $options=[]): bool
        {
            try
            {
                self::checkPhpVersion();
                
                // Validate directory
                if (!is_dir($directoryPath))
                {
                    trigger_error("PAL Autoloader: Directory '$directoryPath' does not exist", E_USER_WARNING);
                    return false;
                }
                
                if (!is_readable($directoryPath))
                {
                    trigger_error("PAL Autoloader: Directory '$directoryPath' is not readable", E_USER_WARNING);
                    return false;
                }
                
                // Generate the mapping
                $mapping = self::generateMappings($directoryPath, $options);
                if (empty($mapping))
                {
                    return false;
                }
                
                $prepend = isset($options['prepend']) && $options['prepend'];
                $caseInsensitive = !isset($options['case_sensitive']) || !$options['case_sensitive'];
                
                // Create autoloader closure with PHP 5.3+ compatibility
                $autoloader = self::createCallback($mapping, $caseInsensitive);
                
                // Register the autoloader
                $registered = spl_autoload_register($autoloader, true, $prepend);
                
                if ($registered)
                {
                    // Store reference to prevent garbage collection
                    $autoloaderId = md5($directoryPath . serialize($options));
                    self::$registeredLoaders[$autoloaderId] = array(
                        'directory' => $directoryPath,
                        'callback' => $autoloader,
                        'mapping' => $mapping
                    );
                }
                else
                {
                    trigger_error("PAL Autoloader: Failed to register autoloader for directory '$directoryPath'", E_USER_WARNING);
                }
            }
            catch (Exception $e)
            {
                trigger_error("PAL Autoloader: " . $e->getMessage(), E_USER_WARNING);
                return false;
            }

            return $registered;
        }

        /**
         * Generates a standalone PHP autoloader as source code
         *
         * Creates a complete PHP autoloader script that can be used independently
         * without requiring the PAL utility. The generated code includes all
         * necessary class-to-file mappings and autoloader logic.
         *
         * @param string $directoryPath The path to the PHP source files
         * @param array{
         *     extensions?: string[],
         *     exclude?: string[],
         *     case_sensitive?: bool,
         *     follow_symlinks?: bool,
         *     prepend?: bool,
         *     namespace?: string,
         *     class_name?: string,
         *     relative?: bool
         * } $options Configuration options for the autoloader generation
         * @return string|false The generated PHP autoloader source code, or false on failure
         */
        public static function generateAutoloader(string $directoryPath, array $options=[]): string|false
        {
            try
            {
                self::checkPhpVersion();

                // Validate directory
                if (!is_dir($directoryPath))
                {
                    trigger_error("PAL Autoloader: Directory '$directoryPath' does not exist", E_USER_WARNING);
                    return false;
                }

                if (!is_readable($directoryPath))
                {
                    trigger_error("PAL Autoloader: Directory '$directoryPath' is not readable", E_USER_WARNING);
                    return false;
                }

                // Generate the mapping
                $mapping = self::generateMappings($directoryPath, $options);
                if (empty($mapping))
                {
                    return false;
                }

                // Set default options
                $defaultOptions = [
                    'case_sensitive' => false,
                    'prepend' => false,
                    'namespace' => '',
                    'class_name' => 'Autoloader',
                    'relative' => true
                ];
                $options = array_merge($defaultOptions, $options);

                // Generate the PHP source code
                return self::buildAutoloaderSource($mapping, $options, $directoryPath);

            }
            catch (Exception $e)
            {
                trigger_error("PAL Autoloader: " . $e->getMessage(), E_USER_WARNING);
                return false;
            }
        }

        /**
         * Returns information about all registered autoloaders
         *
         * Provides an overview of all currently registered autoloaders,
         * including their base directories and the number of classes they
         * manage.
         *
         * @return array<int, array{directory: string, class_count: int}> Array of autoloader info
         */
        public static function getRegisteredLoaders(): array
        {
            return array_values(array_map(function ($info)
            {
                return array(
                    'directory' => $info['directory'],
                    'class_count' => count($info['mapping'])
                );
            }, self::$registeredLoaders));
        }

        /**
         * Clears the internal cache of class-to-file mappings
         *
         * Empties the cached mappings to force regeneration on the next
         * autoloader registration. Useful for development or dynamic
         * environments where files may change.
         *
         * @return void
         */
        public static function clearCache(): void
        {
            self::$cachedMappings = [];
        }

        /**
         * Unregisters all autoloaders registered by this class
         *
         * Iterates through all autoloaders registered via this class and
         * unregisters them from the SPL autoload stack. Returns the count
         * of successfully unregistered autoloaders.
         *
         * @return int The number of autoloaders that were unregistered
         */
        public static function unregisterAll(): int
        {
            $count = 0;

            foreach (self::$registeredLoaders as $info)
            {
                if (spl_autoload_unregister($info['callback']))
                {
                    $count++;
                }
            }

            self::$registeredLoaders = [];

            return $count;
        }

        /**
         * Generates and returns the class-to-file mapping array for a directory
         *
         * Scans the specified directory and generates the mapping array without
         * registering an autoloader. Useful for inspection or custom autoloader
         * implementations.
         *
         * @param string $directoryPath The path to the PHP source files
         * @param array{
         *     extensions?: string[],
         *     exclude?: string[],
         *     case_sensitive?: bool,
         *     follow_symlinks?: bool
         * } $options Configuration options for the mapping generation
         * @return false|array<string, string> The generated mapping array, or false if none found
         */
        public static function generateAutoloaderArray(string $directoryPath, array $options=[]): false|array
        {
            $mapping = self::generateMappings($directoryPath, $options);
            return empty($mapping) ? false : $mapping;
        }

        /**
         * Builds the complete PHP source code for a standalone autoloader
         *
         * Constructs a PHP script containing all the mapping data and autoloader
         * logic that can function independently of the PAL utility by simply
         * requiring or including the file.
         *
         * @param array<string, string> $mapping Array mapping class names to file paths
         * @param array{
         *     case_sensitive: bool,
         *     prepend: bool,
         *     namespace: string,
         *     class_name: string,
         *     relative: bool
         * } $options Configuration options for code generation
         * @param string $directoryPath The base directory path for relative path calculations
         * @return string The complete PHP autoloader source code
         */
        private static function buildAutoloaderSource(array $mapping, array $options, string $directoryPath): string
        {
            $caseInsensitive = !$options['case_sensitive'];
            $prepend = $options['prepend'];
            $relative = $options['relative'];

            // Build the mapping array as PHP code
            $mappingCode = self::buildMappingArrayCode($mapping, $relative, $directoryPath);

            // Generate timestamp and metadata
            $timestamp = date('Y-m-d H:i:s');
            $classCount = count($mapping);
            $caseInsensitiveText = $caseInsensitive ? 'true' : 'false';
            $prependText = $prepend ? 'true' : 'false';
            $relativeText = $relative ? 'true' : 'false';

            // Generate a unique autoloader ID to avoid conflicts
            $autoloaderId = 'pal_' . md5(serialize($mapping) . microtime(true));

            // Build the complete source code
            return <<<PHP
<?php
/**
 * Generated Standalone Autoloader
 * 
 * This autoloader was generated by PAL Autoloader on {$timestamp}
 * Total classes: {$classCount}
 * Case insensitive: {$caseInsensitiveText}
 * Prepend: {$prependText}
 * Relative paths: {$relativeText}
 * 
 * Usage: Simply require or include this file to register the autoloader
 * 
 * @see https://git.n64.cc/nosial/pal
 * @see https://github.com/nosial/pal
 * @see https://codeberg.org/nosial/pal
 * @generated
 */

// Prevent multiple registrations of the same autoloader
if (!defined('{$autoloaderId}')) 
{
    define('{$autoloaderId}', true);
    
    // Class to file mapping
    \$GLOBALS['{$autoloaderId}_mapping'] = {$mappingCode};
    
    // Configuration
    \$GLOBALS['{$autoloaderId}_case_insensitive'] = {$caseInsensitiveText};
    
    // Autoloader function
    \$GLOBALS['{$autoloaderId}_loader'] = function(\$className) 
    {
        \$mapping = \$GLOBALS['{$autoloaderId}_mapping'];
        \$caseInsensitive = \$GLOBALS['{$autoloaderId}_case_insensitive'];
        \$filePath = null;
        
        if (\$caseInsensitive)
        {
            foreach (\$mapping as \$mappedClass => \$mappedFile)
            {
                if (strcasecmp(\$mappedClass, \$className) === 0)
                {
                    \$filePath = \$mappedFile;
                    break;
                }
            }
        }
         else 
        {
            if (isset(\$mapping[\$className]))
            {
                \$filePath = \$mapping[\$className];
            }
        }
        
        if (!\$filePath || !is_file(\$filePath) || !is_readable(\$filePath)) 
        {
            return false;
        }
        
        try 
        {
            require_once \$filePath;
            return true;
        } 
        catch (\Exception \$e)
        {
            trigger_error("Autoloader: Failed to load '{\$filePath}': " . \$e->getMessage(), E_USER_WARNING);
            return false;
        }
    };
    
    // Register the autoloader
    spl_autoload_register(\$GLOBALS['{$autoloaderId}_loader'], true, {$prependText});
}
PHP;
        }


        /**
         * Converts a mapping array to PHP array code representation
         *
         * Takes the class-to-file mapping array and converts it into a properly
         * formatted PHP array definition string that can be embedded in source code.
         *
         * @param array<string, string> $mapping The mapping array to convert
         * @param bool $relative Whether to use relative paths with __DIR__
         * @param string $baseDirectory The base directory for relative path calculations
         * @return string PHP array code representation
         */
        private static function buildMappingArrayCode(array $mapping, bool $relative = false, string $baseDirectory = ''): string
        {
            if (empty($mapping))
            {
                return '[]';
            }

            $lines = ["["];

            foreach ($mapping as $className => $filePath)
            {
                $escapedClass = addslashes($className);
                
                if ($relative && $baseDirectory !== '')
                {
                    // Convert absolute path to relative path using __DIR__
                    $realBase = realpath($baseDirectory);
                    $realFile = realpath($filePath);
                    
                    if ($realBase !== false && $realFile !== false)
                    {
                        // Get relative path from base directory to file
                        $relativePath = self::getRelativePath($realBase, $realFile);
                        
                        // Format as __DIR__ . 'relative/path'
                        $escapedPath = addslashes($relativePath);
                        $lines[] = "        '{$escapedClass}' => __DIR__ . '{$escapedPath}',";
                    }
                    else
                    {
                        // Fallback to absolute path if unable to calculate relative path
                        $escapedPath = addslashes($filePath);
                        $lines[] = "        '{$escapedClass}' => '{$escapedPath}',";
                    }
                }
                else
                {
                    // Use absolute path
                    $escapedPath = addslashes($filePath);
                    $lines[] = "        '{$escapedClass}' => '{$escapedPath}',";
                }
            }

            $lines[] = "    ]";
            return implode("\n", $lines);
        }

        /**
         * Calculates the relative path from a base directory to a target file
         *
         * Computes the relative path needed to reach the target file from the base
         * directory. The result includes the leading separator for use with __DIR__.
         *
         * @param string $baseDir The base directory (should be absolute)
         * @param string $targetFile The target file path (should be absolute)
         * @return string The relative path from base to target, starting with separator
         */
        private static function getRelativePath(string $baseDir, string $targetFile): string
        {
            // Normalize paths to use forward slashes
            $baseDir = str_replace('\\', '/', rtrim($baseDir, '/'));
            $targetFile = str_replace('\\', '/', $targetFile);
            
            // Split paths into arrays
            $baseParts = explode('/', $baseDir);
            $targetParts = explode('/', dirname($targetFile));
            $fileName = basename($targetFile);
            
            // Find common path length
            $commonLength = 0;
            $minLength = min(count($baseParts), count($targetParts));
            
            for ($i = 0; $i < $minLength; $i++)
            {
                if ($baseParts[$i] === $targetParts[$i])
                {
                    $commonLength++;
                }
                else
                {
                    break;
                }
            }
            
            // Calculate the relative path
            $relativeParts = [];
            
            // Add .. for each directory we need to go up from base
            $upLevels = count($baseParts) - $commonLength;
            for ($i = 0; $i < $upLevels; $i++)
            {
                $relativeParts[] = '..';
            }
            
            // Add the remaining path parts to target
            for ($i = $commonLength; $i < count($targetParts); $i++)
            {
                $relativeParts[] = $targetParts[$i];
            }
            
            // Add the filename
            $relativeParts[] = $fileName;
            
            // Return the relative path with leading separator
            return '/' . implode('/', $relativeParts);
        }

        /**
         * Validates the current PHP version meets minimum requirements
         * 
         * Ensures the running PHP version is at least 8.3.0. This check is
         * performed only once per execution and the result is cached.
         *
         * @return void
         * @throws RuntimeException If PHP version is below 8.3.0
         */
        private static function checkPhpVersion(): void
        {
            if (self::$phpVersionChecked)
            {
                return;
            }
            
            if (PHP_VERSION_ID < self::PHP_VERSION_ID_MIN)
            {
                throw new RuntimeException('PAL Autoloader requires PHP 8.3 or higher. Current version: ' . PHP_VERSION);
            }
            
            self::$phpVersionChecked = true;
        }

        /**
         * Creates an autoloader callback function
         * 
         * Generates a closure that will be used by spl_autoload_register to
         * automatically load classes when they are first referenced.
         * 
         * @param array<string, string> $mapping Array mapping class names to file paths
         * @param bool $caseInsensitive Whether to perform case-insensitive class name matching
         * @return callable|array The autoloader callback function
         */
        private static function createCallback(array $mapping, bool $caseInsensitive): array|callable
        {
            // For PHP 5.3 compatibility, we need to be careful with closures
            if (PHP_VERSION_ID >= 50300)
            {
                return function($className) use ($mapping, $caseInsensitive)
                {
                    return self::loadClass($className, $mapping, $caseInsensitive);
                };
            }
            
            // Fallback for older PHP versions (though we require 5.3+)
            return array('PAL\\Autoloader', 'loadClassCallback');
        }

        /**
         * Loads a specific class from the filesystem
         * 
         * Attempts to load a class by finding its corresponding file in the mapping
         * and including it. Supports both case-sensitive and case-insensitive lookups.
         * 
         * @param string $className The fully qualified class name to load
         * @param array<string, string> $mapping Array mapping class names to file paths
         * @param bool $caseInsensitive Whether to perform case-insensitive matching
         * @return bool True if the class was successfully loaded, false otherwise
         */
        private static function loadClass(string $className, array $mapping, bool $caseInsensitive): bool
        {
            $filePath = null;
            
            // Handle case-insensitive lookup
            if ($caseInsensitive)
            {
                foreach ($mapping as $mappedClass => $mappedFile)
                {
                    if (strcasecmp($mappedClass, $className) === 0)
                    {
                        $filePath = $mappedFile;
                        break;
                    }
                }
            }
            else
            {
                if (isset($mapping[$className]))
                {
                    $filePath = $mapping[$className];
                }
            }
            
            if (!$filePath)
            {
                return false;
            }
            
            // Load the file with error handling
            if (is_file($filePath) && is_readable($filePath))
            {
                try
                {
                    require_once $filePath;
                    return true;
                }
                catch (Exception $e)
                {
                    trigger_error("PAL Autoloader: Failed to load '$filePath': " . $e->getMessage(), E_USER_WARNING);
                    return false;
                }
            }
            
            return false;
        }

        /**
         * Generates class-to-file mappings for a given directory
         * 
         * Recursively scans the specified directory for PHP files, parses them
         * to extract class, interface, trait, and enum definitions, and creates
         * a mapping array for use by the autoloader.
         * 
         * @param string $directory The directory path to scan for PHP files
         * @param array{
         *     extensions?: string[],
         *     exclude?: string[],
         *     case_sensitive?: bool,
         *     follow_symlinks?: bool
         * } $options Configuration options for the mapping generation
         * @return array<string, string> Array mapping class names to their file paths
         */
        private static function generateMappings(string $directory, array $options=[]): array
        {
            // Normalize directory path
            $realPath = realpath($directory);
            if ($realPath === false)
            {
                trigger_error("PAL Autoloader: Cannot resolve real path for directory '$directory'", E_USER_WARNING);
                return [];
            }

            $directory = rtrim($realPath, DIRECTORY_SEPARATOR);
            
            // Check cache
            $cacheKey = md5($directory . serialize($options));
            if (isset(self::$cachedMappings[$cacheKey]))
            {
                return self::$cachedMappings[$cacheKey];
            }
            
            // Set default options with PHP 5.3+ array syntax compatibility
            $defaultOptions = array(
                'extensions' => array('php'),
                'exclude' => [],
                'case_sensitive' => false,
                'follow_symlinks' => false
            );
            $options = array_merge($defaultOptions, $options);
            
            $mapping = [];
            
            try
            {
                // Create directory iterator with error handling
                if (!class_exists('RecursiveDirectoryIterator') || !class_exists('RecursiveIteratorIterator'))
                {
                    return [];
                }
                
                $dirIterator = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);
                $iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::LEAVES_ONLY);
                
                foreach ($iterator as $file)
                {
                    if (!($file instanceof SplFileInfo) || !$file->isFile())
                    {
                        continue;
                    }
                    
                    // Check extension
                    $extension = strtolower($file->getExtension());
                    if (!in_array($extension, $options['extensions']))
                    {
                        continue;
                    }
                    
                    // Get file path and handle potential errors
                    $filePath = $file->getRealPath();
                    if ($filePath === false)
                    {
                        continue;
                    }
                    
                    // Check exclusion patterns
                    $relativePath = str_replace($directory . DIRECTORY_SEPARATOR, '', $filePath);
                    if (self::isExcluded($relativePath, $options['exclude']))
                    {
                        continue;
                    }
                    
                    // Parse file for class definitions
                    $classes = self::parseSourceFile($filePath);
                    foreach ($classes as $className)
                    {
                        $mapping[$className] = $filePath;
                    }
                }
            }
            catch (Exception $e)
            {
                trigger_error("PAL Autoloader: Error scanning directory '$directory': " . $e->getMessage(), E_USER_WARNING);
                return []; // Return empty array instead of throwing
            }
            
            // Cache the result
            self::$cachedMappings[$cacheKey] = $mapping;
            return $mapping;
        }
        
        /**
         * Parses a PHP source file to extract class definitions
         * 
         * Tokenizes the PHP file and extracts all class, interface, trait, and enum
         * definitions along with their namespaces. Handles various PHP language
         * constructs and edge cases.
         * 
         * @param string $filePath The absolute path to the PHP file to parse
         * @return string[] Array of fully qualified class names found in the file
         */
        private static function parseSourceFile(string $filePath): array
        {
            // Safe file reading with error handling
            $content = @file_get_contents($filePath);
            if ($content === false)
            {
                trigger_error("PAL Autoloader: Cannot read file '$filePath'");
                return [];
            }
            
            // Tokenize the PHP code with error suppression
            $tokens = @token_get_all($content);
            if (!is_array($tokens))
            {
                return [];
            }
            
            $classes = [];
            $namespace = '';
            $tokenCount = count($tokens);
            $bracketLevel = 0;
            $namespaceBracketLevel = 0;
            $inClass = false;
            $aliases = []; // For use statements
            
            for ($i = 0; $i < $tokenCount; $i++)
            {
                $token = $tokens[$i];
                
                if (!is_array($token))
                {
                    if ($token === '{')
                    {
                        $bracketLevel++;
                    }
                    elseif ($token === '}')
                    {
                        $bracketLevel--;
                        if ($namespaceBracketLevel > 0 && $bracketLevel < $namespaceBracketLevel) {
                            $namespace = '';
                            $namespaceBracketLevel = 0;
                            $aliases = [];
                        }
                    }

                    continue;
                }
                
                list($tokenType) = $token;
                
                // Handle namespace declarations
                if ($tokenType === T_NAMESPACE)
                {
                    $namespace = self::extractNamespace($tokens, $i);
                    $aliases = []; // Reset aliases for new namespace
                    
                    // Check if this is a bracketed namespace
                    $j = $i + 1;
                    while ($j < $tokenCount && (is_array($tokens[$j]) || trim($tokens[$j]) === ''))
                    {
                        if ($tokens[$j] === '{')
                        {
                            $namespaceBracketLevel = $bracketLevel + 1;
                            break;
                        }

                        $j++;
                    }

                    continue;
                }
                
                // Handle use statements
                if ($tokenType === T_USE && !$inClass)
                {
                    $useStatement = self::extractUseStatement($tokens, $i);
                    if ($useStatement)
                    {
                        $aliases = array_merge($aliases, $useStatement);
                    }

                    continue;
                }
                
                // Handle class/interface/trait/enum definitions with PHP version compatibility
                $allowedTypes = array(T_CLASS, T_INTERFACE);
                
                // Add T_TRAIT support based on PHP version
                if (defined('T_TRAIT'))
                {
                    $allowedTypes[] = T_TRAIT;
                }
                elseif (defined('self::T_TRAIT_53'))
                {
                    $allowedTypes[] = self::T_TRAIT_53;
                }
                
                // Add T_ENUM support for PHP 8.1+
                if (defined('T_ENUM'))
                {
                    $allowedTypes[] = T_ENUM;
                }
                
                if (in_array($tokenType, $allowedTypes))
                {
                    // Handle anonymous classes (skip them)
                    if (self::isAnonymousClass($tokens, $i))
                    {
                        continue;
                    }
                    
                    // Handle ::class syntax (skip)
                    if (self::isClassConstant($tokens, $i))
                    {
                        continue;
                    }
                    
                    $className = self::extractClassName($tokens, $i);
                    if ($className)
                    {
                        $fullClassName = $namespace ? $namespace . '\\' . $className : $className;
                        $classes[] = $fullClassName;
                        $inClass = true;
                    }
                }
            }
            
            return array_unique($classes);
        }
        
        /**
         * Extracts namespace declaration from token array
         * 
         * Parses tokens starting from a T_NAMESPACE token to extract the full
         * namespace declaration. Handles both bracketed and non-bracketed namespace syntax.
         * 
         * @param array $tokens Array of PHP tokens from token_get_all()
         * @param int $startPos Starting position in the tokens array (T_NAMESPACE token)
         * @return string The extracted namespace string, or empty string if none found
         */
        private static function extractNamespace(array $tokens, int $startPos): string
        {
            $namespace = '';
            $i = $startPos + 1;
            
            while ($i < count($tokens))
            {
                $token = $tokens[$i];
                
                if (!is_array($token))
                {
                    if ($token === ';' || $token === '{')
                    {
                        break;
                    }
                    $i++;
                    continue;
                }
                
                if ($token[0] === T_STRING || $token[0] === T_NS_SEPARATOR)
                {
                    $namespace .= $token[1];
                }
                elseif (defined('T_NAME_QUALIFIED') && $token[0] === T_NAME_QUALIFIED)
                {
                    // PHP 8.0+ fully qualified name token
                    $namespace .= $token[1];
                }
                elseif (defined('T_NAME_FULLY_QUALIFIED') && $token[0] === T_NAME_FULLY_QUALIFIED)
                {
                    // PHP 8.0+ fully qualified name token starting with \
                    $namespace .= ltrim($token[1], '\\');
                }
                elseif ($token[0] === T_WHITESPACE)
                {
                    // Skip whitespace
                }
                else
                {
                    break;
                }

                $i++;
            }
            
            return trim($namespace, '\\');
        }
        
        /**
         * Extracts use statement and creates alias mapping
         * 
         * Parses T_USE tokens to extract import statements and their aliases.
         * Creates a mapping of alias names to their full qualified class names.
         * 
         * @param array $tokens Array of PHP tokens from token_get_all()
         * @param int $startPos Starting position in the tokens array (T_USE token)
         * @return array<string, string> Array mapping alias names to full class names
         */
        private static function extractUseStatement(array $tokens, int $startPos): array
        {
            $i = $startPos + 1;
            $useClause = '';
            $aliases = [];
            
            while ($i < count($tokens))
            {
                $token = $tokens[$i];
                
                if (!is_array($token))
                {
                    if ($token === ';')
                    {
                        break;
                    }

                    $i++;
                    continue;
                }
                
                if ($token[0] === T_STRING || $token[0] === T_NS_SEPARATOR)
                {
                    $useClause .= $token[1];
                }
                elseif ($token[0] === T_AS)
                {
                    // Handle aliased imports
                    $i++;
                    while ($i < count($tokens) && (!is_array($tokens[$i]) || $tokens[$i][0] === T_WHITESPACE))
                    {
                        $i++;
                    }

                    if ($i < count($tokens) && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING)
                    {
                        $alias = $tokens[$i][1];
                        $aliases[$alias] = $useClause;
                        $useClause = '';
                    }
                }
                elseif ($token[0] === T_WHITESPACE)
                {
                    // Skip whitespace
                }
                
                $i++;
            }
            
            // Handle simple use statement without alias
            if ($useClause)
            {
                $parts = explode('\\', $useClause);
                $alias = end($parts);
                $aliases[$alias] = $useClause;
            }
            
            return $aliases;
        }
        
        /**
         * Extracts class name from token array
         * 
         * Parses tokens following a class/interface/trait/enum declaration to
         * extract the class name identifier.
         * 
         * @param array $tokens Array of PHP tokens from token_get_all()
         * @param int $startPos Starting position in the tokens array (after class keyword)
         * @return string|null The extracted class name, or null if not found
         */
        private static function extractClassName(array $tokens, int $startPos): ?string
        {
            $i = $startPos + 1;
            
            while ($i < count($tokens))
            {
                $token = $tokens[$i];
                
                if (!is_array($token))
                {
                    $i++;
                    continue;
                }
                
                if ($token[0] === T_STRING)
                {
                    return $token[1];
                }
                elseif ($token[0] === T_WHITESPACE)
                {
                    // Skip whitespace
                }
                else
                {
                    break;
                }
                
                $i++;
            }
            
            return null;
        }
        
        /**
         * Determines if a class declaration is for an anonymous class
         * 
         * Looks backwards from the class token to check if it's preceded by
         * the 'new' keyword, indicating an anonymous class instantiation.
         * 
         * @param array $tokens Array of PHP tokens from token_get_all()
         * @param int $pos Position of the class token in the array
         * @return bool True if this is an anonymous class, false otherwise
         */
        private static function isAnonymousClass(array $tokens, int $pos): bool
        {
            // Look backwards for 'new' keyword
            for ($i = $pos - 1; $i >= 0; $i--)
            {
                $token = $tokens[$i];
                
                if (!is_array($token))
                {
                    continue;
                }
                
                if ($token[0] === T_NEW)
                {
                    return true;
                }
                elseif ($token[0] !== T_WHITESPACE && $token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT)
                {
                    break;
                }
            }
            
            return false;
        }
        
        /**
         * Determines if a 'class' token refers to the ::class constant
         * 
         * Checks if the 'class' keyword is preceded by the scope resolution
         * operator (::), indicating its being used as a class constant rather
         * than a class declaration.
         * 
         * @param array $tokens Array of PHP tokens from token_get_all()
         * @param int $pos Position of the class token in the array
         * @return bool True if this is a ::class constant reference, false otherwise
         */
        private static function isClassConstant(array $tokens, int $pos): bool
        {
            // Look backwards for double colon
            for ($i = $pos - 1; $i >= 0; $i--)
            {
                $token = $tokens[$i];
                
                if (!is_array($token))
                {
                    continue;
                }
                
                if ($token[0] === T_DOUBLE_COLON)
                {
                    return true;
                }
                elseif ($token[0] !== T_WHITESPACE && $token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT)
                {
                    break;
                }
            }
            
            return false;
        }
        
        /**
         * Checks if a file path matches any exclusion patterns
         * 
         * Uses fnmatch() to test if the given path matches any of the
         * configured exclusion patterns (supports wildcards).
         * 
         * @param string $path The relative file path to check
         * @param string[] $excludePatterns Array of exclusion patterns (supports wildcards)
         * @return bool True if the path should be excluded, false otherwise
         */
        private static function isExcluded(string $path, array $excludePatterns): bool
        {
            foreach ($excludePatterns as $pattern)
            {
                if (fnmatch($pattern, $path))
                {
                    return true;
                }
            }
            
            return false;
        }
    }

    // Global convenience functions
    if(!function_exists('autoload'))
    {
        /**
         * Registers an autoloader for a specified directory
         *
         * Scans the given directory for PHP files, generates a mapping of class
         * names to file paths, and registers an autoloader that will load classes
         * on demand. Supports various configuration options.
         *
         * @param string $directoryPath The path to the PHP source files
         * @param array{
         *     extensions?: string[],
         *     exclude?: string[],
         *     case_sensitive?: bool,
         *     follow_symlinks?: bool,
         *     prepend?: bool
         * } $options Configuration options for the autoloader
         * @return bool True if the autoloader was registered successfully, false otherwise
         */
        function autoload(string $directoryPath, array $options=[]): bool
        {
            return Autoloader::autoload($directoryPath, $options);
        }
    }

    if(!function_exists('generate_autoloader'))
    {
        /**
         * Generates a standalone PHP autoloader as source code
         *
         * Creates a complete PHP autoloader script that can be used independently
         * without requiring the PAL utility. The generated code includes all
         * necessary class-to-file mappings and autoloader logic.
         *
         * @param string $directoryPath The path to the PHP source files
         * @param array{
         *     extensions?: string[],
         *     exclude?: string[],
         *     case_sensitive?: bool,
         *     follow_symlinks?: bool,
         *     prepend?: bool,
         *     namespace?: string,
         *     class_name?: string
         * } $options Configuration options for the autoloader generation
         * @return string|false The generated PHP autoloader source code, or false on failure
         */
        function generate_autoloader(string $directoryPath, array $options=[]): string|false
        {
            return Autoloader::generateAutoloader($directoryPath, $options);
        }
    }

    if(!function_exists('generate_autoloader_array'))
    {
        /**
         * Generates and returns the class-to-file mapping array for a directory
         *
         * Scans the specified directory and generates the mapping array without
         * registering an autoloader. Useful for inspection or custom autoloader
         * implementations.
         *
         * @param string $directoryPath The path to the PHP source files
         * @param array{
         *     extensions?: string[],
         *     exclude?: string[],
         *     case_sensitive?: bool,
         *     follow_symlinks?: bool
         * } $options Configuration options for the mapping generation
         * @return false|array<string, string> The generated mapping array, or false if none found
         */
        function generate_autoloader_array(string $directoryPath, array $options=[]): false|array
        {
            return Autoloader::generateAutoloaderArray($directoryPath, $options);
        }
    }