<?php
/**
 * PHPUnit bootstrap: plugin classes without WordPress (Brain Monkey stubs the functions).
 *
 * @package GEO_Insights
 */

require dirname( __DIR__ ) . '/vendor/autoload.php';

define( 'ABSPATH', __DIR__ . '/fixtures/wordpress/' );
define( 'GEOINS_VERSION', '0.0.0-test' );
define( 'GEOINS_DB_VERSION', '0' );
define( 'GEOINS_FILE', dirname( __DIR__ ) . '/geo-insights-ai/geo-insights-ai.php' );
define( 'GEOINS_DIR', dirname( __DIR__ ) . '/geo-insights-ai/' );
define( 'GEOINS_URL', 'https://example.org/wp-content/plugins/geo-insights-ai/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );

// Plugin classes are autoloaded via composer.json "autoload-dev" (classmap of geo-insights-ai/includes/).
require __DIR__ . '/stubs/polyfills.php';
require __DIR__ . '/stubs/class-wp-post.php';
require __DIR__ . '/stubs/class-geoins-test-plugin.php';
require __DIR__ . '/stubs/functions.php';
