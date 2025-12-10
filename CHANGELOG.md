## [1.0.4] - 2025-12-10

This release introduces a new feature and some improvements.

### Added
 - Add `base_directory` option to autoloader for customizable relative paths, allowing users to specify a base directory
   only when the `relative` option is enabled.


## [1.0.3] - 2025-09-29

This release introduces minor improvements.

### Added
 - Added the ability to parse and correctly include static php files that do not contain any classes or interfaces but
   only functions or constants, or even namespaced code. This is configurable using the new `include_static` option


## [1.0.2] - 2025-09-27

This release corrects some changes

### Removed
 - Removed unused options such as `namespace` and `class` for generating an autoloader

### Changed
 - Code cleanup


## [1.0.1] - 2025-09-26

This release adds a new feature.

### Added
 - Added a new option called `relative` to the `generateAutoloader` function, by default it is set to `true`, when
   enabled the generated autoloader will use relative paths instead of absolute paths which will allow the autoloader
   to be more portable across different environments.


## [1.0.0] - 2025-09-25

First stable release