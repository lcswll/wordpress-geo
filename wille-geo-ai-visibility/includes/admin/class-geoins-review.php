<?php
/**
 * Review request: a quiet card on the plugin's own screens, shown only once the plugin demonstrably works
 * on this site (in use for two weeks and AI accesses recorded). One click hides it for 30 days or for good.
 *
 * @package Wille_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Review request module.
 */
class GEOINS_Review {

	/**
	 * Option holding the visitor's choice: array{state:string,until:int}.
	 */
	const OPTION = 'geoins_review';

	/**
	 * Minimum time the plugin has been active before asking.
	 */
	const MIN_AGE = 14 * DAY_IN_SECONDS;

	/**
	 * Minimum AI bot accesses in the last 30 days before asking ("it works").
	 */
	const MIN_HITS = 50;

	/**
	 * How long "Maybe later" hides the request.
	 */
	const SNOOZE = 30 * DAY_IN_SECONDS;

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_geoins_review', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Decide whether to ask (pure, unit-tested).
	 *
	 * @param array<string,mixed> $choice       Stored choice (state: ''|'later'|'done', until: unix time).
	 * @param int                 $now          Current unix time.
	 * @param int                 $activated_at Unix time of the first activation (0 = unknown).
	 * @param int                 $bot_hits     AI bot accesses in the last 30 days.
	 * @return bool
	 */
	public static function should_ask( $choice, $now, $activated_at, $bot_hits ) {
		$state = isset( $choice['state'] ) ? (string) $choice['state'] : '';
		if ( 'done' === $state ) {
			return false;
		}
		if ( 'later' === $state && $now < (int) ( $choice['until'] ?? 0 ) ) {
			return false;
		}
		if ( $activated_at <= 0 || $now - $activated_at < self::MIN_AGE ) {
			return false;
		}
		return $bot_hits >= self::MIN_HITS;
	}

	/**
	 * Stored choice.
	 *
	 * @return array<string,mixed>
	 */
	protected static function choice() {
		$choice = get_option( self::OPTION, array() );
		return is_array( $choice ) ? $choice : array();
	}

	/**
	 * Print the card on the plugin's screens when due.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) || ! GEOINS_Admin::is_plugin_screen() ) {
			return;
		}
		$now          = time();
		$choice       = self::choice();
		$activated_at = (int) get_option( 'geoins_activated_at', 0 );
		// Cheap checks first; the stats query only runs when they all pass.
		if ( ! self::should_ask( $choice, $now, $activated_at, PHP_INT_MAX ) ) {
			return;
		}
		$hits = (int) GEOINS_Stats::totals( $now - 30 * DAY_IN_SECONDS, $now )['bots'];
		if ( ! self::should_ask( $choice, $now, $activated_at, $hits ) ) {
			return;
		}

		$link = static function ( $choice ) {
			return wp_nonce_url( admin_url( 'admin-post.php?action=geoins_review&choice=' . $choice ), 'geoins_review' );
		};
		?>
		<div class="notice geoins-review" role="region" aria-label="<?php esc_attr_e( 'Review Wille GEO', 'wille-geo-ai-visibility' ); ?>">
			<div class="geoins-review-stars" aria-hidden="true">★★★★★</div>
			<div class="geoins-review-text">
				<p class="geoins-review-title">
					<?php
					/* translators: %s: number of AI accesses */
					echo esc_html( sprintf( __( 'AI systems read your site %s times in the last 30 days – Wille GEO is doing its job.', 'wille-geo-ai-visibility' ), number_format_i18n( $hits ) ) );
					?>
				</p>
				<p><?php esc_html_e( 'If the plugin helps you, would you leave a short review on WordPress.org? It takes a minute and helps other site owners find it. Thank you!', 'wille-geo-ai-visibility' ); ?></p>
				<p class="geoins-review-actions">
					<a class="button button-primary" href="<?php echo esc_url( $link( 'rate' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Sure, write a review', 'wille-geo-ai-visibility' ); ?></a>
					<a class="button" href="<?php echo esc_url( $link( 'later' ) ); ?>"><?php esc_html_e( 'Maybe later', 'wille-geo-ai-visibility' ); ?></a>
					<a class="geoins-review-done" href="<?php echo esc_url( $link( 'done' ) ); ?>"><?php esc_html_e( 'I already did', 'wille-geo-ai-visibility' ); ?></a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Store the choice and send the user on (to wordpress.org or back to the page).
	 *
	 * @return void
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'wille-geo-ai-visibility' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'geoins_review' );

		$choice = isset( $_GET['choice'] ) ? sanitize_key( wp_unslash( $_GET['choice'] ) ) : '';
		if ( 'later' === $choice ) {
			update_option(
				self::OPTION,
				array(
					'state' => 'later',
					'until' => time() + self::SNOOZE,
				),
				false
			);
		} elseif ( in_array( $choice, array( 'rate', 'done' ), true ) ) {
			update_option(
				self::OPTION,
				array(
					'state' => 'done',
					'until' => 0,
				),
				false
			);
		}

		if ( 'rate' === $choice ) {
			add_filter(
				'allowed_redirect_hosts',
				static function ( $hosts ) {
					$hosts[] = 'wordpress.org';
					return $hosts;
				}
			);
			wp_safe_redirect( GEOINS_Admin::REVIEW_URL );
			exit;
		}

		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url( 'admin.php?page=wille-geo' ) );
		exit;
	}
}
