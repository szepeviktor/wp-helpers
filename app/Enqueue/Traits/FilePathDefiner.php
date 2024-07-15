<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Enqueue\Traits;

use function sprintf;
use function str_replace;
use function Syntatis\Utils\kebabcased;

use const DIRECTORY_SEPARATOR;

trait FilePathDefiner
{
	private function defineHandle(string $filePath): string
	{
		$fileName = str_replace('.', '-', $this->fileInfo['filename']);
		$dirName = $this->fileInfo['dirname'];

		if ($dirName === '/') {
			return kebabcased($fileName);
		}

		$dirName = str_replace(['/', '.'], '-', $dirName);

		return kebabcased(sprintf('%s-%s', $dirName, $fileName));
	}

	/** @phpstan-return non-empty-string */
	private function definePath(string $filePath, string $extension): string
	{
		$fileName = $this->fileInfo['filename'];
		$dirName = $this->fileInfo['dirname'];
		$filePath = DIRECTORY_SEPARATOR . $fileName . $extension;

		if ($this->fileInfo['dirname'] === '/') {
			return wp_normalize_path($filePath);
		}

		return wp_normalize_path($dirName . $filePath);
	}
}
