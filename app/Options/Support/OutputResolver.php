<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Options\Support;

use Syntatis\WPHelpers\Options\Contracts\Castable;
use Syntatis\WPHelpers\Options\Option;
use Syntatis\WPHelpers\Options\ValueTypes\TypeArray;
use Syntatis\WPHelpers\Options\ValueTypes\TypeBoolean;
use Syntatis\WPHelpers\Options\ValueTypes\TypeInteger;
use Syntatis\WPHelpers\Options\ValueTypes\TypeNumber;
use Syntatis\WPHelpers\Options\ValueTypes\TypeString;

use function array_key_exists;
use function is_array;

/**
 * @phpstan-import-type ValueType from Option
 *
 * @template T of Castable
 */
class OutputResolver
{
	protected string $type;

	protected int $strict;

	/**
	 * @var array<string, string>
	 * @phpstan-var array<ValueType, class-string<T>>
	 */
	protected array $casters = [
		'array' => TypeArray::class,
		'boolean' => TypeBoolean::class,
		'number' => TypeNumber::class,
		'integer' => TypeInteger::class,
		'string' => TypeString::class,
	];

	/** @phpstan-param ValueType $type */
	public function __construct(string $type, int $strict = 0)
	{
		$this->type = $type;
		$this->strict = $strict;
	}

	/**
	 * Resolve the value passed into the select type.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public function resolve($value)
	{
		$value = is_array($value) && array_key_exists('__syntatis', $value) ? $value['__syntatis'] : $value;

		if ($value === null) {
			return $value;
		}

		return isset($this->casters[$this->type]) ?
			(new $this->casters[$this->type]($value))->cast($this->strict) :
			$value;
	}
}
