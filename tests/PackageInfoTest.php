<?php

declare(strict_types=1);

namespace Live627\ModTests\Tests;

use Live627\ModTests\PackageInfoTestCase;

final class PackageInfoTest extends PackageInfoTestCase
{
	/***********************
	 * Public static methods
	 ***********************/

	public static function providePackageInfoCases(): iterable
	{
		yield [__DIR__ . '/fixtures/package-info.xml'];
	}
}
