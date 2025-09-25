SOURCE_PATH = src/pal/Autoloader.php
TARGET_DIR = target
PAL_FILE = $(TARGET_DIR)/pal.php
PHAR_FILE = $(TARGET_DIR)/pal.phar
PHPUNIT_PHAR = phpunit.phar

.PHONY: all clean build phar test test-setup install-phpunit

all: build phar

clean:
	rm -rf $(TARGET_DIR)
	rm -f $(PHPUNIT_PHAR)
	rm -rf .phpunit.cache
	rm -rf coverage
	rm -rf tests/results
	rm -rf tests/temp
	rm -f pal.tar.gz

$(TARGET_DIR):
	mkdir -p $(TARGET_DIR)

build: $(PAL_FILE)

$(PAL_FILE): $(SOURCE_PATH) | $(TARGET_DIR)
	cp $(SOURCE_PATH) $(PAL_FILE)
	@echo "Built pal.php successfully: $(PAL_FILE)"

phar: $(PHAR_FILE)

$(PHAR_FILE): $(SOURCE_PATH) | $(TARGET_DIR)
	php -d phar.readonly=0 -r " \
		\$$phar = new Phar('$(PHAR_FILE)', FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, 'pal.phar'); \
		\$$phar->startBuffering(); \
		\$$phar->addFromString('pal.php', file_get_contents('$(SOURCE_PATH)')); \
		\$$phar->setStub('<?php require_once \"phar://\" . __FILE__ . \"/pal.php\"; __HALT_COMPILER(); ?>'); \
		\$$phar->stopBuffering(); \
		echo \"PHAR archive created successfully: $(PHAR_FILE)\n\"; \
	"

install-phpunit:
	@if ! command -v phpunit >/dev/null 2>&1 && [ ! -f $(PHPUNIT_PHAR) ]; then \
		echo "Downloading PHPUnit..."; \
		curl -L https://phar.phpunit.de/phpunit-10.phar -o $(PHPUNIT_PHAR); \
		chmod +x $(PHPUNIT_PHAR); \
	fi

test-setup: install-phpunit
	@mkdir -p tests/results

test: test-setup
	@if command -v phpunit >/dev/null 2>&1; then \
		phpunit --testdox --stderr; \
	elif [ -f $(PHPUNIT_PHAR) ]; then \
		php $(PHPUNIT_PHAR) --testdox --stderr; \
	else \
		echo "PHPUnit not found. Run 'make install-phpunit' first."; \
		exit 1; \
	fi

test-coverage: test-setup
	@if command -v phpunit >/dev/null 2>&1; then \
		phpunit --coverage-html coverage --testdox; \
	elif [ -f $(PHPUNIT_PHAR) ]; then \
		php $(PHPUNIT_PHAR) --coverage-html coverage --testdox; \
	else \
		echo "PHPUnit not found. Run 'make install-phpunit' first."; \
		exit 1; \
	fi

# CI-friendly targets
ci-test: test-setup
	@if command -v phpunit >/dev/null 2>&1; then \
		phpunit --log-junit tests/results/junit.xml --testdox-text tests/results/testdox.txt --stderr; \
	elif [ -f $(PHPUNIT_PHAR) ]; then \
		php $(PHPUNIT_PHAR) --log-junit tests/results/junit.xml --testdox-text tests/results/testdox.txt --stderr; \
	else \
		echo "PHPUnit not found. Run 'make install-phpunit' first."; \
		exit 1; \
	fi

test-brief: test-setup
	@if command -v phpunit >/dev/null 2>&1; then \
		phpunit --no-coverage --no-output; \
	elif [ -f $(PHPUNIT_PHAR) ]; then \
		php $(PHPUNIT_PHAR) --no-coverage --no-output; \
	else \
		echo "PHPUnit not found. Run 'make install-phpunit' first."; \
		exit 1; \
	fi

# Development helpers
dev-test:
	@while inotifywait -e modify -r src/ tests/ 2>/dev/null; do \
		clear; \
		make test; \
	done

# Package for distribution
package: clean all
	@echo "Packaging PAL for distribution..."
	@tar -czf pal.tar.gz -C $(TARGET_DIR) pal.php pal.phar
	@echo "Package created: pal.tar.gz"
