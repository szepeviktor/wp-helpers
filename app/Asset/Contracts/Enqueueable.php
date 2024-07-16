<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Asset\Contracts;

interface Enqueueable
{
	public function getHandle(): string;

	public function getFilePath(): string;

	/** @phpstan-return non-empty-string|null */
	public function getVersion(): ?string;

	/** @return array<string> */
	public function getDependencies(): array;
}
