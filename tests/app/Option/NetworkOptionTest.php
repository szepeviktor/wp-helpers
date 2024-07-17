<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Tests\Option;

use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use Syntatis\WPHelpers\Option\Exceptions\TypeError;
use Syntatis\WPHelpers\Option\NetworkOption;
use Syntatis\WPHelpers\Option\Registry;
use Syntatis\WPHelpers\Option\Support\InputSanitizer;
use Syntatis\WPHelpers\Tests\WPTestCase;
use Syntatis\WPHook\Hook;

/** @group network-option */
class NetworkOptionTest extends WPTestCase
{
	private Hook $hook;

	private string $optionName = 'foo_bar';

	// phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
	public function set_up(): void
	{
		parent::set_up();

		$this->hook = new Hook();
	}

	// phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
	public function tear_down(): void
	{
		delete_site_option($this->optionName);

		parent::tear_down();
	}

	/** @testdox should return the name */
	public function testName(): void
	{
		$option = new NetworkOption($this->optionName, 'string');

		$this->assertEquals($this->optionName, $option->getName());
	}

	/** @testdox should throw error when name is blank */
	public function testBlankName(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$option = new NetworkOption('', 'string');
	}

	/** @testdox should set and return the constraints */
	public function testConstraints(): void
	{
		$option = new NetworkOption($this->optionName, 'string');
		$option = $option->withConstraints('is_string');

		$this->assertEquals(['is_string'], $option->getConstraints());

		$option = new NetworkOption($this->optionName, 'string');
		$option = $option->withConstraints('is_string', 'is_numeric');

		$this->assertEquals(
			['is_string', 'is_numeric'],
			$option->getConstraints(),
		);
	}

	/** @testdox should set and return the priority */
	public function testPriority(): void
	{
		$option = new NetworkOption($this->optionName, 'string');

		$this->assertSame(99, $option->getPriority());

		$option = $option->withPriority(100);

		$this->assertSame(100, $option->getPriority());
	}

	/** @testdox should set and return the default value set */
	public function testSettingArgsDefault(): void
	{
		$option = (new NetworkOption($this->optionName, 'string'))
			->withDefault('bar');

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
		$option = (new NetworkOption($this->optionName, 'string'))->withDescription('This is the description');

		$this->assertEquals(
			[
				'type' => 'string',
				'description' => 'This is the description',
				'default' => null,
			],
			$option->getSettingArgs(),
		);
	}

	/** @dataProvider dataNoDefaultSet */
	public function testNoDefaultSet(array $options): void
	{
		$registry = new Registry();
		$registry->addOptions(...$options);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertNull(get_site_option($this->optionName));
	}

	public function dataNoDefaultSet(): iterable
	{
		yield [[new NetworkOption($this->optionName, 'string')]];
		yield [[new NetworkOption($this->optionName, 'boolean')]];
		yield [[new NetworkOption($this->optionName, 'integer')]];
		yield [[new NetworkOption($this->optionName, 'number')]];
		yield [[new NetworkOption($this->optionName, 'array')]];
	}

	/**
	 * @dataProvider dataDefaultSet
	 *
	 * @param mixed $defaultReturned The default value to return
	 */
	public function testDefaultSet(array $options, $defaultReturned): void
	{
		$registry = new Registry();
		$registry->addOptions(...$options);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($defaultReturned, get_site_option($this->optionName));
	}

	/**
	 * Non-strict. Value may be coerced.
	 */
	public function dataDefaultSet(): iterable
	{
		yield [[(new NetworkOption($this->optionName, 'string'))->withDefault(123)], '123'];
		yield [[(new NetworkOption($this->optionName, 'boolean'))->withDefault(1)], true];
		yield [[(new NetworkOption($this->optionName, 'boolean'))->withDefault('')], false];
		yield [[(new NetworkOption($this->optionName, 'integer'))->withDefault('123')], 123];
		yield [[(new NetworkOption($this->optionName, 'number'))->withDefault('123')], 123];
		yield [[(new NetworkOption($this->optionName, 'number'))->withDefault('1.23')], 1.23];
		yield [[(new NetworkOption($this->optionName, 'array'))->withDefault('foo')], ['foo']];
	}

