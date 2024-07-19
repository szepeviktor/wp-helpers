<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Options\Contracts;

interface Registrable
{
	public function register(): void;

	public function deregister(): void;
}
