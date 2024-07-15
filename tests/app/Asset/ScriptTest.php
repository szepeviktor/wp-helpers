<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Tests\Asset;

use Syntatis\WPHelpers\Asset\Contracts\InlineScript;
use Syntatis\WPHelpers\Asset\Script;
use Syntatis\WPHelpers\Tests\WPTestCase;

class ScriptTest extends WPTestCase
{
	/** @dataProvider dataGetHandle */
	public function testGetHandle(string $filePath, string $expected): void
	{
		$this->assertSame($expected, (new Script($filePath))->getHandle());
	}

	public static function dataGetHandle(): iterable
	{
		yield ['/index.js', 'index'];
		yield ['/index.ts', 'index'];
		yield ['/index.tsx', 'index'];
		yield ['/script.js', 'script'];
		yield ['/script.ts', 'script'];
		yield ['/script.tsx', 'script'];
		yield ['/script.min.js', 'script-min'];

		// With dashes.
		yield ['/admin-script.js', 'admin-script'];
		yield ['/admin-script.ts', 'admin-script'];
		yield ['/admin-script.tsx', 'admin-script'];

		// With dashes.
		yield ['/admin_script.js', 'admin-script'];
		yield ['/admin_script.ts', 'admin-script'];
		yield ['/admin_script.tsx', 'admin-script'];

		// Sub-directories
		yield ['/admin/index.js', 'admin-index'];
		yield ['/admin/index.ts', 'admin-index'];
		yield ['/admin/index.tsx', 'admin-index'];
		yield ['/admin/script.js', 'admin-script'];
		yield ['/admin/script.ts', 'admin-script'];
		yield ['/admin/script.tsx', 'admin-script'];
		yield ['/admin/script.min.js', 'admin-script-min'];

		// Nested sub-directories
		yield ['/admin/app/index.js', 'admin-app-index'];
		yield ['/admin/app/index.ts', 'admin-app-index'];
		yield ['/admin/app/index.tsx', 'admin-app-index'];
		yield ['/admin/app/script.js', 'admin-app-script'];
		yield ['/admin/app/script.ts', 'admin-app-script'];
		yield ['/admin/app/script.tsx', 'admin-app-script'];
		yield ['/admin/app/script.min.js', 'admin-app-script-min'];
	}

	/** @dataProvider dataGetHandleFromArg */
	public function testGetHandleFromArg(string $filePath): void
	{
		$this->assertSame(
			'hello-world-admin-script',
			(new Script($filePath, 'hello-world-admin-script'))->getHandle(),
		);
	}

	public static function dataGetHandleFromArg(): iterable
	{
		yield ['/index.js'];
		yield ['/script.js'];
	}

	/** @dataProvider dataGetFilePath */
	public function testGetFilePath(string $filePath, string $expected): void
	{
		$this->assertSame($expected, (new Script($filePath))->getFilePath());
	}

	public static function dataGetFilePath(): iterable
	{
		yield ['/index.js', '/index.js'];
		yield ['/index.ts', '/index.js'];
		yield ['/index.tsx', '/index.js'];
		yield ['/script.js', '/script.js'];
		yield ['/script.ts', '/script.js'];
		yield ['/script.tsx', '/script.js'];
		yield ['/script.min.js', '/script.min.js'];

		// With dashes.
		yield ['/admin-script.js', '/admin-script.js'];
		yield ['/admin-script.ts', '/admin-script.js'];
		yield ['/admin-script.tsx', '/admin-script.js'];

		// With dashes.
		yield ['/admin_script.js', '/admin_script.js'];
		yield ['/admin_script.ts', '/admin_script.js'];
		yield ['/admin_script.tsx', '/admin_script.js'];

		// Sub-directories
		yield ['/admin/index.js', '/admin/index.js'];
		yield ['/admin/index.ts', '/admin/index.js'];
		yield ['/admin/index.tsx', '/admin/index.js'];
		yield ['/admin/script.js', '/admin/script.js'];
		yield ['/admin/script.ts', '/admin/script.js'];
		yield ['/admin/script.tsx', '/admin/script.js'];
		yield ['/admin/script.min.js', '/admin/script.min.js'];

		// Nested sub-directories
		yield ['/admin/app/index.js', '/admin/app/index.js'];
		yield ['/admin/app/index.ts', '/admin/app/index.js'];
		yield ['/admin/app/index.tsx', '/admin/app/index.js'];
		yield ['/admin/app/script.js', '/admin/app/script.js'];
		yield ['/admin/app/script.ts', '/admin/app/script.js'];
		yield ['/admin/app/script.tsx', '/admin/app/script.js'];
		yield ['/admin/app/script.min.js', '/admin/app/script.min.js'];
	}

	/** @dataProvider dataGetManifestPath */
	public function testGetManifestPath(string $filePath, string $expected): void
	{
		$this->assertSame($expected, (new Script($filePath))->getManifestPath());
	}

	public static function dataGetManifestPath(): iterable
	{
		yield ['/index.js', '/index.asset.php'];
		yield ['/index.ts', '/index.asset.php'];
		yield ['/index.tsx', '/index.asset.php'];
		yield ['/script.js', '/script.asset.php'];
		yield ['/script.ts', '/script.asset.php'];
		yield ['/script.tsx', '/script.asset.php'];

		// With dashes.
		yield ['/admin-script.js', '/admin-script.asset.php'];
		yield ['/admin-script.ts', '/admin-script.asset.php'];
		yield ['/admin-script.tsx', '/admin-script.asset.php'];

		// Sub-directories.
		yield ['/admin/index.js', '/admin/index.asset.php'];
		yield ['/admin/script.ts', '/admin/script.asset.php'];
		yield ['/admin/script.tsx', '/admin/script.asset.php'];
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

		$script = (new Script('/admin/index.js'))->withInlineScripts($inlineScript);

		$this->assertSame([$inlineScript], $script->getInlineScripts());
	}

	public function testGetPosition(): void
	{
		$script = new Script('/admin/index.js');

		$this->assertFalse($script->isAtFooter());

		$script = (new Script('/admin/index.js'))->atFooter();

		$this->assertTrue($script->isAtFooter());

		$script = (new Script('/admin/index.js'))->atFooter(false);

		$this->assertFalse($script->isAtFooter());
	}

	public function testIsTranslated(): void
	{
		$script = new Script('/admin/index.js');

		$this->assertFalse($script->isTranslated());

		$script = (new Script('/admin/index.js'))->withTranslation();

		$this->assertTrue($script->isTranslated());

		$script = $script->withTranslation(false);

		$this->assertFalse($script->isTranslated());
	}
}
