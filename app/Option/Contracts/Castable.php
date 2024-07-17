<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Option\Contracts;

interface Castable
{
	/** @return mixed Return the value resolved. */
	public function cast(int $strict);
}
