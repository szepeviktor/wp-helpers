<div align="center">
  <strong>📦 wp-helpers</strong>
  <p>Reusable functions for WordPress</p>
</div>

---

This package contains a set of reusable functions for common functionalities used in WordPress plugins or themes.

| Function  | Description |
| --- | --- |
| `is_plugin_updated` |  Check whether the plugin is updated. This function should run within the `upgrader_process_complete` action hook, where the corresponding options requires in this function is passed. |
| `is_wp_cli` | Checks if the current environment is WP-CLI.. |

## Examples

All the functions are namespaced under `Syntatis\WPHelpers` namespace. You can import the functions using the `use` statement, for example.

```php
use WP_Upgrader;
use function Syntatis\WPHelpers\is_plugin_updated;

add_action('upgrader_process_complete', function (WP_Upgrader $upgrader, array $hookExtra) {
    if (is_plugin_updated($hookExtra, 'plugin-name/plugin-name.php')) {
      // Do something.
    }
});
```
