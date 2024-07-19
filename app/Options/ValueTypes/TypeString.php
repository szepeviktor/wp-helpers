<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Options\ValueTypes;

use Syntatis\WPHelpers\Options\Contracts\Castable;
use Syntatis\WPHelpers\Options\Exceptions\TypeError;
use Throwable;

use function is_string;

/**
 * Cast a value to a string.
 *
 * It carries different levels of strictness:
 * - 0: Casts the value to string, if possible. Otherwise, it returns a `null`.
 * - 1: Return the value as is, which may throw an exception if the value is not a string.
 */
class TypeString implements Castable
{
	/**
	 * The value to cast to string.
	 *
	 * @var mixed
	 */
	private $value;

	/** @param mixed $value */
	public function __construct($value)
	{
		$this->value = $value;
	}

	/** @throws TypeError If the value is not a string. */
	public function cast(int $strict = 0): ?string
	{
		if ($this->value === null) {
			return $this->value;
		}

		if ($strict === 1) {
			if (! is_string($this->value)) {
				throw new TypeError('string', $this->value);
			}
		}

		try {
			// @phpstan-ignore-next-line -- May throw an exception, if the value cannot be casted to string e.g. an object, or an array.
			return (string) $this->value;
		// @phpstan-ignore-next-line
		} catch (Throwable $th) {
			return null;
		}
	}
}
