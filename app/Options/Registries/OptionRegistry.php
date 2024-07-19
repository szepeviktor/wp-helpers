<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Options\Registries;

use Syntatis\WPHelpers\Options\Contracts\Registrable;
use Syntatis\WPHelpers\Options\Option;
use Syntatis\WPHelpers\Options\Support\InputSanitizer;
use Syntatis\WPHelpers\Options\Support\InputValidator;
use Syntatis\WPHelpers\Options\Support\OutputResolver;
use Syntatis\WPHook\Contracts\Hookable;
use Syntatis\WPHook\Registry as WPHookRegistry;

use function array_merge;
use function trim;

class OptionRegistry implements Registrable, Hookable
{
	private WPHookRegistry $hook;

	private Option $option;

	private int $strict;

	private string $optionName;

	private ?string $settingGroup = null;

	public function __construct(Option $option, int $strict = 0)
	{
		$this->option = $option;
		$this->optionName = $option->getName();
		$this->strict = $strict;
	}

	/**
	 * Set the name of the setting group for the option. By setting the group,
	 * the option will be registered with the WordPress settings API.
	 *
	 * @see https://developer.wordpress.org/reference/functions/register_setting/
	 */
	public function setSettingGroup(?string $settingGroup = null): void
	{
		$this->settingGroup = $settingGroup;
	}

	/**
	 * Set the option prefix. e.g. `wp_starter_plugin_`.
	 */
	public function setPrefix(string $prefix = ''): void
	{
		$this->optionName = trim($prefix) . $this->optionName;
	}

	/**
	 * Retrieve the option name to register, which may contain the prefix if set.
	 */
	public function getName(): string
	{
		return $this->optionName;
	}

	public function hook(WPHookRegistry $hook): void
	{
		$this->hook = $hook;
	}

	public function register(): void
	{
		$optionType = $this->option->getType();
		$optionPriority = $this->option->getPriority();

		$inputSanitizer = new InputSanitizer();
		$outputResolver = new OutputResolver($optionType, $this->strict);
		$inputValidator = new InputValidator($optionType, $this->strict);
		$inputValidator->setConstraints($this->option->getConstraints());
		$sanitizeCallback = static fn ($value) => $inputSanitizer->sanitize($value);

		$this->hook->addFilter(
			'default_option_' . $this->optionName,
			function ($default, $option, $passedDefault) use ($outputResolver) {
				return $outputResolver->resolve($passedDefault ? $default : $this->option->getDefault());
			},
			$optionPriority,
			3,
		);

		$this->hook->addFilter(
			'option_' . $this->optionName,
			static fn ($value) => $outputResolver->resolve($value),
			$optionPriority,
		);

		$this->hook->addFilter(
			'sanitize_option_' . $this->optionName,
			$sanitizeCallback,
			$optionPriority,
		);

		$this->hook->addAction(
			'add_option',
			function ($optionName, $value) use ($inputValidator): void {
				if ($optionName !== $this->optionName) {
					return;
				}

				$inputValidator->validate($value);
			},
			$optionPriority,
			2,
		);

		$this->hook->addAction(
			'update_option',
			function ($optionName, $oldValue, $newValue) use ($inputValidator): void {
				if ($optionName !== $this->optionName) {
					return;
				}

				$inputValidator->validate($newValue);
			},
			$optionPriority,
			3,
		);

		if (! $this->settingGroup) {
			return;
		}

		$settingArgs = $this->option->getSettingArgs();
		$registerSetting = fn () => register_setting(
			$this->settingGroup,
			$this->optionName,
			array_merge(
				$settingArgs,
				['sanitize_callback' => $sanitizeCallback],
			),
		);

		$this->hook->addAction('admin_init', $registerSetting);

		if (! ($settingArgs['show_in_rest'] ?? false)) {
			return;
		}

		$this->hook->addAction('rest_api_init', $registerSetting);
	}

	public function deregister(): void
	{
		if ($this->settingGroup) {
			unregister_setting($this->settingGroup, $this->optionName);
		}

		$this->hook->deregister();

		delete_option($this->optionName);
	}
}
