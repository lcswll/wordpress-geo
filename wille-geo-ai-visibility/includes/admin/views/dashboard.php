<?php
/**
 * Dashboard view: server renders the headline, the React app
 * (assets/js/geoins-dashboard.js, source in src/dashboard/) renders the
 * charts and tables into #geoins-dashboard-root.
 *
 * @package Wille_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap geoins-wrap" id="geoins-dashboard">
	<?php GEOINS_Admin::header( 'wille-geo' ); ?>
	<h1><?php esc_html_e( 'AI Statistics', 'wille-geo-ai-visibility' ); ?></h1>
	<p class="geoins-intro">
		<?php esc_html_e( 'Which AI reads which of your pages, for which term – and which AI answers actually send you human visitors.', 'wille-geo-ai-visibility' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wille-geo-learn' ) ); ?>"><?php esc_html_e( 'New here? How it all works, in plain language.', 'wille-geo-ai-visibility' ); ?></a>
	</p>

	<div id="geoins-dashboard-root">
		<noscript><p><?php esc_html_e( 'The statistics dashboard needs JavaScript. Use the CSV export or WP-CLI (wp geoins stats) instead.', 'wille-geo-ai-visibility' ); ?></p></noscript>
	</div>
</div>
