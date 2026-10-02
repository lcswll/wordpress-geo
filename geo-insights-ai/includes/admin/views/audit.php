<?php
/**
 * GEO audit view: the React app (same bundle as the dashboard) renders
 * the sortable site-wide score table into #geoins-audit-root.
 *
 * @package GEO_Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap geoins-wrap" id="geoins-audit">
	<?php GEOINS_Admin::header( 'geo-insights-audit' ); ?>
	<h1><?php esc_html_e( 'GEO Audit', 'geo-insights-ai' ); ?></h1>
	<p class="geoins-intro">
		<?php esc_html_e( 'Every published post and page against the 13 GEO checks – sorted so the biggest citation opportunities surface first, next to the AI interest each page already gets.', 'geo-insights-ai' ); ?>
	</p>

	<div id="geoins-audit-root">
		<noscript><p><?php esc_html_e( 'The audit table needs JavaScript.', 'geo-insights-ai' ); ?></p></noscript>
	</div>
</div>
