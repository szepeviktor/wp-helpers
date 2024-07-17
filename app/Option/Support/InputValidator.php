<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Option\Support;

use InvalidArgumentException;
use Symfony\Component\Validator\Constraint;
use Syntatis\Utils\Validator\Validator;
use Syntatis\WPHelpers\Option\Exceptions\TypeError;
use Syntatis\WPHelpers\Option\Option;
use TypeError as PHPTypeError;

use function array_key_exists;
use function gettype;
use function is_array;
use function is_bool;
use function is_callable;
use function is_float;
use function is_int;
use function is_string;

/**
 * @phpstan-import-type Constraints from Option
 * @phpstan-import-type ValueType from Option
 */
class InputValidator
{
	/** @phpstan-var ValueType */
	private string $type;

	/** @phpstan-var array<Constraints> */
	private array $constraints;

	/**
	 * @phpstan-param ValueType $type
	 * @phpstan-param array<Constraints> $constraints
	 */
	public function __construct(string $type, array $constraints = [])
	{
		$this->type = $type;
		$this->constraints = $constraints;
	}

	/** @param mixed $value */
	public function validate($value): void
	{
		$value = is_array($value) && array_key_exists('__syntatis', $value) ? $value['__syntatis'] : $value;

		if ($value === null) {
			return;
		}

		$givenType = gettype($value);
		$matchedType = $this->hasMatchedType($value);

		if ($matchedType === false) {
			throw new TypeError($this->type, $value);
		}

		if ($matchedType === null) {
			throw new PHPTypeError('Unable to validate of type ' . $this->type . '.');
		}

		$this->validateWithConstraints($value);
	}

	/** @param mixed $value */
	private function hasMatchedType($value): ?bool
	{
		switch ($this->type) {
			case 'string':
				return is_string($value);

			case 'boolean':
				return is_bool($value);

			case 'integer':
				return is_int($value);

			case 'number':
				return is_float($value) || is_int($value);

			case 'array':
				return is_array($value);

			default:
				return null;
		}
	}

	/** @param mixed $value */
	private function validateWithConstraints($value): void
	{
		if (! is_array($this->constraints)) {
			return;
		}

		foreach ($this->constraints as $constraint) {
			if (is_callable($constraint)) {
				$result = $constraint($value);

				if ($result === false) {
					throw new InvalidArgumentException('Value does not match the given constraints.');
				}
			}

			if (! $constraint instanceof Constraint) {
				continue;
			}

			$validators = Validator::instance()->validate($value, $constraint);

			foreach ($validators as $validator) {
				throw new InvalidArgumentException((string) $validator->getMessage());
			}
		}
	}
}
