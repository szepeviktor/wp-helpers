<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Tests\Option\Support;

use Syntatis\WPHelpers\Option\Support\InputSanitizer;
use Syntatis\WPHelpers\Tests\WPTestCase;

class InputSanitizerTest extends WPTestCase
{
	/**
	 * @dataProvider dataSanitize
	 *
	 * @param mixed $value  The value to validate.
	 * @param mixed $expect The type of the value to validate.
	 */
	public function testSanitize($value, $expect): void
	{
		$sanitizer = new InputSanitizer();

		$this->assertSame($expect, $sanitizer->sanitize($value));
	}

	public function dataSanitize(): iterable
	{
		yield 'string' => ['Hello world!', 'Hello world!'];
		yield 'integer' => [1, 1];
		yield 'number (float)' => [1.23, ['__syntatis' => 1.23]];
		yield 'boolean (true)' => [true, true];
		yield 'boolean (false)' => [false, ['__syntatis' => false]];
		yield 'array' => [['foo'], ['foo']];
		yield 'null' => [null, ['__syntatis' => null]];
	}
}
