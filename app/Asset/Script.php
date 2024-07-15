<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Asset;

use Syntatis\WPHelpers\Asset\Contracts\Enqueueable;
use Syntatis\WPHelpers\Asset\Contracts\InlineScript;
use Syntatis\WPHelpers\Asset\Traits\FilePathDefiner;

use function pathinfo;
use function str_starts_with;
use function Syntatis\Utils\is_blank;

/**
 * Defines the JavaScript file to enqueue.
 */
class Script implements Enqueueable
{
	use FilePathDefiner;

	protected bool $isTranslated = false;
	protected bool $footer = false;

	/**
	 * The version of the script.
	 *
	 * If it is set, it will be appended to the script URL as a query string,
	 * and will override the version provided from the `*.asset.php` file
	 * generated from `@wordpress/scripts`.
	 */
	protected ?string $version = null;

	/** @var array<InlineScript> */
	protected array $inlineScripts = [];

	/** @var array<string> */
	protected array $dependencies = [];

	/** @phpstan-var non-empty-string */
	protected string $filePath;

	/** @phpstan-var non-empty-string */
	protected string $manifestPath;

	/** @phpstan-var non-empty-string */
	protected string $handle;

	/** @var array{dirname:string,filename:string,basename:string,extension:string} */
	protected array $fileInfo;

	/**
	 * @param string $filePath The path to the script file, relative to the directory path set in the
	 *                         `Enqueue` class.
	 * @param string $handle   Optional. Name of the script. Should be unique. By default, when it is
	 *                         not set, it will try to determine the handle from the file name. If
	 *                         the file name is `script.ts`, the handle will be `script`. Keep
	 *                         in mind that handles generated from file names may not be
	 *                         unique. In such cases, it's better to pass the the
	 *                         argument in this parameter to set the handle.
	 *
	 * @phpstan-param non-empty-string $filePath
	 * @phpstan-param non-empty-string|null $handle
	 */
	public function __construct(string $filePath, ?string $handle = null)
	{
		$this->fileInfo = pathinfo(str_starts_with($filePath, '/') ? $filePath : '/' . $filePath);
		$this->filePath = $this->definePath($filePath, '.js');
		$this->manifestPath = $this->definePath($filePath, '.asset.php');
		$this->handle = is_blank($handle) ? $this->defineHandle($filePath) : $handle;
	}

	/**
	 * If set to `true` it will load with the translation data related to the
	 * script, through Wordress native function `wp_set_script_translations`.
	 *
	 * @see https://make.wordpress.org/core/2018/11/09/new-javascript-i18n-support-in-wordpress/
	 */
	public function withTranslation(bool $translated = true): self
	{
		$self = clone $this;
		$self->isTranslated = $translated;

		return $self;
	}

	public function atFooter(bool $footer = true): self
	{
		$self = clone $this;
		$self->footer = $footer;

		return $self;
	}

	/** @phpstan-param non-empty-string $version */
	public function versionedAt(string $version): self
	{
		$self = clone $this;
		$self->version = $version;

		return $self;
	}

	public function withInlineScripts(InlineScript ...$inlineScripts): self
	{
		$self = clone $this;
		$self->inlineScripts = $inlineScripts;

		return $self;
	}

	/**
	 * @param array<string> $dependencies
	 *
	 * @phpstan-param array<non-empty-string> $dependencies
	 */
	public function withDependencies(array $dependencies): self
	{
		$self = clone $this;
		$self->dependencies = $dependencies;

		return $self;
	}

	/** @phpstan-return non-empty-string */
	public function getHandle(): string
	{
		return $this->handle;
	}

	/** @phpstan-return non-empty-string */
	public function getFilePath(): string
	{
		return $this->filePath;
	}

	/** @phpstan-return non-empty-string */
	public function getManifestPath(): string
	{
		return $this->manifestPath;
	}

	/** @return array<InlineScript> */
	public function getInlineScripts(): array
	{
		return $this->inlineScripts;
	}

	/** @inheritDoc */
	public function getDependencies(): array
	{
		return $this->dependencies;
	}

	public function isTranslated(): bool
	{
		return $this->isTranslated;
	}

	public function isAtFooter(): bool
	{
		return $this->footer;
	}

	public function getVersion(): ?string
	{
		return $this->version;
	}
}
