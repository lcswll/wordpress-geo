<?php
/**
 * Test double for the plugin accessor geoins() (defined in geo-insights-ai.php, which boots every module).
 * Tests set the settings via TestCase::$settings.
 *
 * @package GEO_Insights
 */

// phpcs:disable Squiz.Commenting
final class GEOINS_Test_Plugin {
	/** @var array<string,mixed> */
	public static $settings = array();

	/** @return array<string,mixed> */
	public function settings() {
		return array_merge( GEOINS_Install::defaults(), self::$settings );
	}
}
