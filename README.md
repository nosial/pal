# PHP Autoloader (PAL)

PHP Autoloader (PAL) is a single-file PHP implementation of the autoloading mechanism for PHP with the goal to simplify
autoloading PHP source files from a given directory path.


## Features

- Autoload an existing PHP source directory without the need of using an autoloader file
- Generate an autoloader as a PHP code or as an array for programmatic purposes
- Extremely simple to use

## Requirements

- PHP 8.3 or higher
- The tokenizer extension enabled (usually enabled by default)
- The SPL extension enabled (usually enabled by default)

## Usage

PAL can be used simply by including/requiring the [`Autoloader.php`](src/Autoloader.php) file and calling it's public
methods, additionally you can use the [`Makefile`](Makefile) to copy over the `Autoloader.php` file to the target
directory as `pal.php` file or build a `pal.phar` file for easier distribution.

### Autoloading a directory

PAL expects a directory where the PHP source files are located, it will then scan the directory recursively and register an
autoloader for the found PHP source files. The given directory can contain multiple different namespaces and classes, pal
is designed to handle most common use cases.

```php
require 'pal.php';
\pal\Autoloader::autoload('/example/src');
```

Additionally, a public method `autoload` is available which can be used to autoload multiple directories, this is an
alias for calling `\pal\Autoloader::autoload`

```php
require 'pal.php';
use function pal\autoload;
autoload('/example/src');
```

This method also accepts options to adjust the autoloading behavior, for example to exclude certain directories.

```php
require 'pal.php';
\pal\Autoloader::autoload('/example/src', [
    'extensions' => ['php'], // only include files with .php extension
    'exclude' => ['tests', 'vendor'], // exclude directories named tests or vendor
    'case_sensitive' => false, // make class name matching case insensitive
    'follow_symlinks' => true, // follow symbolic links when scanning directories
    'prepend' => false, // prepend the autoloader to the autoload stack, default: false (append)
]);
```

### Generating an autoloader

PAL can also generate an autoloader as a PHP code or as an array for programmatic purposes. This can be useful if you want
to generate an autoloader once and then include it in your project which can be faster than scanning the directory on every
request.

```php
require 'pal.php';
$autoloaderCode = \pal\Autoloader::generateAutoloader('/example/src');
// or
$autoloaderArray = \pal\Autoloader::generateAutoloaderArray('/example/src');
```

And a public method `generate_autoloader` is also available which can be used to generate an autoloader for multiple directories,
this is an alias for calling `\pal\Autoloader::generateAutoloader`

```php
require 'pal.php';
use function \pal\generate_autoloader;
$autoloaderCode = generate_autoloader('/example/src');
// or
use function \pal\generate_autoloader_array;
$autoloaderArray = generate_autoloader_array('/example/src');
```

Similarly to the `autoload` method, the `generateAutoloader` and `generateAutoloaderArray` methods also accept options to
adjust the autoloader generation behavior.

```php
require 'pal.php';
$autoloaderCode = \pal\Autoloader::generateAutoloader('/example/src', [
    'extensions' => ['php'], // only include files with .php extension
    'exclude' => ['tests', 'vendor'], // exclude directories named tests or vendor
    'case_sensitive' => false, // make class name matching case insensitive
    'follow_symlinks' => true, // follow symbolic links when scanning directories
    'prepend' => false, // prepend the autoloader to the autoload stack, default: false (append)
]);
```

The generated autoloader code can be saved as a PHP file and included in your project without requiring the `pal.php` file.

```php
file_put_contents('/example/autoload.php', $autoloaderCode);
require '/example/autoload.php';
```

### Management Methods

PAL also provides some management methods to interact with the autoloader after it has been registered.

- `getRegisteredLoaders()`: Returns an array of all registered autoloaders.
- `clearCache()`: Clears the internal cache of the autoloader.
- `unregisterAll()`: Unregisters all autoloaders that have been registered by PAL.

### Building

To build distribution files:

```bash
# Build pal.php (copy of Autoloader.php)
make build

# Build pal.phar (PHAR archive)
make phar

# Build both
make all

# Clean build artifacts
make clean
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
