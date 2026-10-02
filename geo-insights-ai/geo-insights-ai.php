<?php
/**
 * Plugin Name:       GEO Insights – AI Search Visibility & Stats
 * Plugin URI:        https://github.com/lcswll/wordpress-geo
 * Description:       See which AI (ChatGPT, Claude, Perplexity …) reads which of your pages – and optimize your site for AI search (GEO). Free, no personal data.
 * Version:           2.2.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Lucas Wille
 * Author URI:        https://lucaswille.de/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       geo-insights-ai
 * Domain Path:       /languages
 *
 * @package GEO_Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GEOINS_VERSION', '2.2.0' );
define( 'GEOINS_DB_VERSION', '4' ); // v1.8.0: no schema change, bumped so upgrades seed the alerts seen-sources map.
define( 'GEOINS_FILE', __FILE__ );
define( 'GEOINS_DIR', plugin_dir_path( __FILE__ ) );
define( 'GEOINS_URL', plugin_dir_url( __FILE__ ) );

require_once GEOINS_DIR . 'includes/class-geoins-bots.php';
require_once GEOINS_DIR . 'includes/class-geoins-install.php';
require_once GEOINS_DIR . 'includes/class-geoins-verify.php';
require_once GEOINS_DIR . 'includes/class-geoins-stats.php';
require_once GEOINS_DIR . 'includes/class-geoins-tracker.php';
require_once GEOINS_DIR . 'includes/class-geoins-beacon.php';
require_once GEOINS_DIR . 'includes/class-geoins-markdown.php';
require_once GEOINS_DIR . 'includes/class-geoins-llms-txt.php';
require_once GEOINS_DIR . 'includes/class-geoins-robots.php';
require_once GEOINS_DIR . 'includes/class-geoins-schema.php';
require_once GEOINS_DIR . 'includes/class-geoins-meta.php';
require_once GEOINS_DIR . 'includes/class-geoins-analysis.php';
require_once GEOINS_DIR . 'includes/class-geoins-content.php';
require_once GEOINS_DIR . 'includes/class-geoins-indexnow.php';
require_once GEOINS_DIR . 'includes/class-geoins-report.php';
require_once GEOINS_DIR . 'includes/class-geoins-alerts.php';
require_once GEOINS_DIR . 'includes/class-geoins-audit.php';
require_once GEOINS_DIR . 'includes/class-geoins-selftest.php';
require_once GEOINS_DIR . 'includes/class-geoins-rest.php';
require_once GEOINS_DIR . 'includes/class-geoins-plugin.php';

if ( is_admin() ) {
	require_once GEOINS_DIR . 'includes/admin/class-geoins-admin.php';
	require_once GEOINS_DIR . 'includes/admin/class-geoins-dashboard.php';
	require_once GEOINS_DIR . 'includes/admin/class-geoins-columns.php';
	require_once GEOINS_DIR . 'includes/admin/class-geoins-review.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once GEOINS_DIR . 'includes/class-geoins-cli.php';
	WP_CLI::add_command( 'geoins', 'GEOINS_CLI' );
}

register_activation_hook( __FILE__, array( 'GEOINS_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GEOINS_Install', 'deactivate' ) );

/**
 * Returns the main plugin instance.
 *
 * @return GEOINS_Plugin
 */
function geoins() {
	return GEOINS_Plugin::instance();
}

/**
 * Boots the plugin (plugins_loaded callback; actions discard return values).
 *
 * @return void
 */
function geoins_boot() {
	geoins();
}

add_action( 'plugins_loaded', 'geoins_boot' );
