<?php

declare(strict_types=1);

namespace Syntatis\WPHelpers\Tests;

use Syntatis\WPHelpers\Enqueue\Style;

class StyleTest extends WPTestCase
{
	/** @dataProvider dataGetHandle */
	public function testGetHandle(string $filePath, string $expected): void
	{
		$this->assertSame($expected, (new Style($filePath))->getHandle());
	}

	public static function dataGetHandle(): iterable
	{
		yield ['/index.scss', 'index'];
		yield ['/style.scss', 'style'];
		yield ['/style.min.css', 'style-min'];

		// With dashes.
		yield ['/admin-style.scss', 'admin-style'];
		yield ['/admin-style.min.css', 'admin-style-min'];

		// Sub-directories.
		yield ['/admin/index.scss', 'admin-index'];
		yield ['/admin/style.scss', 'admin-style'];
		yield ['/admin/style.min.css', 'admin-style-min'];

		// Nested Sub-directories.
		yield ['/admin/app/index.scss', 'admin-app-index'];
		yield ['/admin/app/style.scss', 'admin-app-style'];
		yield ['/admin/app/style.min.css', 'admin-app-style-min'];
	}

	/** @dataProvider dataGetHandleFromArg */
	public function testGetHandleFromArg(string $filePath): void
	{
		$this->assertSame(
			'hello-world-admin-style',
			(new Style($filePath, 'hello-world-admin-style'))->getHandle(),
		);
	}

	public static function dataGetHandleFromArg(): iterable
	{
		yield ['/index.scss'];
		yield ['/style.scss'];
	}

	/** @dataProvider dataGetFilePath */
	public function testGetFilePath(string $filePath, string $expected): void
	{
		$this->assertSame($expected, (new Style($filePath))->getFilePath());
	}

	public static function dataGetFilePath(): iterable
	{
		yield ['/index.css', '/index.css'];
		yield ['/index.scss', '/index.css'];
		yield ['/style.css', '/style.css'];
		yield ['/style.scss', '/style.css'];
		yield ['/style.min.css', '/style.min.css'];

		// With dashes.
		yield ['/admin-style.css', '/admin-style.css'];
		yield ['/admin-style.scss', '/admin-style.css'];

		// Sub-directories.
		yield ['/admin/index.scss', '/admin/index.css'];
		yield ['/admin/style.scss', '/admin/style.css'];
		yield ['/admin/style.min.css', '/admin/style.min.css'];

		// Nested sub-directories.
		yield ['/admin/app/index.css', '/admin/app/index.css'];
		yield ['/admin/app/index.scss', '/admin/app/index.css'];
	}

	public function testOnMedia(): void
	{
		$style = new Style('/style.css');

		$this->assertSame('all', $style->getMedia());

		$style = $style->onMedia('print');

		$this->assertSame('print', $style->getMedia());
	}

	public function testWithDependencies(): void
	{
		$style = (new Style('/style.css'))->withDependencies(['main']);

		$this->assertSame(['main'], $style->getDependencies());
	}

	public function testVersionedAt(): void
	{
		$style = (new Style('/style.css'))->versionedAt('v1');

		$this->assertSame('v1', $style->getVersion());
	}
}
