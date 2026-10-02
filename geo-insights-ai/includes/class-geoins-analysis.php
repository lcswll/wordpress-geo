<?php
/**
 * GEO content analysis: per-post checklist + focus keyword.
 *
 * The focus keyword ("Begriff") is what connects AI bot hits to topics
 * in the statistics dashboard. Every check explains its direct benefit.
 * In the block editor the checks run live (assets/js/geoins-editor.js)
 * against the same definitions; the classic editor gets a meta box.
 *
 * @package GEO_Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analysis module.
 */
class GEOINS_Analysis {

	const META_KEY = '_geoins_keyword';
	const PIN_KEY  = '_geoins_llms_pin';

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );

		if ( ! is_admin() ) {
			return;
		}
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_keyword' ) );
		add_action( 'wp_ajax_geoins_analyze', array( __CLASS__, 'ajax_analyze' ) );
	}

	/**
	 * Expose the focus keyword to the block editor via REST.
	 */
	public static function register_meta() {
		$auth = static function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};
		foreach ( array( 'post', 'page' ) as $type ) {
			register_post_meta(
				$type,
				self::META_KEY,
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => $auth,
				)
			);
			register_post_meta(
				$type,
				self::PIN_KEY,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'boolean',
					'default'       => false,
					'auth_callback' => $auth,
				)
			);
		}
	}

	/**
	 * All check definitions: id => label + benefit.
	 *
	 * Shared between the server-side analysis and the live editor panel.
	 *
	 * @return array<string,array>
	 */
	public static function definitions() {
		return array(
			'keyword_set'       => array(
				'label'   => __( 'Focus term set', 'geo-insights-ai' ),
				'benefit' => __( 'Connects AI accesses to a topic in your statistics and focuses your writing on one clear question.', 'geo-insights-ai' ),
			),
			'keyword_in_title'  => array(
				'label'   => __( 'Focus term appears in the title', 'geo-insights-ai' ),
				'benefit' => __( 'AI systems match questions against titles first – a clear title makes your page the obvious source.', 'geo-insights-ai' ),
			),
			'answer_first'      => array(
				'label'   => __( 'Direct answer in the first paragraph (20–120 words)', 'geo-insights-ai' ),
				'benefit' => __( 'AI assistants quote self-contained answer blocks. If the first paragraph answers the core question, it is the easiest thing to cite.', 'geo-insights-ai' ),
			),
			'question_headings' => array(
				'label'   => __( 'At least one heading phrased as a question', 'geo-insights-ai' ),
				'benefit' => __( 'People ask AI assistants questions. Headings that mirror those questions map your content directly onto real prompts – and feed the automatic FAQ schema.', 'geo-insights-ai' ),
			),
			'lists_tables'      => array(
				'label'   => __( 'Contains a list or table', 'geo-insights-ai' ),
				'benefit' => __( 'Structured chunks (lists, tables) are extracted and quoted by AI far more often than wall-of-text prose.', 'geo-insights-ai' ),
			),
			'short_paragraphs'  => array(
				'label'   => __( 'No overlong paragraphs (max. 150 words each)', 'geo-insights-ai' ),
				'benefit' => __( 'Short, self-contained paragraphs are the unit AI systems lift into answers. Long blocks get skipped.', 'geo-insights-ai' ),
			),
			'numbers'           => array(
				'label'   => __( 'Contains concrete numbers or data', 'geo-insights-ai' ),
				'benefit' => __( 'Statistics and concrete figures measurably increase the chance of being cited – AI answers love verifiable numbers.', 'geo-insights-ai' ),
			),
			'external_links'    => array(
				'label'   => __( 'Links to at least one external source', 'geo-insights-ai' ),
				'benefit' => __( 'Citing sources signals trustworthiness – a core ranking factor for AI retrieval systems.', 'geo-insights-ai' ),
			),
			'internal_links'    => array(
				'label'   => __( 'Links to at least one other page on your site', 'geo-insights-ai' ),
				'benefit' => __( 'Internal links are the paths crawlers and retrieval systems follow to discover your related content – orphan pages get read less.', 'geo-insights-ai' ),
			),
			'image_alt'         => array(
				'label'   => __( 'All images have alt text', 'geo-insights-ai' ),
				'benefit' => __( 'Alt text is how AI systems understand your images – and a long-standing accessibility and ranking signal. Pages without images pass automatically.', 'geo-insights-ai' ),
			),
			'word_count'        => array(
				'label'   => __( 'At least 300 words', 'geo-insights-ai' ),
				'benefit' => __( 'Very thin pages rarely contain enough substance for an AI to ground an answer on.', 'geo-insights-ai' ),
			),
			'freshness'         => array(
				'label'   => __( 'Updated within the last 12 months', 'geo-insights-ai' ),
				'benefit' => __( 'AI search prefers fresh sources; the modification date is sent in your structured data. Reviewing content yearly keeps you citable.', 'geo-insights-ai' ),
			),
			'author_bio'        => array(
				'label'   => __( 'Author has a profile description', 'geo-insights-ai' ),
				'benefit' => __( 'Visible expertise (a real author with a bio) feeds the E-E-A-T signals that AI systems use to pick trustworthy sources.', 'geo-insights-ai' ),
			),
		);
	}

	/**
	 * Register the GEO check meta box (classic editor only – the block
	 * editor gets a live sidebar panel instead).
	 */
	public static function add_meta_box() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return;
		}
		foreach ( array( 'post', 'page' ) as $type ) {
			add_meta_box(
				'geoins-analysis',
				__( 'GEO check (AI visibility)', 'geo-insights-ai' ),
				array( __CLASS__, 'render_meta_box' ),
				$type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the meta box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( 'geoins_keyword_save', 'geoins_keyword_nonce' );
		$keyword = get_post_meta( $post->ID, self::META_KEY, true );
		?>
		<p>
			<label for="geoins-keyword"><strong><?php esc_html_e( 'Focus term', 'geo-insights-ai' ); ?></strong></label>
			<input type="text" id="geoins-keyword" name="geoins_keyword" class="widefat" value="<?php echo esc_attr( $keyword ); ?>" placeholder="<?php esc_attr_e( 'e.g. wordpress backup plugin', 'geo-insights-ai' ); ?>" />
		</p>
		<p class="description"><?php esc_html_e( 'Benefit: the statistics dashboard maps every AI access to this term, so you can see which AI reads your content for which topic. Without it, the post title is used.', 'geo-insights-ai' ); ?></p>
		<p>
			<label>
				<input type="checkbox" name="geoins_llms_pin" value="1" <?php checked( (bool) get_post_meta( $post->ID, self::PIN_KEY, true ) ); ?> />
				<?php esc_html_e( 'Feature in llms.txt (key content)', 'geo-insights-ai' ); ?>
			</label>
		</p>
		<div id="geoins-analysis-results" data-post="<?php echo esc_attr( (string) $post->ID ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'geoins_analyze' ) ); ?>">
			<p class="geoins-loading"><?php esc_html_e( 'Running GEO checks …', 'geo-insights-ai' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Save the focus keyword (classic editor form field).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save_keyword( $post_id ) {
		if ( ! isset( $_POST['geoins_keyword_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['geoins_keyword_nonce'] ), 'geoins_keyword_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['geoins_keyword'] ) ) {
			$keyword = sanitize_text_field( wp_unslash( $_POST['geoins_keyword'] ) );
			if ( '' === $keyword ) {
				delete_post_meta( $post_id, self::META_KEY );
			} else {
				update_post_meta( $post_id, self::META_KEY, $keyword );
			}
		}
		if ( empty( $_POST['geoins_llms_pin'] ) ) {
			delete_post_meta( $post_id, self::PIN_KEY );
		} else {
			update_post_meta( $post_id, self::PIN_KEY, 1 );
		}
	}

	/**
	 * AJAX: run checks for one post.
	 */
	public static function ajax_analyze() {
		check_ajax_referer( 'geoins_analyze', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'geo-insights-ai' ) ), 403 );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'Post not found.', 'geo-insights-ai' ) ), 404 );
		}

		wp_send_json_success( self::analyze( $post ) );
	}

	/**
	 * Effective keyword for a post (focus term or title).
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function keyword( $post_id ) {
		$keyword = (string) get_post_meta( $post_id, self::META_KEY, true );
		if ( '' === $keyword ) {
			$keyword = (string) get_the_title( $post_id );
		}
		return $keyword;
	}

	/**
	 * Run all GEO checks.
	 *
	 * @param WP_Post $post Post.
	 * @return array { score, total, checks: [ {id,label,pass,benefit} ] }
	 */
	public static function analyze( $post ) {
		$content_html = function_exists( 'do_blocks' ) ? do_blocks( $post->post_content ) : $post->post_content;
		$content_html = strip_shortcodes( $content_html );
		// Drop surviving plain HTML comments (do_blocks only consumes block
		// delimiters): commented-out markup must not count as real content.
		$content_html = preg_replace( '/<!--.*?-->/s', '', $content_html );
		$text         = wp_strip_all_tags( $content_html );
		$word_count   = self::count_words( $text );
		$keyword_raw  = (string) get_post_meta( $post->ID, self::META_KEY, true );
		$keyword      = '' !== $keyword_raw ? $keyword_raw : (string) get_the_title( $post );

		// Paragraphs.
		preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $content_html, $p_matches );
		$paragraphs = array();
		foreach ( $p_matches[1] as $p ) {
			$p_text = trim( wp_strip_all_tags( $p ) );
			if ( '' !== $p_text ) {
				$paragraphs[] = $p_text;
			}
		}
		if ( empty( $paragraphs ) && '' !== $text ) {
			$paragraphs = preg_split( '/\n{2,}/', $text );
		}
		$first_para       = isset( $paragraphs[0] ) ? $paragraphs[0] : '';
		$first_para_words = self::count_words( $first_para );
		$long_paragraphs  = 0;
		foreach ( $paragraphs as $p ) {
			if ( self::count_words( $p ) > 150 ) {
				$long_paragraphs++;
			}
		}

		// Headings.
		preg_match_all( '/<h[23][^>]*>(.*?)<\/h[23]>/is', $content_html, $h_matches );
		$question_headings = 0;
		foreach ( $h_matches[1] as $h ) {
			if ( str_ends_with( trim( wp_strip_all_tags( $h ) ), '?' ) ) {
				$question_headings++;
			}
		}

		$has_list_or_table = (bool) preg_match( '/<(ul|ol|table)[\s>]/i', $content_html );
		$number_groups     = preg_match_all( '/\d[\d.,%]*/', $text );

		// External + internal links. Hosts are punycode-normalized so IDN
		// sites (müller.de vs xn--mller-kva.de) classify consistently with
		// the editor-side check.
		$site_host      = self::ascii_host( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$external_links = 0;
		$internal_links = 0;
		if ( preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\']/i', $content_html, $a_matches ) ) {
			foreach ( $a_matches[1] as $href ) {
				if ( preg_match( '#^https?://#i', $href ) ) {
					$href_host = self::ascii_host( (string) wp_parse_url( $href, PHP_URL_HOST ) );
					if ( '' === $href_host ) {
						continue;
					}
					if ( $href_host !== $site_host ) {
						$external_links++;
					} else {
						$internal_links++;
					}
				} elseif ( str_starts_with( $href, '/' ) && ! str_starts_with( $href, '//' ) ) {
					$internal_links++; // Relative link on the own site.
				}
			}
		}

		// Images without alt text.
		$images_total   = 0;
		$images_missing = 0;
		if ( preg_match_all( '/<img\b[^>]*>/i', $content_html, $img_matches ) ) {
			foreach ( $img_matches[0] as $img_tag ) {
				$images_total++;
				if ( ! preg_match( '/\balt\s*=\s*["\'][^"\']*\S[^"\']*["\']/i', $img_tag ) ) {
					$images_missing++;
				}
			}
		}

		$modified_age_days = ( time() - get_post_modified_time( 'U', true, $post ) ) / DAY_IN_SECONDS;
		$author_bio        = '' !== trim( (string) get_the_author_meta( 'description', (int) $post->post_author ) );
		$kw                = self::normalize( $keyword );

		$results = array(
			'keyword_set'       => '' !== $keyword_raw,
			'keyword_in_title'  => '' !== $kw && false !== stripos( self::normalize( get_the_title( $post ) ), $kw ),
			'answer_first'      => $first_para_words >= 20 && $first_para_words <= 120,
			'question_headings' => $question_headings > 0,
			'lists_tables'      => $has_list_or_table,
			'short_paragraphs'  => 0 === $long_paragraphs && ! empty( $paragraphs ),
			'numbers'           => $number_groups >= 3,
			'external_links'    => $external_links > 0,
			'internal_links'    => $internal_links > 0,
			'image_alt'         => 0 === $images_missing,
			'word_count'        => $word_count >= 300,
			'freshness'         => $modified_age_days <= 365,
			'author_bio'        => $author_bio,
		);

		$checks = array();
		$score  = 0;
		foreach ( self::definitions() as $id => $definition ) {
			$pass = ! empty( $results[ $id ] );
			if ( $pass ) {
				$score++;
			}
			$checks[] = array(
				'id'      => $id,
				'label'   => $definition['label'],
				'pass'    => $pass,
				'benefit' => $definition['benefit'],
			);
		}

		return array(
			'score'   => $score,
			'total'   => count( $checks ),
			'keyword' => $keyword,
			'checks'  => $checks,
		);
	}

	/**
	 * Unicode-aware word count.
	 *
	 * str_word_count() is ASCII-based and miscounts anything with umlauts
	 * or accents ("größer" counts as two words). This counts sequences of
	 * letters/digits, allowing inner hyphens and apostrophes.
	 *
	 * @param string $text Plain text.
	 * @return int
	 */
	public static function count_words( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return 0;
		}
		$count = preg_match_all( '/[\p{L}\p{N}]+(?:[\'\x{2019}\x{2010}-][\p{L}\p{N}]+)*/u', $text );
		if ( false === $count ) {
			return str_word_count( $text ); // Invalid UTF-8 fallback.
		}
		return (int) $count;
	}

	/**
	 * Lowercased host in punycode form (IDN-safe comparisons).
	 *
	 * @param string $host Hostname.
	 * @return string
	 */
	protected static function ascii_host( $host ) {
		$host = strtolower( (string) $host );
		if ( '' !== $host && preg_match( '/[^\x00-\x7F]/', $host ) && function_exists( 'idn_to_ascii' ) ) {
			$ascii = idn_to_ascii( $host );
			if ( false !== $ascii && null !== $ascii ) {
				return $ascii;
			}
		}
		return $host;
	}

	/**
	 * Lowercase, whitespace-normalized comparison string.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected static function normalize( $value ) {
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
		return trim( preg_replace( '/\s+/', ' ', $value ) );
	}
}
