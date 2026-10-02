<?php
/**
 * Dashboard view: server renders the headline, the React app
 * (assets/js/geoins-dashboard.js, source in src/dashboard/) renders the
 * charts and tables into #geoins-dashboard-root.
 *
 * @package GEO_Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap geoins-wrap" id="geoins-dashboard">
	<?php GEOINS_Admin::header( 'geo-insights' ); ?>
	<h1><?php esc_html_e( 'AI Statistics', 'geo-insights-ai' ); ?></h1>
	<p class="geoins-intro">
		<?php esc_html_e( 'Which AI reads which of your pages, for which term – and which AI answers actually send you human visitors.', 'geo-insights-ai' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=geo-insights-learn' ) ); ?>"><?php esc_html_e( 'New here? How it all works, in plain language.', 'geo-insights-ai' ); ?></a>
	</p>

	<div id="geoins-dashboard-root">
		<noscript><p><?php esc_html_e( 'The statistics dashboard needs JavaScript. Use the CSV export or WP-CLI (wp geoins stats) instead.', 'geo-insights-ai' ); ?></p></noscript>
	</div>
</div>
