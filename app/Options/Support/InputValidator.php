<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Options\Support;

use InvalidArgumentException;
use Symfony\Component\Validator\Constraint;
use Syntatis\Utils\Validator\Validator;
use Syntatis\WPHelpers\Options\Exceptions\TypeError;
use Syntatis\WPHelpers\Options\Option;
use TypeError as PHPTypeError;

use function array_key_exists;
use function gettype;
use function is_array;
use function is_bool;
use function is_callable;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;
use function Syntatis\Utils\is_blank;

/**
 * @phpstan-import-type Constraints from Option
 * @phpstan-import-type ValueType from Option
 */
class InputValidator
{
	private string $optionName;

	/** @phpstan-var ValueType */
	private string $type;

	private int $strict;

	/** @phpstan-var array<Constraints> */
	private array $constraints = [];

	/** @phpstan-param ValueType $type */
	public function __construct(string $type, string $optionName, int $strict = 0)
	{
		$this->type = $type;
		$this->optionName = $optionName;
		$this->strict = $strict;
	}

	/** @phpstan-param array<Constraints> $constraints */
	public function setConstraints(array $constraints): self
	{
		$this->constraints = $constraints;

		return $this;
	}

	/** @param mixed $value */
	public function validate($value): void
	{
		$value = is_array($value) && array_key_exists('__syntatis', $value) ? $value['__syntatis'] : $value;

		if ($value === null) {
			return;
		}

		$this->validateWithConstraints($value);

		if (! $this->strict) {
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
		if (! is_array($this->constraints) || $this->constraints === []) {
			return;
		}

		foreach ($this->constraints as $constraint) {
			if (is_callable($constraint)) {
				$result = $constraint($value);

				if (is_string($result) && ! is_blank($result)) {
					throw new InvalidArgumentException(
						sprintf('[%s] %s', $this->optionName, $result),
					);
				}

				if ($result === false) {
					throw new InvalidArgumentException(
						sprintf('[%s] The value does not match the constraint.', $this->optionName),
					);
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
