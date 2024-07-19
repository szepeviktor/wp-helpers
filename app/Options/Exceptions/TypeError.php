<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Options\Exceptions;

use Error;

use function get_class;
use function gettype;
use function is_object;
use function strtolower;

class TypeError extends Error
{
	private const TYPE_MAP = ['double' => 'number (float)'];

	/**
	 * @param string $expected The expected type of the value e.g. 'string', 'integer', 'array', etc.
	 * @param mixed  $value    The value given.
	 */
	public function __construct(string $expected, $value)
	{
		$expected = self::TYPE_MAP[$expected] ?? $expected;

		parent::__construct('Value must be of type ' . $expected . ', ' . $this->inferType($value) . ' given.');
	}

	/**
	 * Infer the type of value given.
	 *
	 * @param mixed $value
	 */
	private function inferType($value): string
	{
		if (is_object($value)) {
			return get_class($value);
		}

		$inferredType = strtolower(gettype($value));

		return self::TYPE_MAP[$inferredType] ?? $inferredType;
	}
}
