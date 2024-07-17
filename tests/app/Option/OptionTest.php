<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Tests\Option;

use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use Syntatis\WPHelpers\Option\Exceptions\TypeError;
use Syntatis\WPHelpers\Option\Option;
use Syntatis\WPHelpers\Option\Registry;
use Syntatis\WPHelpers\Option\Support\InputSanitizer;
use Syntatis\WPHelpers\Tests\WPTestCase;
use Syntatis\WPHook\Registry as WPWPHookRegistry;
use WP_REST_Request;
use WP_UnitTest_Factory;

class OptionTest extends WPTestCase
{
	private static int $administrator;

	private WPWPHookRegistry $hook;

	private string $optionName = 'foo_bar';

	private string $optionGroup = 'tests';

	// phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
	public function set_up(): void
	{
		parent::set_up();

		$this->hook = new WPWPHookRegistry();
	}

	// phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
	public function tear_down(): void
	{
		delete_option($this->optionName);

		parent::tear_down();
	}

	public static function wpSetUpBeforeClass(WP_UnitTest_Factory $factory): void
	{
		self::$administrator = $factory->user->create(['role' => 'administrator']);
	}

	public static function wpTearDownAfterClass(): void
	{
		self::delete_user(self::$administrator);
	}

	/** @testdox should return the name */
	public function testName(): void
	{
		$option = new Option($this->optionName, 'string');

		$this->assertEquals($this->optionName, $option->getName());
	}

	/** @testdox should throw error when name is blank */
	public function testBlankName(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$option = new Option('', 'string');
	}

	/** @testdox should set and return the constraints */
	public function testConstraints(): void
	{
		$option = new Option($this->optionName, 'string');
		$option = $option->withConstraints('is_string');

		$this->assertEquals(['is_string'], $option->getConstraints());

		$option = new Option($this->optionName, 'string');
		$option = $option->withConstraints('is_string', 'is_numeric');

		$this->assertEquals(
			['is_string', 'is_numeric'],
			$option->getConstraints(),
		);
	}

	/** @testdox should set and return the priority */
	public function testPriority(): void
	{
		$option = new Option($this->optionName, 'string');

		$this->assertSame(99, $option->getPriority());

		$option = $option->withPriority(100);

		$this->assertSame(100, $option->getPriority());
	}

	/** @testdox should set and return the default value set */
	public function testSettingArgsDefault(): void
	{
		$option = (new Option($this->optionName, 'string'))->withDefault('bar');

		$this->assertEquals(
			[
				'type' => 'string',
				'default' => 'bar',
			],
			$option->getSettingArgs(),
		);
	}

	/** @testdox should set and return the description */
	public function testSettingArgsDescription(): void
	{
		$option = (new Option($this->optionName, 'string'))
			->withDescription('This is the description');

		$this->assertEquals(
			[
				'type' => 'string',
				'description' => 'This is the description',
				'default' => null,
			],
			$option->getSettingArgs(),
		);
	}

	/**
	 * @dataProvider dataSettingArgsTypeAPIConfig
	 * @testdox should override the inferred type when the type is set explicitly.
	 *
	 * @param mixed $config
	 */
	public function testSettingArgsTypeAPIConfig($config): void
	{
		$option = (new Option($this->optionName, 'string'))->apiEnabled($config);

		$this->assertEquals(
			$config,
			$option->getSettingArgs()['show_in_rest'],
		);
	}

	public function dataSettingArgsTypeAPIConfig(): iterable
	{
		yield [true];
		yield [false];
		yield [
			[
				'schema' => [
					'type'  => 'array',
					'items' => [
						'type'  => 'array',
						'items' => [
							'type'   => 'string',
							'format' => 'hex-color',
						],
					],
				],
			],
		];
	}

	/**
	 * @dataProvider dataRegistryNoDefaultSet
	 * @testdox it should return `null` when no default is set
	 *
	 * @param array<Option> $options
	 */
	public function testRegistryNoDefaultSet(array $options): void
	{
		$registry = new Registry();
		$registry->addOptions(...$options);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertNull(get_option($this->optionName));
	}

	public function dataRegistryNoDefaultSet(): iterable
	{
		yield [[new Option($this->optionName, 'string')]];
		yield [[new Option($this->optionName, 'boolean')]];
		yield [[new Option($this->optionName, 'integer')]];
		yield [[new Option($this->optionName, 'number')]];
		yield [[new Option($this->optionName, 'array')]];
	}

	/**
	 * @dataProvider dataRegistryDefaultSet
	 * @testdox it should return the default value when set, and coerce the value if necessary on a non-strict mode
	 *
	 * @param array<Option> $options
	 * @param mixed         $return  The expected returned value from `get_option`.
	 */
	public function testRegistryDefaultSet(array $options, $return): void
	{
		$registry = new Registry();
		$registry->addOptions(...$options);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($return, get_option($this->optionName));
	}

