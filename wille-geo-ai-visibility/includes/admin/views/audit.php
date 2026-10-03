<?php
/**
 * GEO audit view: the React app (same bundle as the dashboard) renders
 * the sortable site-wide score table into #geoins-audit-root.
 *
 * @package Wille_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap geoins-wrap" id="geoins-audit">
	<?php GEOINS_Admin::header( 'wille-geo-audit' ); ?>
	<h1><?php esc_html_e( 'GEO Audit', 'wille-geo-ai-visibility' ); ?></h1>
	<p class="geoins-intro">
		<?php esc_html_e( 'Every published post and page against the 13 GEO checks – sorted so the biggest citation opportunities surface first, next to the AI interest each page already gets.', 'wille-geo-ai-visibility' ); ?>
	</p>

	<div id="geoins-audit-root">
		<noscript><p><?php esc_html_e( 'The audit table needs JavaScript.', 'wille-geo-ai-visibility' ); ?></p></noscript>
	</div>
</div>
