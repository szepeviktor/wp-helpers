<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers;

use function defined;

/**
 * Check whether the plugin is updated.
 *
 * This function should run within the `upgrader_process_complete` action hook,
 * where the corresponding options requires in this function is passed.
 *
 * @see https://developer.wordpress.org/reference/hooks/upgrader_process_complete/
 *
 * @param string               $pluginBasename The plugin basename. Example: `plugin-folder/plugin-file.php`.
 *                                             This can be obtained from the plugin's main file using
 *                                             WordPress native `plugin_basename` function.
 * @param array<string, mixed> $hookExtra
 *
 * @phpstan-param array{action: string, type: string, plugins?: array<string>} $hookExtra
 */
function is_plugin_updated(string $pluginBasename, array $hookExtra): bool
{
	if ($hookExtra['action'] === 'update' && $hookExtra['type'] === 'plugin' && isset($hookExtra['plugins'])) {
		foreach ($hookExtra['plugins'] as $plugin) {
			// Check if the plugin is the current plugin.
			if ($plugin !== $pluginBasename) {
				continue;
			}

			return true;
		}
	}

	return false;
}

/**
 * Checks if the current environment is WP-CLI.
 *
 * @return bool `true` if the current environment is WP-CLI, `false` otherwise.
 */
function is_wp_cli(): bool
{
	return defined('WP_CLI') && WP_CLI;
}