	/**
	 * Non-strict. Value may be coerced.
	 */
	public function dataRegistryDefaultSet(): iterable
	{
		yield [[(new Option($this->optionName, 'string'))->withDefault(123)], '123'];
		yield [[(new Option($this->optionName, 'boolean'))->withDefault('')], false];
		yield [[(new Option($this->optionName, 'boolean'))->withDefault(1)], true];
		yield [[(new Option($this->optionName, 'integer'))->withDefault('123')], 123];
		yield [[(new Option($this->optionName, 'array'))->withDefault('foo')], ['foo']];
		yield [[(new Option($this->optionName, 'array'))->withDefault(['foo' => 'bar'])], ['foo' => 'bar']];
		yield [[(new Option($this->optionName, 'number'))->withDefault('12.3')], 12.3];
		yield [[(new Option($this->optionName, 'number'))->withDefault('123')], 123];

		// The `null` value should be defaulted to `null`.
		yield [[(new Option($this->optionName, 'string'))->withDefault(null)], null];
		yield [[(new Option($this->optionName, 'number'))->withDefault(null)], null];
		yield [[(new Option($this->optionName, 'boolean'))->withDefault(null)], null];
		yield [[(new Option($this->optionName, 'integer'))->withDefault(null)], null];
		yield [[(new Option($this->optionName, 'array'))->withDefault(null)], null];
	}

