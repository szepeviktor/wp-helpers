<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Tests;

use Syntatis\WPHelpers\Contracts\InlineScript;
use Syntatis\WPHelpers\Enqueue\Script;

class ScriptTest extends WPTestCase
{
	public function testGet(): void
	{
		$script = new Script([
			'url' => 'https://example.com/assets/script.js',
			'handle' => 'script',
			'dependencies' => ['jquery'],
			'version' => '1.0.0',
		]);

		$this->assertSame([
			'url' => 'https://example.com/assets/script.js',
			'handle' => 'script',
			'dependencies' => ['jquery'],
			'version' => '1.0.0',
			'inline' => [],
			'localized' => false,
		], $script->get());
	}

	public function testWithInlineScripts(): void
	{
		$inlineScript = new class implements InlineScript {
			public function getInlineScriptPosition(): string
			{
				return 'before';
			}

			public function getInlineScriptContent(): string
			{
				return 'console.log("Hello, World!");';
			}
		};

		$script = (new Script([
			'url' => 'https://example.com/assets/script.js',
			'handle' => 'script',
			'dependencies' => ['jquery'],
			'version' => '1.0.0',
		]))->withInlineScripts($inlineScript);

		$this->assertSame([
			'url' => 'https://example.com/assets/script.js',
			'handle' => 'script',
			'dependencies' => ['jquery'],
			'version' => '1.0.0',
			'inline' => [
				[
					'position' => 'before',
					'data' => 'console.log("Hello, World!");',
				],
			],
			'localized' => false,
		], $script->get());
	}

	public function testWithTranslation(): void
	{
		$script = (new Script([
			'url' => 'https://example.com/assets/script.js',
			'handle' => 'script',
			'dependencies' => ['jquery'],
			'version' => '1.0.0',
		]))
		->hasTranslation();

		$this->assertSame([
			'url' => 'https://example.com/assets/script.js',
			'handle' => 'script',
			'dependencies' => ['jquery'],
			'version' => '1.0.0',
			'inline' => [],
			'localized' => true,
		], $script->get());
	}
}
