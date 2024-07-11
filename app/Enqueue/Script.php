<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Enqueue;

use Syntatis\WPHelpers\Contracts\InlineScript;

use function array_merge;

class Script
{
	/** @var array<InlineScript> */
	private array $inlineScripts = [];

	private bool $localized = false;

	/** @var array{handle:string,url:string,dependencies:array<string>,version:string|null} */
	private array $args;

	/** @param array{handle:string,url:string,dependencies:array<string>,version:string|null} $args */
	public function __construct(array $args)
	{
		$this->args = $args;
	}

	/**
	 * If set to `true` it will load with the translation data related to the script,
	 * through Wordress native function `wp_set_script_translations`.
	 *
	 * @see https://make.wordpress.org/core/2018/11/09/new-javascript-i18n-support-in-wordpress/
	 */
	public function hasTranslation(bool $localized = true): self
	{
		$self = clone $this;
		$self->localized = $localized;

		return $self;
	}

	public function withInlineScripts(InlineScript ...$inlineScripts): self
	{
		$self = clone $this;
		$self->inlineScripts = $inlineScripts;

		return $self;
	}

	/** @return array{handle:string,url:string,localized:bool,dependencies:array<string>,version:string|null,inline:array<array{data:string,position:string}>} */
	public function get(): array
	{
		$inline = [];

		foreach ($this->inlineScripts as $inlineScript) {
			$inline[] = [
				'position' => $inlineScript->getInlineScriptPosition(),
				'data' => $inlineScript->getInlineScriptContent(),
			];
		}

		return array_merge(
			$this->args,
			[
				'inline' => $inline,
				'localized' => $this->localized,
			],
		);
	}
}
