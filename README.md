<div align="center">
  <strong>📦 wp-helpers</strong>
  <p>Handy functions for WordPress</p>
  ![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/syntatis/wp-helpers/php) [![wp](https://github.com/syntatis/wp-helpers/actions/workflows/wp.yml/badge.svg)](https://github.com/syntatis/wp-helpers/actions/workflows/wp.yml)
</div>

---

The `syntatis/wp-helpers` package provides a set of reusable functions designed to simplify common tasks in WordPress plugins and themes.

## Installation

Install the package via Composer:

```bash
composer require syntatis/wp-helpers
```

## Usage

The following functions are included in this package:

| Function            | Description                                   |
|---------------------|-----------------------------------------------|
| `is_plugin_updated` | Checks whether the plugin is updated.         |
| `is_wp_cli`         | Checks if the current environment is WP-CLI.  |

### Examples

The `is_plugin_updated` function checks if a specific plugin has been updated. It is intended to be used within the `upgrader_process_complete` action hook.

```php
use WP_Upgrader;
use function Syntatis\WPHelpers\is_plugin_updated;

add_action('upgrader_process_complete', function (WP_Upgrader $upgrader, array $hookExtra) {
    if (is_plugin_updated($hookExtra, 'plugin-name/plugin-name.php')) {
        // Perform actions after the plugin has been updated.
    }
});
```