	/**
	 * @dataProvider dataRegistryDefaultSetStrictValid
	 * @group strict-mode
	 * @testdox it should return the default value when set, on a strict mode
	 *
	 * @param array<Option> $option
	 * @param mixed         $return The expected returned value from `get_option`.
	 */
	public function testRegistryDefaultSetStrictValid(array $options, $return): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...$options);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($return, get_option($this->optionName));
	}

	public function dataRegistryDefaultSetStrictValid(): iterable
	{
		yield [[(new Option($this->optionName, 'string'))->withDefault('Hello World!')], 'Hello World!'];
		yield [[(new Option($this->optionName, 'boolean'))->withDefault(true)], true];
		yield [[(new Option($this->optionName, 'boolean'))->withDefault(false)], false];
		yield [[(new Option($this->optionName, 'integer'))->withDefault(1)], 1];
		yield [[(new Option($this->optionName, 'integer'))->withDefault(-1)], -1];
		yield [[(new Option($this->optionName, 'number'))->withDefault(1)], 1];
		yield [[(new Option($this->optionName, 'number'))->withDefault(1.1)], 1.1];
		yield [[(new Option($this->optionName, 'array'))->withDefault([1])], [1]];
		yield [[(new Option($this->optionName, 'array'))->withDefault(['foo' => 'bar'])], ['foo' => 'bar']];

		// The `null` value should be defaulted to `null`.
		yield [[(new Option($this->optionName, 'string'))->withDefault(null)], null];
		yield [[(new Option($this->optionName, 'number'))->withDefault(null)], null];
		yield [[(new Option($this->optionName, 'boolean'))->withDefault(null)], null];
		yield [[(new Option($this->optionName, 'integer'))->withDefault(null)], null];
		yield [[(new Option($this->optionName, 'array'))->withDefault(null)], null];
	}

	/**
	 * @dataProvider dataRegistryDefaultSetStrictInvalid
	 * @group strict-mode
	 * @testdox it should throw an exception when the default value is invalid, on a strict mode
	 *
	 * @param array<Option> $options
	 * @param string        $message The error message to expect.
	 */
	public function testRegistryDefaultSetStrictInvalid(array $options, string $message): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...$options);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($message);

		get_option($this->optionName);
	}

	public function dataRegistryDefaultSetStrictInvalid(): iterable
	{
		yield [[(new Option($this->optionName, 'string'))->withDefault(true)], 'Value must be of type string, boolean given.'];
		yield [[(new Option($this->optionName, 'boolean'))->withDefault('true')], 'Value must be of type boolean, string given.'];
		yield [[(new Option($this->optionName, 'boolean'))->withDefault(0)], 'Value must be of type boolean, integer given.'];
		yield [[(new Option($this->optionName, 'integer'))->withDefault(1.1)], 'Value must be of type integer, number (float) given.'];
		yield [[(new Option($this->optionName, 'integer'))->withDefault('-1')], 'Value must be of type integer, string given.'];
		yield [[(new Option($this->optionName, 'number'))->withDefault([1])], 'Value must be of type number, array given.'];
		yield [[(new Option($this->optionName, 'number'))->withDefault(false)], 'Value must be of type number, boolean given.'];
		yield [[(new Option($this->optionName, 'array'))->withDefault('foo')], 'Value must be of type array, string given.'];
	}

	/**
	 * @dataProvider dataRegistryDefaultPassed
	 *
	 * @param array<Option> $options
	 * @param mixed         $defaultPassed The default value passed in the function `get_option`.
	 * @param mixed         $coerced       The default value returned or coerced by the function `get_option`.
	 */
	public function testRegistryDefaultPassed(array $options, $defaultPassed, $coerced): void
	{
		$registry = new Registry();
		$registry->addOptions(...$options);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertSame($coerced, get_option($this->optionName, $defaultPassed));
	}

	/**
	 * Non-strict. Value may be coerced.
	 */
	public function dataRegistryDefaultPassed(): iterable
	{
		yield [[(new Option($this->optionName, 'string'))->withDefault('Hello World')], 123, '123'];
		yield [[(new Option($this->optionName, 'boolean'))->withDefault(false)], 'true', true];
		yield [[(new Option($this->optionName, 'boolean'))->withDefault(true)], '', false];
		yield [[(new Option($this->optionName, 'boolean'))->withDefault(true)], false, false];
		yield [[(new Option($this->optionName, 'integer'))->withDefault(1)], '2', 2];
		yield [[(new Option($this->optionName, 'number'))->withDefault(1.2)], '2.5', 2.5];
		yield [[(new Option($this->optionName, 'number'))->withDefault(1)], '2', 2];
		yield [[(new Option($this->optionName, 'array'))->withDefault(['foo'])], 'bar', ['bar']];
	}

	/**
	 * @dataProvider dataRegistryDefaultPassedStrictValid
	 * @group strict-mode
	 *
	 * @param array<Option> $option
	 * @param mixed         $defaultPassed The default value passed in the function `get_option`.
	 */
	public function testRegistryDefaultPassedStrictValid(array $option, $defaultPassed): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...$option);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertSame($defaultPassed, get_option($this->optionName, $defaultPassed));
	}

	public function dataRegistryDefaultPassedStrictValid(): iterable
	{
		yield [[(new Option($this->optionName, 'string'))->withDefault('Foo Bar')], null];
		yield [[(new Option($this->optionName, 'string'))->withDefault('Hello World')], '123'];
		yield [[(new Option($this->optionName, 'boolean'))->withDefault(true)], null];
		yield [[(new Option($this->optionName, 'boolean'))->withDefault(true)], false];
		yield [[(new Option($this->optionName, 'integer'))->withDefault(1)], null];
		yield [[(new Option($this->optionName, 'integer'))->withDefault(1)], 2];
		yield [[(new Option($this->optionName, 'number'))->withDefault(1)], null];
		yield [[(new Option($this->optionName, 'number'))->withDefault(1)], 2];
		yield [[(new Option($this->optionName, 'number'))->withDefault(1.2)], null];
		yield [[(new Option($this->optionName, 'number'))->withDefault(1.2)], 2.3];
		yield [[(new Option($this->optionName, 'array'))->withDefault(['foo'])], null];
		yield [[(new Option($this->optionName, 'array'))->withDefault(['foo'])], ['foo']];
		yield [[(new Option($this->optionName, 'array'))->withDefault(['foo' => 'bar'])], null];
		yield [[(new Option($this->optionName, 'array'))->withDefault(['foo' => 'bar'])], ['foo' => 'bar']];
	}

	/**
	 * @dataProvider dataRegistryDefaultPassedStrictInvalid
	 * @group strict-mode
	 * @testdox it should throw an exception when the default passed is invalid, on a strict mode
	 *
	 * @param array<Option> $options
	 * @param mixed         $defaultPassed The default value passed in the function `get_option`.
	 * @param string        $errorMessage  The expected error message thrown with the `TypeError`.
	 */
	public function testRegistryDefaultPassedStrictInvalid(array $options, $defaultPassed, string $errorMessage): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...$options);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		get_option($this->optionName, $defaultPassed);
	}

	public function dataRegistryDefaultPassedStrictInvalid(): iterable
	{
		yield [[(new Option($this->optionName, 'string'))->withDefault('Hello World')], 123, 'Value must be of type string, integer given.'];
		yield [[(new Option($this->optionName, 'boolean'))->withDefault(true)], '0', 'Value must be of type boolean, string given.'];
		yield [[(new Option($this->optionName, 'integer'))->withDefault(1)], '2', 'Value must be of type integer, string given.'];
		yield [[(new Option($this->optionName, 'integer'))->withDefault(1)], 1.2, 'Value must be of type integer, number (float) given.'];
		yield [[(new Option($this->optionName, 'number'))->withDefault(1)], [], 'Value must be of type number, array given.'];
		yield [[(new Option($this->optionName, 'array'))->withDefault([1])], 1, 'Value must be of type array, integer given.'];
		yield [[(new Option($this->optionName, 'array'))->withDefault(['foo' => 'bar'])], 'foo->bar', 'Value must be of type array, string given.'];
	}

	/**
	 * @dataProvider dataRegistryPrefixSet
	 *
	 * @param array<Option> $options
	 * @param mixed         $value   The value to add with `add_option` and one retrieved with `get_option`.
	 */
	public function testRegistryPrefixSet(array $options, $value): void
	{
		$registry = new Registry();
		$registry->addOptions(...$options);
		$registry->hook($this->hook);
		$registry->setPrefix('syntatis_');

		$optionName = 'syntatis_' . $this->optionName;

		$this->assertFalse(has_filter('default_option_syntatis_' . $this->optionName));
		$this->assertFalse(has_filter('option_syntatis_' . $this->optionName));

		$registry->register();
		$this->hook->register();

		$this->assertTrue(has_filter('default_option_syntatis_' . $this->optionName));
		$this->assertTrue(has_filter('option_syntatis_' . $this->optionName));

		$this->assertTrue(add_option($optionName, $value));
		$this->assertSame($value, get_option($optionName));
	}

	public function dataRegistryPrefixSet(): iterable
	{
		yield [[new Option($this->optionName, 'string')], 'Hello World!'];
		yield [[new Option($this->optionName, 'boolean')], true];
		yield [[new Option($this->optionName, 'integer')], 1];
		yield [[new Option($this->optionName, 'number')], 2];
		yield [[new Option($this->optionName, 'number')], 1.2];
		yield [[new Option($this->optionName, 'array')], ['foo']];
		yield [[new Option($this->optionName, 'array')], ['foo' => 'bar']];
	}

	/**
	 * @dataProvider dataRegistryTypeString
	 * @group type-string
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testRegistryGetTypeString($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize($value),
		);

		$registry = new Registry();
		$registry->addOptions(...[new Option($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertSame($expect, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeString
	 * @group type-string
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testRegistryAddTypeString($value, $expect): void
	{
		$registry = new Registry();
		$registry->addOptions(...[new Option($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(add_option($this->optionName, $value));
		$this->assertSame($expect, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeString
	 * @group type-string
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testRegistryUpdateTypeString($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value updated with the `update_option` function and
		 * aone retrieved with the `get_option` function.
		 */
		add_option($this->optionName, (new InputSanitizer())->sanitize('Initial value!'));

		$registry = new Registry();
		$registry->addOptions(...[new Option($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(update_option($this->optionName, $value));
		$this->assertSame($expect, get_option($this->optionName));
	}

	/**
	 * Non-strict. Value may be coerced.
	 */
	public function dataRegistryTypeString(): iterable
	{
		yield ['Hello World!', 'Hello World!'];
		yield [1, '1'];
		yield [1.2, '1.2'];
		yield [true, '1'];
		yield [false, ''];

		/**
		 * For consistency with how other type handles `null` values, and how it handles default
		 * when no value is passed on the `get_option` function, a `null` value would return
		 * as a `null`.
		 */
		yield [null, null];

		/**
		 * PHP can't convert an array to a string.
		 *
		 * When converting an array to a string, it will throw an exception
		 * and value returned will fallback to a `null`.
		 */
		yield [[], null];
		yield [['foo'], null];
		yield [['foo' => 'bar'], null];
	}

	/**
	 * @dataProvider dataRegistryTypeStringStrictValid
	 * @group type-string
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryGetTypeStringStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize($value),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertSame($value, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeStringStrictValid
	 * @group type-string
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryAddTypeStringStrictValid($value): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(add_option($this->optionName, $value));
		$this->assertSame($value, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeStringStrictValid
	 * @group type-string
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryUpdateTypeStringStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value updated with the `update_option` function and
		 * aone retrieved with the `get_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize('Initial value!'),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(update_option($this->optionName, $value));
		$this->assertSame($value, get_option($this->optionName));
	}

	public function dataRegistryTypeStringStrictValid(): iterable
	{
		yield ['Hello World!'];
		yield [''];
		yield [' '];
		yield [null];
	}

	/**
	 * @dataProvider dataRegistryTypeStringStrictInvalid
	 * @group type-string
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expectd error message thrown with the `TypeError`.
	 */
	public function testRegistryGetTypeStringStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize($value),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		get_option($this->optionName);
	}

	/**
	 * @dataProvider dataRegistryTypeStringStrictInvalid
	 * @group type-string
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expectd error message thrown with the `TypeError`.
	 */
	public function testRegistryAddTypeStringStrictInvalid($value, string $errorMessage): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		add_option($this->optionName, $value);
	}

	/**
	 * @dataProvider dataRegistryTypeStringStrictInvalid
	 * @group type-string
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expectd error message thrown with the `TypeError`.
	 */
	public function testRegistryUpdateTypeStringStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize('Initial value!'),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		update_option($this->optionName, $value);
	}

	public function dataRegistryTypeStringStrictInvalid(): iterable
	{
		yield [1, 'Value must be of type string, integer given.'];
		yield [1.2, 'Value must be of type string, number (float) given.'];
		yield [false, 'Value must be of type string, boolean given.'];
		yield [true, 'Value must be of type string, boolean given.'];
		yield [[1], 'Value must be of type string, array given.'];
		yield [['foo' => 'bar'], 'Value must be of type string, array given.'];
	}

	/**
	 * @dataProvider dataRegistryTypeBoolean
	 * @group type-boolean
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testRegistryGetTypeBoolean($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize($value),
		);

		$registry = new Registry();
		$registry->addOptions(...[new Option($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertSame($expect, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeBoolean
	 * @group type-boolean
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testRegistryAddTypeBoolean($value, $expect): void
	{
		$registry = new Registry();
		$registry->addOptions(...[new Option($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(add_option($this->optionName, $value));
		$this->assertSame($expect, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeBoolean
	 * @group type-boolean
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testRegistryUpdateTypeBoolean($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function,
		 * and updated with the `update_option` function.
		 */
		add_option($this->optionName, (new InputSanitizer())->sanitize(false));

		$registry = new Registry();
		$registry->addOptions(...[new Option($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(update_option($this->optionName, $value));
		$this->assertSame($expect, get_option($this->optionName));
	}

	/**
	 * Non-strict. Value may be coerced.
	 */
	public function dataRegistryTypeBoolean(): iterable
	{
		yield ['Hello world!', true];
		yield ['true', true];
		yield ['false', false];
		yield ['0', false];
		yield ['1', true];
		yield ['', false];
		yield [0, false];
		yield [1, true];
		yield [1.2, true];
		yield [true, true];
		yield [[], false];

		/**
		 * -1 is considered true, like any other non-zero (whether negative or positive) number!
		 *
		 * @see https://www.php.net/manual/en/language.types.boolean.php
		 */
		yield [-1, true];

		/**
		 * A `null` value would return as a `null`.
		 */
		yield [null, null];
	}

	/**
	 * @dataProvider dataRegistryTypeBooleanStrictValid
	 * @group type-boolean
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryGetTypeBooleanStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize($value),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertSame($value, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeBooleanStrictValid
	 * @group type-boolean
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryAddTypeBooleanStrictValid($value): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(add_option($this->optionName, $value));
		$this->assertSame($value, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeBooleanStrictValid
	 * @group type-boolean
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryUpdateTypeBooleanStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize(false),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertFalse(get_option($this->optionName));
		$this->assertTrue(update_option($this->optionName, $value));
		$this->assertSame($value, get_option($this->optionName));
	}

	public function dataRegistryTypeBooleanStrictValid(): iterable
	{
		yield [true];
		yield [null];
	}

	/**
	 * @dataProvider dataRegistryTypeBooleanStrictInvalid
	 * @group type-boolean
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expected error message thrown with the `TypeError`.
	 */
	public function testRegistryGetTypeBooleanStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize($value),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		get_option($this->optionName);
	}

	/**
	 * @dataProvider dataRegistryTypeBooleanStrictInvalid
	 * @group type-boolean
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expected error message thrown with the `TypeError`.
	 */
	public function testRegistryAddTypeBooleanStrictInvalid($value, string $errorMessage): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		add_option($this->optionName, $value);
	}

	/**
	 * @dataProvider dataRegistryTypeBooleanStrictInvalid
	 * @group type-boolean
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expected error message thrown with the `TypeError`.
	 */
	public function testRegistryUpdateTypeBooleanStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value updated with the `update_option` function, and
		 * retrieved with the `get_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize(false),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		update_option($this->optionName, $value);
	}

	public function dataRegistryTypeBooleanStrictInvalid(): iterable
	{
		yield ['Hello world!', 'Value must be of type boolean, string given.'];
		yield ['', 'Value must be of type boolean, string given.'];
		yield [' ', 'Value must be of type boolean, string given.'];
		yield [0, 'Value must be of type boolean, integer given.'];
		yield [1, 'Value must be of type boolean, integer given.'];
		yield [1.2, 'Value must be of type boolean, number (float) given.'];
		yield [-1, 'Value must be of type boolean, integer given.'];
		yield [[], 'Value must be of type boolean, array given.'];
		yield [['foo' => 'bar'], 'Value must be of type boolean, array given.'];
		yield ['false', 'Value must be of type boolean, string given.'];
		yield ['true', 'Value must be of type boolean, string given.'];
	}

	/**
	 * @dataProvider dataRegistryTypeInteger
	 * @group type-integer
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testRegistryGetTypeInteger($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value updated with the `update_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize($value),
		);

		$registry = new Registry();
		$registry->addOptions(...[new Option($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertSame($expect, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeInteger
	 * @group type-integer
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testRegistryAddTypeInteger($value, $expect): void
	{
		$registry = new Registry();
		$registry->addOptions(...[new Option($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(add_option($this->optionName, $value));
		$this->assertSame($expect, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeInteger
	 * @group type-integer
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testRegistryUpdateTypeInteger($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value updated with the `update_option` function, and
		 * retrieved with the `get_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize(0),
		);

		$registry = new Registry();
		$registry->addOptions(...[new Option($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(update_option($this->optionName, $value));
		$this->assertSame($expect, get_option($this->optionName));
	}

	/**
	 * Non-strict. Value may be coerced.
	 */
	public function dataRegistryTypeInteger(): iterable
	{
		yield ['Hello world!', 0];
		yield ['', 0];
		yield [1, 1];
		yield [1.2, 1];
		yield [1.23, 1];
		yield [-1, -1];
		yield [false, 0];
		yield [true, 1];

		/**
		 * The behaviour of converting to int is undefined for other types.
		 * Do not rely on any observed behaviour, as it can change without
		 * notice. Similar to how it handles the string type, an array
		 * would return as a `null`.
		 *
		 * @see https://www.php.net/manual/en/language.types.integer.php
		 */
		yield [[], null];
		yield [['foo'], null];
		yield [['foo' => 'bar'], null];

		/**
		 * PHP internally would cast a `null` to `0`, but for consistency
		 * with the other types, and how it handles default when no value
		 * is passed on the `get_option` function, a `null` value would
		 * return as a `null`.
		 */
		yield [null, null];
	}

	/**
	 * @dataProvider dataRegistryTypeIntegerStrictValid
	 * @group type-integer
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryGetTypeIntegerStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize($value),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertSame($value, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeIntegerStrictValid
	 * @group type-integer
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryAddTypeIntegerStrictValid($value): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(add_option($this->optionName, $value));
		$this->assertSame($value, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeIntegerStrictValid
	 * @group type-integer
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryUpdateTypeIntegerStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value updated with the `update_option` function, and
		 * retrieved with the `get_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize(0),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(update_option($this->optionName, $value));
		$this->assertSame($value, get_option($this->optionName));
	}

	public function dataRegistryTypeIntegerStrictValid(): iterable
	{
		yield [1]; // Positive
		yield [-1]; // Negative
		yield [0123]; // Octal
		yield [0x1A]; // Hexadecimal
		yield [0b11111111]; // Binary
		yield [1_234_567];
		yield [null];
	}

	/**
	 * @dataProvider dataRegistryTypeIntegerStrictInvalid
	 * @group type-integer
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expected error message thrown with the `TypeError`.
	 */
	public function testRegistryGetTypeIntegerStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize($value),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		get_option($this->optionName);
	}

	/**
	 * @dataProvider dataRegistryTypeIntegerStrictInvalid
	 * @group type-integer
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expected error message thrown with the `TypeError`.
	 */
	public function testRegistryAddTypeIntegerStrictInvalid($value, string $errorMessage): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		add_option($this->optionName, $value);
	}

	/**
	 * @dataProvider dataRegistryTypeIntegerStrictInvalid
	 * @group type-integer
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expected error message thrown with the `TypeError`.
	 */
	public function testRegistryUpdateTypeIntegerStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function, and
		 * updated with the `update_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize(0),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		update_option($this->optionName, $value);
	}

	public function dataRegistryTypeIntegerStrictInvalid(): iterable
	{
		yield ['Hello world!', 'Value must be of type integer, string given.'];
		yield ['', 'Value must be of type integer, string given.'];
		yield [1.2, 'Value must be of type integer, number (float) given.'];
		yield [false, 'Value must be of type integer, boolean given.'];
		yield [true, 'Value must be of type integer, boolean given.'];
		yield [['foo'], 'Value must be of type integer, array given.'];
		yield [['foo' => 'bar'], 'Value must be of type integer, array given.'];
	}

	/**
	 * @dataProvider dataRegistryTypeNumber
	 * @group type-number
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The value to be returned.
	 */
	public function testRegistryGetTypeNumber($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function.
		 */
		add_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry();
		$registry->addOptions(...[new Option($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertSame($expect, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeNumber
	 * @group type-number
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testRegistryAddTypeNumber($value, $expect): void
	{
		$registry = new Registry();
		$registry->addOptions(...[new Option($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(add_option($this->optionName, $value));
		$this->assertSame($expect, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeNumber
	 * @group type-number
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testRegistryUpdateTypeNumber($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value updated with the `update_option` function, and
		 * retrieved with the `get_option` function.
		 */
		add_option($this->optionName, (new InputSanitizer())->sanitize(0.0));

		$registry = new Registry();
		$registry->addOptions(...[new Option($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(update_option($this->optionName, $value));
		$this->assertSame($expect, get_option($this->optionName));
	}

	/**
	 * Non-strict. Value may be coerced.
	 */
	public function dataRegistryTypeNumber(): iterable
	{
		yield [0, 0];
		yield [1, 1];
		yield [1.2, 1.2];
		yield ['1', 1];
		yield ['1.2', 1.2];
		yield [-1, -1];
		yield [false, 0];
		yield [true, 1];

		/**
		 * As certain types have undefined behavior when converting to number.
		 */
		yield [[], null];
		yield [['foo'], null];
		yield [['foo' => 'bar'], null];
		yield ['Hello world!', null];
		yield ['', null];

		yield [null, null];
	}

	/**
	 * @dataProvider dataRegistryTypeNumberStrictValid
	 * @group type-number
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryGetTypeNumberStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function, and
		 * updated with the `update_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize($value),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertSame($value, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeNumberStrictValid
	 * @group type-number
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryAddTypeNumberStrictValid($value): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(add_option($this->optionName, $value));
		$this->assertSame($value, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeNumberStrictValid
	 * @group type-number
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryUpdateTypeNumberStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function, and
		 * updated with the `update_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize(1.0),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(update_option($this->optionName, $value));
		$this->assertSame($value, get_option($this->optionName));
	}

	public function dataRegistryTypeNumberStrictValid(): iterable
	{
		yield [1.2]; // Positive
		yield [-1.2]; // Negative
		yield [1.2e3]; // Scientific notation
		yield [7E-10]; // Scientific notation
		yield [1_234_567.89];

		// Integers
		yield [1];
		yield [-1];
		yield [0];

		yield [null];
	}

	/**
	 * @dataProvider dataRegistryTypeNumberStrictInvalid
	 * @group type-number
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expected error message thrown with the `TypeError`.
	 */
	public function testRegistryGetTypeNumberStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize($value),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->expectException(TypeError::class);

		get_option($this->optionName);
	}

	/**
	 * @dataProvider dataRegistryTypeNumberStrictInvalid
	 * @group type-number
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expected error message thrown with the `TypeError`.
	 */
	public function testRegistryAddTypeNumberStrictInvalid($value, string $errorMessage): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		add_option($this->optionName, $value);
	}

	/**
	 * @dataProvider dataRegistryTypeNumberStrictInvalid
	 * @group type-number
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expected error message thrown with the `TypeError`.
	 */
	public function testRegistryUpdateTypeNumberStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function, and
		 * updated with the `update_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize(0.0),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		update_option($this->optionName, $value);
	}

	public function dataRegistryTypeNumberStrictInvalid(): iterable
	{
		yield ['Hello world!', 'Value must be of type number, string given.'];
		yield ['', 'Value must be of type number, string given.'];
		yield [false, 'Value must be of type number, boolean given.'];
		yield [true, 'Value must be of type number, boolean given.'];
		yield [[], 'Value must be of type number, array given.'];
	}

	/**
	 * @dataProvider dataRegistryTypeArray
	 * @group type-array
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testRegistryGetTypeArray($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function.
		 */
		add_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry();
		$registry->addOptions(...[new Option($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($expect, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeArray
	 * @group type-array
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testRegistryAddTypeArray($value, $expect): void
	{
		$registry = new Registry();
		$registry->addOptions(...[new Option($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertTrue(add_option($this->optionName, $value));
		$this->assertSame($expect, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeArray
	 * @group type-array
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testRegistryUpdateTypeArray($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value updated with the `update_option` function, and
		 * retrieved with the `get_option` function.
		 */
		add_option($this->optionName, (new InputSanitizer())->sanitize([]));

		$registry = new Registry();
		$registry->addOptions(...[new Option($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertTrue(update_option($this->optionName, $value));
		$this->assertSame($expect, get_option($this->optionName));
	}

	/**
	 * Non-strict. Value may be coerced.
	 */
	public function dataRegistryTypeArray(): iterable
	{
		yield ['Hello world!', ['Hello world!']];
		yield ['', ['']];
		yield [0, [0]];
		yield [1, [1]];
		yield [1.2, [1.2]];
		yield [-1, [-1]];
		yield [false, [false]];
		yield [true, [true]];
		yield [['foo', 'bar'], ['foo', 'bar']];
		yield [['foo' => 'bar'], ['foo' => 'bar']];
		yield [null, null];
	}

	/**
	 * @dataProvider dataRegistryTypeArrayStrictValid
	 * @group type-array
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryGetTypeArrayStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize($value),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertSame($value, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeArrayStrictValid
	 * @group type-array
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryAddTypeArrayStrictValid($value): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(add_option($this->optionName, $value));
		$this->assertSame($value, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryTypeArrayStrictValid
	 * @group type-array
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryUpdateTypeArrayStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function, and
		 * updated with the `update_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize([]),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(update_option($this->optionName, $value));
		$this->assertSame($value, get_option($this->optionName));
	}

	public function dataRegistryTypeArrayStrictValid(): iterable
	{
		yield [['foo']];
		yield [['foo' => 'bar']];
		yield [null];
	}

	/**
	 * @dataProvider dataRegistryTypeArrayStrictInvalid
	 * @group type-array
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expected error message thrown with the `TypeError`.
	 */
	public function testRegistryGetTypeArrayStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize($value),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		get_option($this->optionName);
	}

	/**
	 * @dataProvider dataRegistryTypeArrayStrictInvalid
	 * @group type-array
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryAddTypeArrayStrictInvalid($value, string $errorMessage): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		add_option($this->optionName, $value);
	}

	/**
	 * @dataProvider dataRegistryTypeArrayStrictInvalid
	 * @group type-array
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expected error message thrown with the `TypeError`.
	 */
	public function testRegistryUpdateTypeArrayStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function, and
		 * updated with the `update_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize([]),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[new Option($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		update_option($this->optionName, $value);
	}

	public function dataRegistryTypeArrayStrictInvalid(): iterable
	{
		yield ['Hello world!', 'Value must be of type array, string given.'];
		yield ['', 'Value must be of type array, string given.'];
		yield [0, 'Value must be of type array, integer given.'];
		yield [1.2, 'Value must be of type array, number (float) given.'];
		yield [-1, 'Value must be of type array, integer given.'];
		yield [false, 'Value must be of type array, boolean given.'];
		yield [true, 'Value must be of type array, boolean given.'];
	}

	/**
	 * @dataProvider dataRegistryConstraints
	 * @group strict-mode
	 *
	 * @param mixed $constraints  The constraints to be passed in the schema.
	 * @param mixed $value        The value to add in the option.
	 * @param mixed $errorMessage The expected error message.
	 */
	public function testRegistryAddConstraints($constraints, $value, $errorMessage): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[(new Option($this->optionName, 'string'))->withConstraints(...$constraints)]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($errorMessage);

		add_option($this->optionName, $value);
	}

	/**
	 * @dataProvider dataRegistryConstraints
	 *
	 * @param mixed $constraints The constraints to be passed in the schema.
	 * @param mixed $value       The value to add in the option.
	 */
	public function testRegistryAddConstraintsNonStrict($constraints, $value): void
	{
		$registry = new Registry();
		$registry->addOptions(...[(new Option($this->optionName, 'string'))->withConstraints(...$constraints)]);
		$registry->hook($this->hook);
		$registry->register();
		$this->hook->register();

		$this->assertTrue(add_option($this->optionName, $value));
		$this->assertSame($value, get_option($this->optionName));
	}

	/**
	 * @dataProvider dataRegistryConstraints
	 * @group strict-mode
	 *
	 * @param mixed $constraints  The constraints to be passed in the schema.
	 * @param mixed $value        The value to add in the option.
	 * @param mixed $errorMessage The expected error message.
	 */
	public function testRegistryUpdateConstraints($constraints, $value, $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value updated with the `update_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize('email@example.org'),
		);

		$registry = new Registry(1);
		$registry->addOptions(...[(new Option($this->optionName, 'string'))->withConstraints(...$constraints)]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($errorMessage);

		update_option($this->optionName, $value);
	}

	/**
	 * @dataProvider dataRegistryConstraints
	 *
	 * @param mixed $constraints The constraints to be passed in the schema.
	 * @param mixed $value       The value to add in the option.
	 */
	public function testRegistryUpdateConstraintsNonStrict($constraints, $value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value updated with the `update_option` function.
		 */
		add_option(
			$this->optionName,
			(new InputSanitizer())->sanitize('email@example.org'),
		);

		$registry = new Registry();
		$registry->addOptions(...[(new Option($this->optionName, 'string'))->withConstraints($constraints)]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame('email@example.org', get_option($this->optionName));
		$this->assertTrue(update_option($this->optionName, $value));
		$this->assertSame($value, get_option($this->optionName));
	}

	public function dataRegistryConstraints(): iterable
	{
		// With arrays.
		yield [['\Syntatis\Utils\is_email'], 'Maybe Email', 'Value does not match the given constraints.'];
		yield [[new Assert\Email(null, 'The email {{ value }} is not a valid email.')], 'Hello Email', 'The email "Hello Email" is not a valid email.'];
	}

	/** @testdox it should not register the option as setting if it's not registered with a group. */
	public function testRegistryNotRegisteredSettings(): void
	{
		$registry = new Registry();
		$registry->addOptions(...[new Option($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertArrayNotHasKey($this->optionName, get_registered_settings());
	}

	/** @testdox it should register the option as setting with the args if it's registered with a group. */
	public function testRegistryRegisteredSettings(): void
	{
		$registry = new Registry();
		$registry->addOptions(...[
			(new Option($this->optionName, 'string'))
				->withDefault('Hello world!')
				->withDescription('This is the description.'),
		]);
		$registry->hook($this->hook);
		$registry->register($this->optionGroup);

		$this->hook->register();

		$registeredSettings = get_registered_settings();

		$this->assertArrayHasKey($this->optionName, $registeredSettings);
		$this->assertSame('string', $registeredSettings[$this->optionName]['type']);
		$this->assertSame($this->optionGroup, $registeredSettings[$this->optionName]['group']);
		$this->assertSame('Hello world!', $registeredSettings[$this->optionName]['default']);
	}

	/**
	 * @dataProvider dataRegistryAPIEnabled
	 * @group wp-api
	 */
	public function testRegistryAPIEnabled(Registry $registry, array $schema): void
	{
		$registry->hook($this->hook);
		$registry->register($this->optionGroup);
		$this->hook->register();

		do_action('rest_api_init');

		$request = new WP_REST_Request('OPTIONS', '/wp/v2/settings');
		$response = rest_do_request($request);
		$data = $response->get_data();
		$properties = $data['schema']['properties'];

		/**
		 * In WordPress 6.6, WordPress adds new setting argument, `label`,
		 * which will output an additional `title` in the schema. If
		 * the label is not explicitily set it will be an empty
		 * string.
		 *
		 * @see https://developer.wordpress.org/reference/functions/register_setting/
		 */
		$this->assertSame($schema['default'], $properties[$this->optionName]['default']);
		$this->assertSame($schema['description'], $properties[$this->optionName]['description']);
		$this->assertSame($schema['type'], $properties[$this->optionName]['type']);
	}

	public function dataRegistryAPIEnabled(): iterable
	{
		$registry = new Registry();
		$registry->addOptions(...[
			(new Option($this->optionName, 'string'))
				->withDefault('Hello world!')
				->withDescription('This is the description.')
				->apiEnabled(),
		]);

		yield [
			$registry,
			[
				'type' => 'string',
				'description' => 'This is the description.',
				'default' => 'Hello world!',
			],
		];

		$registry = new Registry();
		$registry->addOptions(...[
			(new Option($this->optionName, 'array'))
				->withDefault(['#fff'])
				->withDescription('This is the description.')
				->apiEnabled([
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'  => 'array',
							'items' => [
								'type'   => 'string',
								'format' => 'hex-color',
							],
						],
					],
				]),
		]);

		yield [
			$registry,
			[
				'type'  => 'array',
				'description' => 'This is the description.',
				'default' => ['#fff'],
				'items' => [
					'type'  => 'array',
					'items' => [
						'type'   => 'string',
						'format' => 'hex-color',
					],
				],
			],
		];
	}

	/** @group wp-api */
	public function testRegistryUpdateAPI(): void
	{
		wp_set_current_user(self::$administrator);

		$registry = new Registry();
		$registry->addOptions(...[
			(new Option($this->optionName, 'string'))
				->withDefault('Hello world!')
				->withDescription('This is the description.')
				->apiEnabled(),
		]);
		$registry->hook($this->hook);
		$registry->setPrefix('wp_starter_plugin_');
		$registry->register($this->optionGroup);

		$this->hook->register();

		do_action('rest_api_init');

		$optionName = 'wp_starter_plugin_' . $this->optionName;

		$request = new WP_REST_Request('PUT', '/wp/v2/settings');
		$request->set_body(wp_json_encode([$optionName => 'Hello Earth!']));
		$request->add_header('Content-Type', 'application/json');
		$response = rest_do_request($request);
		$data = $response->get_data();

		$this->assertSame(200, $response->get_status());
		$this->assertArrayHasKey($optionName, $data);
		$this->assertSame('Hello Earth!', $data[$optionName]);
	}

	/**
	 * @group wp-api
	 * @group strict-mode
	 */
	public function testRegistryUpdateAPIStrict(): void
	{
		wp_set_current_user(self::$administrator);

		$registry = new Registry(1);
		$registry->addOptions(...[
			(new Option($this->optionName, 'integer'))
				->withDefault(1)
				->apiEnabled(),
		]);
		$registry->hook($this->hook);
		$registry->setPrefix('wp_starter_plugin_');
		$registry->register($this->optionGroup);

		$this->hook->register();

		do_action('rest_api_init');

		$optionName = 'wp_starter_plugin_' . $this->optionName;

		$request = new WP_REST_Request('PUT', '/wp/v2/settings');
		$request->set_body(wp_json_encode([$optionName => 'Hello Earth!']));
		$request->add_header('Content-Type', 'application/json');

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage('Value must be of type integer, string given.');

		rest_do_request($request);
	}
}