	/**
	 * @dataProvider dataDefaultSetStrictValid
	 *
	 * @param mixed $default The default value to return
	 */
	public function testDefaultSetStrictValid(array $options, $default): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...$options);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($default, get_site_option($this->optionName));
	}

	public function dataDefaultSetStrictValid(): iterable
	{
		yield [[(new NetworkOption($this->optionName, 'string'))->withDefault('Hello world!')], 'Hello world!'];
		yield [[(new NetworkOption($this->optionName, 'boolean'))->withDefault(true)], true];
		yield [[(new NetworkOption($this->optionName, 'boolean'))->withDefault(false)], false];
		yield [[(new NetworkOption($this->optionName, 'integer'))->withDefault(123)], 123];
		yield [[(new NetworkOption($this->optionName, 'number'))->withDefault(1.23)], 1.23];
		yield [[(new NetworkOption($this->optionName, 'number'))->withDefault(123)], 123];
		yield [[(new NetworkOption($this->optionName, 'array'))->withDefault(['foo', 'bar'])], ['foo', 'bar']];
		yield [[(new NetworkOption($this->optionName, 'array'))->withDefault(['foo' => 1, 'bar' => 2])], ['foo' => 1, 'bar' => 2]];
	}

	/**
	 * @dataProvider dataDefaultSetStrictInvalid
	 *
	 * @param mixed $errorMessage The default value to return
	 */
	public function testDefaultSetStrictInvalid(array $options, string $errorMessage): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...$options);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		get_site_option($this->optionName);
	}

	public function dataDefaultSetStrictInvalid(): iterable
	{
		yield [[(new NetworkOption($this->optionName, 'string'))->withDefault(123)], 'Value must be of type string, integer given.'];
		yield [[(new NetworkOption($this->optionName, 'boolean'))->withDefault('true')], 'Value must be of type boolean, string given.'];
		yield [[(new NetworkOption($this->optionName, 'integer'))->withDefault(1.23)], 'Value must be of type integer, number (float) given.'];
		yield [[(new NetworkOption($this->optionName, 'number'))->withDefault([])], 'Value must be of type number, array given.'];
		yield [[(new NetworkOption($this->optionName, 'array'))->withDefault(true)], 'Value must be of type array, boolean given.'];
	}

	/**
	 * @dataProvider dataDefaultPassed
	 *
	 * @param mixed $default               The default value passed in the schema.
	 * @param mixed $defaultPassed         The default value passed in the function `get_site_option`.
	 * @param mixed $defaultPassedReturned The default value returned or coerced by the function `get_site_option`.
	 */
	public function testDefaultPassed(array $options, $default, $defaultPassed, $defaultPassedReturned): void
	{
		$registry = new Registry();
		$registry->addOptions(...$options);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($default, get_site_option($this->optionName));
		$this->assertSame($defaultPassedReturned, get_site_option($this->optionName, $defaultPassed));
	}

	/**
	 * Non-strict. Value may be coerced.
	 */
	public function dataDefaultPassed(): iterable
	{
		yield [[(new NetworkOption($this->optionName, 'string'))->withDefault('Hello World')], 'Hello World', 123, '123'];
		yield [[(new NetworkOption($this->optionName, 'boolean'))->withDefault(false)], false, 'true', true];
		yield [[(new NetworkOption($this->optionName, 'boolean'))->withDefault(true)], true, '', false];
		yield [[(new NetworkOption($this->optionName, 'integer'))->withDefault(1)], 1, '2', 2];
		yield [[(new NetworkOption($this->optionName, 'number'))->withDefault(1.2)], 1.2, [], null];
		yield [[(new NetworkOption($this->optionName, 'number'))->withDefault(1.2)], 1.2, '2.3', 2.3];
		yield [[(new NetworkOption($this->optionName, 'array'))->withDefault(['foo'])], ['foo'], 'bar', ['bar']];
	}

	/**
	 * @dataProvider dataDefaultPassedStrictValid
	 *
	 * @param mixed $default       The default value passed in the schema.
	 * @param mixed $defaultPassed The default value passed in the function `get_site_option`.
	 */
	public function testDefaultPassedStrictValid(array $options, $default, $defaultPassed): void
	{
		$registry = new Registry();
		$registry->addOptions(...$options);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($default, get_site_option($this->optionName));
		$this->assertSame($defaultPassed, get_site_option($this->optionName, $defaultPassed));
	}

	public function dataDefaultPassedStrictValid(): iterable
	{
		yield [[(new NetworkOption($this->optionName, 'string'))->withDefault('Hello World')], 'Hello World', '123'];
		yield [[(new NetworkOption($this->optionName, 'string'))->withDefault('')], '', null];
		yield [[(new NetworkOption($this->optionName, 'boolean'))->withDefault(false)], false, true];
		yield [[(new NetworkOption($this->optionName, 'integer'))->withDefault(1)], 1, 2];
		yield [[(new NetworkOption($this->optionName, 'number'))->withDefault(1.2)], 1.2, 2.5];
		yield [[(new NetworkOption($this->optionName, 'array'))->withDefault(['foo'])], ['foo'], ['bar']];

		/**
		 * Passing `false` as the default value currently would not work as expected.
		 *
		 * It is probably because the `false` value is the default default set in `get_site_option` function,
		 * and WordPress considers the option is not available and would return the default value that's
		 * already set through the filter hook or the `setDefault` method. Unlike the `get_site_option`,
		 * there's currently no way to identify if the default is passed from `get_site_option`
		 * function.
		 *
		 * @see https://github.com/WordPress/wordpress-develop/blob/7444885eb3a0df1b3c30bc59891819c2cf885009/src/wp-includes/option.php#L1821-L1841
		 */
		// yield [[(new NetworkOption($this->optionName, 'boolean'))->withDefault(true)], true, false];
	}

	/**
	 * @dataProvider dataDefaultPassedStrictInvalid
	 *
	 * @param mixed $default               The default value passed in the schema.
	 * @param mixed $defaultPassed         The default value passed in the function `get_site_option`.
	 * @param mixed $defaultPassedReturned The default value returned or coerced by the function `get_site_option`.
	 */
	public function testDefaultPassedStrictInvalid(array $options, $default, $defaultPassed, string $errorMessage): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...$options);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($default, get_site_option($this->optionName));
		$this->expectException(TypeError::class);

		get_site_option($this->optionName, $defaultPassed);
	}

	public function dataDefaultPassedStrictInvalid(): iterable
	{
		yield [[(new NetworkOption($this->optionName, 'string'))->withDefault('Hello World')], 'Hello World', 123, 'Value must be of type string, integer given.'];
		yield [[(new NetworkOption($this->optionName, 'boolean'))->withDefault(true)], true, 0, 'Value must be of type boolean, integer given.'];
		yield [[(new NetworkOption($this->optionName, 'integer'))->withDefault(1)], 1, 2.3, 'Value must be of type integer, number (float) given.'];
		yield [[(new NetworkOption($this->optionName, 'number'))->withDefault(1.2)], 1.2, 'foo', 'Value must be of type number, string given.'];
		yield [[(new NetworkOption($this->optionName, 'array'))->withDefault(['foo'])], ['foo'], true, 'Value must be of type array, string given.'];
	}

	/**
	 * @dataProvider dataPrefixSet
	 *
	 * @param string $type  The default value passed in the schema.
	 * @param mixed  $value The value to add with `add_site_option`.
	 */
	public function testPrefixSet(array $options, $value): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...$options);
		$registry->hook($this->hook);
		$registry->setPrefix('syntatis_');

		$this->assertFalse(has_filter('default_site_option_syntatis_' . $this->optionName));
		$this->assertFalse(has_filter('site_option_syntatis_' . $this->optionName));

		$registry->register();
		$this->hook->register();

		$this->assertTrue(has_filter('default_site_option_syntatis_' . $this->optionName));
		$this->assertTrue(has_filter('site_option_syntatis_' . $this->optionName));

		$this->assertTrue(add_site_option('syntatis_' . $this->optionName, $value));
		$this->assertSame($value, get_site_option('syntatis_' . $this->optionName));
	}

	public function dataPrefixSet(): iterable
	{
		yield [[(new NetworkOption($this->optionName, 'string'))->withDefault('Hello World')], 'Hello Earth!'];
		yield [[(new NetworkOption($this->optionName, 'array'))->withDefault([])], ['foo']];
	}

	/**
	 * @dataProvider dataTypeString
	 * @group type-string
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testGetTypeString($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry();
		$registry->addOptions(...[new NetworkOption($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($expect, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeString
	 * @group type-string
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testAddTypeString($value, $expect): void
	{
		$registry = new Registry();
		$registry->addOptions(...[new NetworkOption($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertTrue(add_site_option($this->optionName, $value));
		$this->assertSame($expect, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeString
	 * @group type-string
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testUpdateTypeString($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function,
		 * and the `update_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize('Initial value!'));

		$registry = new Registry();
		$registry->addOptions(...[new NetworkOption($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertTrue(update_site_option($this->optionName, $value));
		$this->assertSame($expect, get_site_option($this->optionName));
	}

	/**
	 * Non-strict. Value may be coerced.
	 */
	public function dataTypeString(): iterable
	{
		yield ['Hello World!', 'Hello World!'];
		yield [1, '1'];
		yield [1.2, '1.2'];
		yield [true, '1'];
		yield [false, ''];

		/**
		 * For consistency with how other type handles `null` values, and how it handles default
		 * when no value is passed on the `get_site_option` function, a `null` value would return
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
	 * @dataProvider dataTypeStringStrictValid
	 * @group type-string
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testGetTypeStringStrictValid($value): void
	{
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($value, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeStringStrictValid
	 * @group type-string
	 * @group strict-mode
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testAddTypeStringStrictValid($value, $expect): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertTrue(add_site_option($this->optionName, $value));
		$this->assertSame($expect, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeStringStrictValid
	 * @group type-string
	 * @group strict-mode
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testUpdateTypeStringStrictValid($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function,
		 * and `update_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize('Initial value!'));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertTrue(update_site_option($this->optionName, $value));
		$this->assertSame($value, get_site_option($this->optionName));
	}

	public function dataTypeStringStrictValid(): iterable
	{
		yield ['Hello World!', 'Hello World!'];
		yield ['', ''];
		yield [' ', ' '];
		yield [null, null];
	}

	/**
	 * @dataProvider dataTypeStringStrictInvalid
	 * @group type-string
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testGetTypeStringStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		get_site_option($this->optionName);
	}

	/**
	 * @dataProvider dataTypeStringStrictInvalid
	 * @group type-string
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testAddTypeStringStrictInvalid($value, string $errorMessage): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		add_site_option($this->optionName, $value);
	}

	/**
	 * @dataProvider dataTypeStringStrictInvalid
	 * @group type-string
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testUpdateTypeStringStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function,
		 * and the `update_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize('Initial value!'));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'string')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		update_site_option($this->optionName, $value);
	}

	public function dataTypeStringStrictInvalid(): iterable
	{
		yield [1, 'Value must be of type string, integer given.'];
		yield [1.2, 'Value must be of type string, number (float) given.'];
		yield [true, 'Value must be of type string, boolean given.'];
		yield [[], 'Value must be of type string, array given.'];
		yield [false, 'Value must be of type string, boolean given.'];
	}

	/**
	 * @dataProvider dataTypeBoolean
	 * @group type-boolean
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testGetTypeBoolean($value, $expect): void
	{
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry();
		$registry->addOptions(...[new NetworkOption($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($expect, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeBoolean
	 * @group type-boolean
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testAddTypeBoolean($value, $expect): void
	{
		$registry = new Registry();
		$registry->addOptions(...[new NetworkOption($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertTrue(add_site_option($this->optionName, $value));
		$this->assertSame($expect, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeBoolean
	 * @group type-boolean
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testUpdateTypeBoolean($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function,
		 * and the `update_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize(false));

		$registry = new Registry();
		$registry->addOptions(...[new NetworkOption($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertTrue(update_site_option($this->optionName, $value));
		$this->assertSame($expect, get_site_option($this->optionName));
	}

	/**
	 * Non-strict. Value may be coerced.
	 */
	public function dataTypeBoolean(): iterable
	{
		yield ['Hello world!', true];
		yield ['', false];
		yield [0, false];
		yield [1, true];
		yield [1.2, true];
		yield [false, false];
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
	 * @dataProvider dataTypeBooleanStrictValid
	 * @group strict-mode
	 * @group type-boolean
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testGetTypeBooleanStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($value, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeBooleanStrictValid
	 * @group strict-mode
	 * @group type-boolean
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testAddTypeBooleanStrictValid($value): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertTrue(add_site_option($this->optionName, $value));
		$this->assertSame($value, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeBooleanStrictValid
	 * @group strict-mode
	 * @group type-boolean
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testUpdateTypeBooleanStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function,
		 * and `update_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize(false));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		update_site_option($this->optionName, $value);

		$this->assertSame($value, get_site_option($this->optionName));
	}

	public function dataTypeBooleanStrictValid(): iterable
	{
		yield [true];
		yield [false];
		yield [null];
	}

	/**
	 * @dataProvider dataTypeBooleanStrictInvalid
	 * @group type-boolean
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testGetTypeBooleanStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function,
		 * and `update_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		get_site_option($this->optionName);
	}

	/**
	 * @dataProvider dataTypeBooleanStrictInvalid
	 * @group type-boolean
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testAddTypeBooleanStrictInvalid($value, string $errorMessage): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		add_site_option($this->optionName, $value);
	}

	/**
	 * @dataProvider dataTypeBooleanStrictInvalid
	 * @group type-boolean
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testUpdateTypeBooleanStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function,
		 * and `update_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize(false));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'boolean')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		update_site_option($this->optionName, $value);
	}

	public function dataTypeBooleanStrictInvalid(): iterable
	{
		yield ['Hello world!', 'Value must be of type boolean, string given.'];
		yield ['', 'Value must be of type boolean, string given.'];
		yield [' ', 'Value must be of type boolean, string given.'];
		yield [0, 'Value must be of type boolean, integer given.'];
		yield [1, 'Value must be of type boolean, integer given.'];
		yield [1.2, 'Value must be of type boolean, number (float) given.'];
		yield [-1, 'Value must be of type boolean, integer given.'];
		yield [[], 'Value must be of type boolean, array given.'];
		yield [['foo'], 'Value must be of type boolean, array given.'];
		yield ['false', 'Value must be of type boolean, string given.'];
		yield ['true', 'Value must be of type boolean, string given.'];
	}

	/**
	 * @dataProvider dataTypeInteger
	 * @group type-integer
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testGetTypeInteger($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry();
		$registry->addOptions(...[new NetworkOption($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($expect, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeInteger
	 * @group type-integer
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testAddTypeInteger($value, $expect): void
	{
		$registry = new Registry();
		$registry->addOptions(...[new NetworkOption($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertTrue(add_site_option($this->optionName, $value));
		$this->assertSame($expect, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeInteger
	 * @group type-integer
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testUpdateTypeInteger($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function,
		 * and `update_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize(0));

		$registry = new Registry();
		$registry->addOptions(...[new NetworkOption($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		update_site_option($this->optionName, $value);

		$this->assertSame($expect, get_site_option($this->optionName));
	}

	/**
	 * Non-strict. Value may be coerced.
	 */
	public function dataTypeInteger(): iterable
	{
		yield ['Hello world!', 0];
		yield ['', 0];
		yield [0, 0];
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
		 * is passed on the `get_site_option` function, a `null` value
		 * would return as a `null`.
		 */
		yield [null, null];
	}

	/**
	 * @dataProvider dataTypeIntegerStrictValid
	 * @group type-integer
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testGetTypeIntegerStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($value, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeIntegerStrictValid
	 * @group type-integer
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testAddTypeIntegerStrictValid($value): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertTrue(add_site_option($this->optionName, $value));
		$this->assertSame($value, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeIntegerStrictValid
	 * @group type-integer
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testUpdateTypeIntegerStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function,
		 * and the `update_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize(1));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		update_site_option($this->optionName, $value);

		$this->assertSame($value, get_site_option($this->optionName));
	}

	public function dataTypeIntegerStrictValid(): iterable
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
	 * @dataProvider dataTypeIntegerStrictInvalid
	 * @group type-integer
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testGetTypeIntegerStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		get_site_option($this->optionName);
	}

	/**
	 * @dataProvider dataTypeIntegerStrictInvalid
	 * @group type-integer
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testAddTypeIntegerStrictInvalid($value, string $errorMessage): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		add_site_option($this->optionName, $value);
	}

	/**
	 * @dataProvider dataTypeIntegerStrictInvalid
	 * @group type-integer
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testUpdateTypeIntegerStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function,
		 * and the `update_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize(1));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'integer')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		update_site_option($this->optionName, $value);
	}

	public function dataTypeIntegerStrictInvalid(): iterable
	{
		yield ['Hello world!', 'Value must be of type integer, string given.'];
		yield [true, 'Value must be of type integer, boolean given.'];
		yield [false, 'Value must be of type integer, boolean given.'];
		yield [1.0, 'Value must be of type integer, number (float) given.'];
		yield [[], 'Value must be of type integer, array given.'];
	}

	/**
	 * @dataProvider dataTypeNumber
	 * @group type-number
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The value to be returned.
	 */
	public function testGetTypeNumber($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry();
		$registry->addOptions(...[new NetworkOption($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($expect, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeNumber
	 * @group type-number
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testAddTypeNumber($value, $expect): void
	{
		$registry = new Registry();
		$registry->addOptions(...[new NetworkOption($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertTrue(add_site_option($this->optionName, $value));
		$this->assertSame($expect, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeNumber
	 * @group type-number
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testUpdateTypeNumber($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function,
		 * and `update_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize(0));

		$registry = new Registry();
		$registry->addOptions(...[new NetworkOption($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		update_site_option($this->optionName, $value);

		$this->assertSame($expect, get_site_option($this->optionName));
	}

	/**
	 * Non-strict. Value may be coerced.
	 */
	public function dataTypeNumber(): iterable
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
	 * @dataProvider dataTypeNumberStrictValid
	 * @group type-number
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testGetTypeNumberStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function,
		 * and `update_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($value, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeNumberStrictValid
	 * @group type-number
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryGetTypeNumberStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($value, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeNumberStrictValid
	 * @group type-number
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testAddTypeNumberStrictValid($value): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertTrue(add_site_option($this->optionName, $value));
		$this->assertSame($value, get_site_option($this->optionName));
	}

	public function dataTypeNumberStrictValid(): iterable
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
	 * @dataProvider dataTypeNumberStrictInvalid
	 * @group type-number
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expected error message thrown with the `TypeError`.
	 */
	public function testGetTypeNumberStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		get_site_option($this->optionName);
	}

	/**
	 * @dataProvider dataTypeNumberStrictInvalid
	 * @group type-number
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expected error message thrown with the `TypeError`.
	 */
	public function testAddTypeNumberStrictInvalid($value, string $errorMessage): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		add_site_option($this->optionName, $value);
	}

	/**
	 * @dataProvider dataTypeNumberStrictInvalid
	 * @group type-number
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expected error message thrown with the `TypeError`.
	 */
	public function testUpdateTypeNumberStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function, and
		 * updated with the `update_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'number')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		update_site_option($this->optionName, $value);
	}

	public function dataTypeNumberStrictInvalid(): iterable
	{
		yield ['Hello world!', 'Value must be of type number, string given.'];
		yield ['', 'Value must be of type number, string given.'];
		yield [false, 'Value must be of type number, boolean given.'];
		yield [true, 'Value must be of type number, boolean given.'];
		yield [[], 'Value must be of type number, array given.'];
	}

	/**
	 * @dataProvider dataTypeArray
	 * @group type-array
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testGetTypeArray($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry();
		$registry->addOptions(...[new NetworkOption($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($expect, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeArray
	 * @group type-array
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testAddTypeArray($value, $expect): void
	{
		$registry = new Registry();
		$registry->addOptions(...[new NetworkOption($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertTrue(add_site_option($this->optionName, $value));
		$this->assertSame($expect, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeArray
	 * @group type-array
	 *
	 * @param mixed $value  The value to add in the option.
	 * @param mixed $expect The expected value to be returned.
	 */
	public function testUpdateypeArray($value, $expect): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function, and
		 * updated with the `update_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize(['bar']));

		$registry = new Registry();
		$registry->addOptions(...[new NetworkOption($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		update_site_option($this->optionName, $value);

		$this->assertSame($expect, get_site_option($this->optionName));
	}

	/**
	 * Non-strict. Value may be coerced.
	 */
	public function dataTypeArray(): iterable
	{
		yield ['Hello world!', ['Hello world!']];
		yield ['', ['']];
		yield [0, [0]];
		yield [1, [1]];
		yield [1.2, [1.2]];
		yield [-1, [-1]];
		yield [false, [false]];
		yield [true, [true]];
		yield [[], []];
		yield [['foo', 'bar'], ['foo', 'bar']];
		yield [['foo' => 'bar'], ['foo' => 'bar']];

		yield [null, null];
	}

	/**
	 * @dataProvider dataTypeArrayStrictValid
	 * @group type-array
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testGetTypeArrayStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame($value, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeArrayStrictValid
	 * @group type-array
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testAddTypeArrayStrictValue($value): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertTrue(add_site_option($this->optionName, $value));
		$this->assertSame($value, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataTypeArrayStrictValid
	 * @group type-array
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testUpdateTypeArrayStrictValid($value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_site_option` function,
		 * and updated with the `update_site_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		update_site_option($this->optionName, $value);

		$this->assertSame($value, get_site_option($this->optionName));
	}

	public function dataTypeArrayStrictValid(): iterable
	{
		yield [[], []];
		yield [['foo'], ['foo']];
		yield [['foo' => 'bar'], ['foo' => 'bar']];
		yield [null, null];
	}

	/**
	 * @dataProvider dataTypeArrayStrictInvalid
	 * @group type-array
	 * @group strict-mode
	 *
	 * @param mixed  $value        The value to add in the option.
	 * @param string $errorMessage The expected error message thrown with the `TypeError`.
	 */
	public function testGetTypeArrayStrictInvalid($value, string $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value retrieved with the `get_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		get_site_option($this->optionName);
	}

	/**
	 * @dataProvider dataTypeArrayStrictInvalid
	 * @group type-array
	 * @group strict-mode
	 *
	 * @param mixed $value The value to add in the option.
	 */
	public function testRegistryAddTypeArrayStrictInvalid($value, string $errorMessage): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		add_site_option($this->optionName, $value);
	}

	/**
	 * @dataProvider dataTypeArrayStrictInvalid
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
		add_site_option($this->optionName, (new InputSanitizer())->sanitize($value));

		$registry = new Registry(1);
		$registry->addOptions(...[new NetworkOption($this->optionName, 'array')]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(TypeError::class);
		$this->expectExceptionMessage($errorMessage);

		update_site_option($this->optionName, $value);
	}

	public function dataTypeArrayStrictInvalid(): iterable
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
	 * @dataProvider dataConstraints
	 * @group strict-mode
	 *
	 * @param mixed $constraints  The constraints to be passed in the schema.
	 * @param mixed $value        The value to add in the option.
	 * @param mixed $errorMessage The expected error message.
	 */
	public function testAddConstraints($constraints, $value, $errorMessage): void
	{
		$registry = new Registry(1);
		$registry->addOptions(...[(new NetworkOption($this->optionName, 'string'))->withConstraints(...$constraints)]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($errorMessage);

		add_site_option($this->optionName, $value);
	}

	/**
	 * @dataProvider dataConstraints
	 *
	 * @param mixed $constraints The constraints to be passed in the schema.
	 * @param mixed $value       The value to add in the option.
	 */
	public function testAddConstraintsNonStrict($constraints, $value): void
	{
		$registry = new Registry();
		$registry->addOptions(...[(new NetworkOption($this->optionName, 'string'))->withConstraints(...$constraints)]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertTrue(add_site_option($this->optionName, $value));
		$this->assertSame($value, get_site_option($this->optionName));
	}

	/**
	 * @dataProvider dataConstraints
	 * @group strict-mode
	 *
	 * @param mixed $constraints  The constraints to be passed in the schema.
	 * @param mixed $value        The value to add in the option.
	 * @param mixed $errorMessage The expected error message.
	 */
	public function testUpdateConstraints($constraints, $value, $errorMessage): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value updated with the `update_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize('email@example.org'));

		$registry = new Registry(1);
		$registry->addOptions(...[(new NetworkOption($this->optionName, 'string'))->withConstraints(...$constraints)]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($errorMessage);

		update_site_option($this->optionName, $value);
	}

	/**
	 * @dataProvider dataConstraints
	 *
	 * @param mixed $constraints The constraints to be passed in the schema.
	 * @param mixed $value       The value to add in the option.
	 */
	public function testUpdateConstraintsNonStrict($constraints, $value): void
	{
		/**
		 * Assumes that the option is already added with a value since the test only
		 * concerns about the value updated with the `update_option` function.
		 */
		add_site_option($this->optionName, (new InputSanitizer())->sanitize('email@example.org'));

		$registry = new Registry();
		$registry->addOptions(...[(new NetworkOption($this->optionName, 'string'))->withConstraints(...$constraints)]);
		$registry->hook($this->hook);
		$registry->register();

		$this->hook->register();

		$this->assertSame('email@example.org', get_site_option($this->optionName));

		update_site_option($this->optionName, $value);

		$this->assertSame($value, get_site_option($this->optionName));
	}

	public function dataConstraints(): iterable
	{
		yield [
			['\Syntatis\Utils\is_email'],
			'Maybe Email',
			'Value does not match the given constraints.',
		];

		yield [
			[new Assert\Email(null, 'The email {{ value }} is not a valid email.')],
			'Hello Email',
			'The email "Hello Email" is not a valid email.',
		];
	}
}
