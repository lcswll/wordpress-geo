<?php
/**
 * Test double for the plugin accessor geoins() (defined in geo-insights-ai.php, which boots every module).
 *
 * @package GEO_Insights
 */

/**
 * @return GEOINS_Test_Plugin
 */
function geoins() {
	return new GEOINS_Test_Plugin();
}
