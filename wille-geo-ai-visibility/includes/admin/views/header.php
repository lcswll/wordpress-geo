<?php
/**
 * Brand bar shown on top of every Wille GEO screen: logo, navigation between the plugin's pages, author credit.
 *
 * Rendered by GEOINS_Admin::header(), which records the current page.
 *
 * @package Wille_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$geoins_current = GEOINS_Admin::current_page();
$geoins_tabs    = array(
	'wille-geo'          => array( __( 'AI Statistics', 'wille-geo-ai-visibility' ), 'manage_options' ),
	'wille-geo-audit'    => array( __( 'GEO Audit', 'wille-geo-ai-visibility' ), 'edit_others_posts' ),
	'wille-geo-settings' => array( __( 'Settings', 'wille-geo-ai-visibility' ), 'manage_options' ),
	'wille-geo-learn'    => array( __( 'How it works', 'wille-geo-ai-visibility' ), 'edit_posts' ),
);
?>
<header class="geoins-brandbar">
	<a class="geoins-brand" href="<?php echo esc_url( admin_url( 'admin.php?page=wille-geo' ) ); ?>">
		<svg class="geoins-logo" viewBox="0 0 100 100" aria-hidden="true" focusable="false">
			<circle cx="50" cy="50" r="44" fill="none" stroke="#fff" stroke-opacity="0.9" stroke-width="6"/>
			<circle cx="50" cy="50" r="27" fill="none" stroke="#fff" stroke-opacity="0.45" stroke-width="5"/>
			<g class="geoins-logo-sweep">
				<path d="M50 50 L50 6 A44 44 0 0 1 88.1 28 Z" fill="#fff" fill-opacity="0.28"/>
				<line x1="50" y1="50" x2="88.1" y2="28" stroke="#fff" stroke-width="6" stroke-linecap="round"/>
			</g>
			<circle cx="71" cy="21" r="8" fill="#4f9fe8" stroke="#fff" stroke-width="3.5"/>
			<circle cx="26" cy="38" r="7" fill="#22c55e" stroke="#fff" stroke-width="3.5"/>
			<circle cx="62" cy="75" r="6.5" fill="#f5b82e" stroke="#fff" stroke-width="3.5"/>
			<circle cx="50" cy="50" r="7" fill="#fff"/>
		</svg>
		<span class="geoins-brand-name"><?php esc_html_e( 'Wille GEO', 'wille-geo-ai-visibility' ); ?></span>
		<span class="geoins-brand-version"><?php echo esc_html( GEOINS_VERSION ); ?></span>
	</a>
	<nav class="geoins-tabs" aria-label="<?php esc_attr_e( 'Wille GEO', 'wille-geo-ai-visibility' ); ?>">
		<?php foreach ( $geoins_tabs as $geoins_slug => $geoins_tab ) : ?>
			<?php
			if ( ! current_user_can( $geoins_tab[1] ) ) {
				continue;
			}
			?>
			<a class="geoins-tab<?php echo $geoins_slug === $geoins_current ? ' is-current' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $geoins_slug ) ); ?>"<?php echo $geoins_slug === $geoins_current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $geoins_tab[0] ); ?></a>
		<?php endforeach; ?>
	</nav>
	<a class="geoins-byline" href="<?php echo esc_url( GEOINS_Admin::AUTHOR_URL ); ?>" target="_blank" rel="noopener">
		<?php
		/* translators: %s: author name */
		echo esc_html( sprintf( __( 'by %s', 'wille-geo-ai-visibility' ), GEOINS_Admin::AUTHOR ) );
		?>
		<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'wille-geo-ai-visibility' ); ?></span>
	</a>
</header>
