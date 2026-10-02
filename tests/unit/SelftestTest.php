<?php
/**
 * The built-in self-test (wp geoins selftest, REST geoins/v1/selftest) passes outside WordPress too.
 *
 * @package GEO_Insights
 */

namespace GEOINS\Tests;

use GEOINS_Selftest;

final class SelftestTest extends TestCase {

	public function test_all_assertions_pass(): void {
		$result = GEOINS_Selftest::run();

		$this->assertSame( array(), $result['failures'] );
		$this->assertGreaterThanOrEqual( 40, $result['passed'] );
	}
}
