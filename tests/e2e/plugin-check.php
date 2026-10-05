<?php
/**
 * Runs the static (non-PHPCS) checks of the official Plugin Check plugin inside Playground
 * and writes the findings to /e2e-out/plugin-check.json.
 *
 * The PHPCS-based Plugin Check rules run outside Playground (scripts/plugin-check.mjs) because
 * php-wasm cannot take the file locks PHPCS uses for its temp reports.
 *
 * @package Wille_GEO
 */

require '/wordpress/wp-load.php';

$geoins_checks = array(
	'code_obfuscation',
	'plugin_content',
	'file_type',
	'plugin_header_fields',
	'plugin_updater',
	'plugin_uninstall',
	'plugin_readme',
	'no_unfiltered_uploads',
	'trademarks',
	'direct_file_access',
	'external_admin_menu_links',
	'wp_functions_compatibility',
);

$geoins_runner = new WordPress\Plugin_Check\Checker\AJAX_Runner();
$geoins_runner->set_plugin( 'wille-geo-ai-visibility/wille-geo-ai-visibility.php' );
$geoins_runner->set_check_slugs( $geoins_checks );
$geoins_runner->set_experimental_flag( true );
$geoins_cleanup = $geoins_runner->prepare();
$geoins_result  = $geoins_runner->run();
$geoins_cleanup();

$geoins_findings = array();
foreach ( array(
	'ERROR'   => $geoins_result->get_errors(),
	'WARNING' => $geoins_result->get_warnings(),
) as $geoins_type => $geoins_files ) {
	foreach ( $geoins_files as $geoins_file => $geoins_lines ) {
		foreach ( $geoins_lines as $geoins_line => $geoins_columns ) {
			foreach ( $geoins_columns as $geoins_messages ) {
				foreach ( $geoins_messages as $geoins_message ) {
					$geoins_findings[] = array(
						'type'     => $geoins_type,
						'file'     => $geoins_file,
						'line'     => $geoins_line,
						'code'     => $geoins_message['code'],
						'message'  => wp_strip_all_tags( $geoins_message['message'] ),
						'severity' => $geoins_message['severity'] ?? 5,
					);
				}
			}
		}
	}
}

file_put_contents(
	'/e2e-out/plugin-check.json',
	wp_json_encode(
		array(
			'checks'   => $geoins_checks,
			'findings' => $geoins_findings,
		),
		JSON_PRETTY_PRINT
	)
);
