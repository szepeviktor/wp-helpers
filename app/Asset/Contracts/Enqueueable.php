<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Asset\Contracts;

interface Enqueueable
{
	public function getHandle(): string;

	public function getFilePath(): string;

	public function getVersion(): ?string;

	/**
	 * @return array<string>
	 *
	 * @phpstan-return array<non-empty-string>
	 */
	public function getDependencies(): array;
}
