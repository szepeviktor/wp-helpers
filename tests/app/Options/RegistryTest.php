<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Tests\Options;

use Syntatis\WPHelpers\Options\NetworkOption;
use Syntatis\WPHelpers\Options\Option;
use Syntatis\WPHelpers\Options\Registry;
use Syntatis\WPHelpers\Tests\WPTestCase;
use Syntatis\WPHook\Registry as WPWPHookRegistry;

use function json_encode;

class RegistryTest extends WPTestCase
{
	private WPWPHookRegistry $hook;

	private string $optionGroup = 'tests';

	// phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
	public function set_up(): void
	{
		parent::set_up();

		$this->hook = new WPWPHookRegistry();
	}

	public function testUninstallOptions(): void
	{
		$registry = new Registry();
		$registry->addOptions(...[
			(new Option('say', 'string'))->withDefault('Hello, World!'),
			(new Option('count', 'number'))->withDefault(1),
			(new Option('list', 'array'))->withDefault(['count', 'two', 'three']),
		]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertSame('Hello, World!', get_option('say'));
		$this->assertSame(1, get_option('count'));
		$this->assertSame(['count', 'two', 'three'], get_option('list'));

		$this->assertTrue(update_option('say', 'Hello Earth!'));
		$this->assertTrue(update_option('count', 2));
		$this->assertTrue(update_option('list', ['four', 'five', 'six']));

		$this->assertSame('Hello Earth!', get_option('say'));
		$this->assertSame(2, get_option('count'));
		$this->assertSame(['four', 'five', 'six'], get_option('list'));

		$registry->deregister();

		$this->assertFalse(get_option('say'));
		$this->assertFalse(get_option('count'));
		$this->assertFalse(get_option('list'));
	}

	public function testUninstallRegisteredOptions(): void
	{
		$registry = new Registry();
		$registry->addOptions(...[
			(new Option('say', 'string'))->withDefault('Hello, World!'),
		]);
		$registry->hook($this->hook);
		$registry->register('tests');
		$this->hook->register();

		$registeredSettings = get_registered_settings();

		$this->assertArrayHasKey('say', $registeredSettings);
		$this->assertSame('string', $registeredSettings['say']['type']);
		$this->assertSame('tests', $registeredSettings['say']['group']);
		$this->assertSame('Hello, World!', $registeredSettings['say']['default']);

		$this->assertSame('Hello, World!', get_option('say'));
		$this->assertTrue(update_option('say', 'Hello, Earth!'));
		$this->assertSame('Hello, Earth!', get_option('say'));

		$registry->deregister('tests');

		$registeredSettings = get_registered_settings();

		$this->assertFalse(get_option('say'));
		$this->assertArrayNotHasKey('say', $registeredSettings);
	}

	/** @group network-option */
	public function testUninstallNetworkOptions(): void
	{
		$registry = new Registry();
		$registry->addOptions(...[
			(new NetworkOption('say', 'string'))->withDefault('Hello, World!'),
			(new NetworkOption('count', 'number'))->withDefault(1),
			(new NetworkOption('list', 'array'))->withDefault(['count', 'two', 'three']),
		]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertSame('Hello, World!', get_site_option('say'));
		$this->assertSame(1, get_site_option('count'));
		$this->assertSame(['count', 'two', 'three'], get_site_option('list'));

		$this->assertTrue(add_site_option('say', 'Hello Earth!'));
		$this->assertTrue(add_site_option('count', 2));
		$this->assertTrue(add_site_option('list', ['four', 'five', 'six']));

		$this->assertSame('Hello Earth!', get_site_option('say'));
		$this->assertSame(2, get_site_option('count'));
		$this->assertSame(['four', 'five', 'six'], get_site_option('list'));

		$this->assertTrue(update_site_option('say', 'Hello, Milkyway!'));
		$this->assertTrue(update_site_option('count', 3));
		$this->assertTrue(update_site_option('list', ['seven']));

		$this->assertSame('Hello, Milkyway!', get_site_option('say'));
		$this->assertSame(3, get_site_option('count'));
		$this->assertSame(['seven'], get_site_option('list'));

		$registry->deregister();

		$this->assertFalse(get_site_option('say'));
		$this->assertFalse(get_site_option('count'));
		$this->assertFalse(get_site_option('list'));
	}

	public function testJsonSerializable(): void
	{
		$registry = new Registry();
		$registry->addOptions(...[
			(new Option('one', 'string'))->withDefault('Hello, World!'),
			(new Option('two', 'number'))->withDefault(1),
			(new Option('three', 'array'))->withDefault(['count', 'two', 'three']),
			(new NetworkOption('foo', 'string'))->withDefault('Hello, World!'),
			(new NetworkOption('bar', 'number'))->withDefault(1),
			(new NetworkOption('baz', 'array'))->withDefault(['count', 'two', 'three']),
		]);
		$registry->setPrefix('tests_');
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertSame(
			'{"options":{"tests_one":"Hello, World!","tests_two":1,"tests_three":["count","two","three"]},"network_options":{"tests_foo":false,"tests_bar":false,"tests_baz":false}}',
			json_encode($registry),
		);
	}
}
