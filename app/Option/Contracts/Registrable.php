<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Option\Contracts;

interface Registrable
{
	public function register(): void;

	public function deregister(): void;
}
