<?php
/**
 * Test double for the plugin accessor geoins() (defined in wille-geo-ai-visibility.php, which boots every module).
 *
 * @package Wille_GEO
 */

/**
 * @return GEOINS_Test_Plugin
 */
function geoins() {
	return new GEOINS_Test_Plugin();
}
