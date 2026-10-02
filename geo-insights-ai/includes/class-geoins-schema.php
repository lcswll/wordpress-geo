<?php
/**
 * Structured data (JSON-LD).
 *
 * Emits WebSite, Organization/Person, Article/WebPage, BreadcrumbList
 * and (optionally) an auto-detected FAQPage built from question headings.
 * Automatically stays silent when a full SEO plugin (Yoast, Rank Math,
 * AIOSEO, SEOPress, The SEO Framework) is active, to avoid duplicates.
 *
 * @package GEO_Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema module.
 */
class GEOINS_Schema {

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 5 );

		// E-E-A-T author profile fields (job title + sameAs profiles).
		if ( is_admin() ) {
			add_action( 'show_user_profile', array( __CLASS__, 'profile_fields' ) );
			add_action( 'edit_user_profile', array( __CLASS__, 'profile_fields' ) );
			add_action( 'personal_options_update', array( __CLASS__, 'save_profile_fields' ) );
			add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile_fields' ) );
		}
	}

	/**
	 * Whether a user qualifies for author E-E-A-T markup: they can publish
	 * or have published. Keeps subscriber/customer accounts (which can edit
	 * their own profile) from feeding public structured data – that would
	 * be an SEO-spam vector on open-registration sites.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	protected static function is_author_user( $user_id ) {
		return user_can( $user_id, 'publish_posts' ) || (int) count_user_posts( $user_id, 'post' ) > 0;
	}

	/**
	 * Author E-E-A-T fields on the user profile screen.
	 *
	 * @param WP_User $user User being edited.
	 * @return void
	 */
	public static function profile_fields( $user ) {
		if ( ! self::is_author_user( $user->ID ) ) {
			return;
		}
		?>
		<h3><?php esc_html_e( 'GEO Insights: author signals (E-E-A-T)', 'geo-insights-ai' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="geoins_job_title"><?php esc_html_e( 'Job title / role', 'geo-insights-ai' ); ?></label></th>
				<td>
					<input type="text" id="geoins_job_title" name="geoins_job_title" class="regular-text" value="<?php echo esc_attr( get_user_meta( $user->ID, 'geoins_job_title', true ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Shown to AI systems as machine-readable expertise (schema.org jobTitle), e.g. "Senior WordPress Developer".', 'geo-insights-ai' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="geoins_sameas"><?php esc_html_e( 'Profile URLs (sameAs)', 'geo-insights-ai' ); ?></label></th>
				<td>
					<textarea id="geoins_sameas" name="geoins_sameas" rows="3" class="regular-text"><?php echo esc_textarea( get_user_meta( $user->ID, 'geoins_sameas', true ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One URL per line (LinkedIn, GitHub, X, personal site …). Lets AI systems verify this author as one consistent, real person – a core E-E-A-T signal.', 'geo-insights-ai' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save the author E-E-A-T fields (WordPress verifies the profile nonce
	 * before these hooks fire).
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function save_profile_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) || ! self::is_author_user( $user_id ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Core checks update-user_{id} before firing these hooks.
		if ( isset( $_POST['geoins_job_title'] ) ) {
			$job = sanitize_text_field( wp_unslash( $_POST['geoins_job_title'] ) );
			update_user_meta( $user_id, 'geoins_job_title', mb_substr( $job, 0, 100 ) );
		}
		if ( isset( $_POST['geoins_sameas'] ) ) {
			$urls  = array();
			$lines = preg_split( '/\r\n|\r|\n/', sanitize_textarea_field( wp_unslash( $_POST['geoins_sameas'] ) ) );
			foreach ( is_array( $lines ) ? $lines : array() as $url ) {
				$url = esc_url_raw( trim( $url ) );
				if ( '' !== $url && strlen( $url ) <= 250 ) {
					$urls[] = $url;
				}
			}
			update_user_meta( $user_id, 'geoins_sameas', implode( "\n", array_slice( $urls, 0, 10 ) ) );
		}
		// phpcs:enable
	}

	/**
	 * Person node for an author, enriched with the E-E-A-T profile fields.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,mixed>
	 */
	protected static function author_person( $user_id ) {
		$person = array(
			'@type' => 'Person',
			'name'  => get_the_author_meta( 'display_name', $user_id ),
			'url'   => get_author_posts_url( $user_id ),
		);

		$bio = trim( (string) get_the_author_meta( 'description', $user_id ) );
		if ( '' !== $bio ) {
			$person['description'] = $bio;
		}
		$job = trim( (string) get_user_meta( $user_id, 'geoins_job_title', true ) );
		if ( '' !== $job ) {
			$person['jobTitle'] = $job;
		}
		$same_as = array();
		$lines   = preg_split( '/\r\n|\r|\n/', (string) get_user_meta( $user_id, 'geoins_sameas', true ) );
		foreach ( is_array( $lines ) ? $lines : array() as $url ) {
			$url = trim( $url );
			if ( '' !== $url ) {
				$same_as[] = esc_url_raw( $url );
			}
		}
		if ( $same_as ) {
			$person['sameAs'] = $same_as;
		}

		return $person;
	}

	/**
	 * Print the JSON-LD graph.
	 *
	 * @return void
	 */
	public static function output() {
		$settings = geoins()->settings();
		if ( empty( $settings['schema'] ) || geoins()->seo_plugin_active() ) {
			return;
		}
		if ( is_admin() || is_feed() || is_robots() ) {
			return;
		}

		$graph = array();

		$graph[] = self::website();
		$graph[] = self::publisher();

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$graph[] = self::singular( $post );
				if ( ! is_front_page() ) {
					$graph[] = self::breadcrumb( $post );
				}
				if ( ! empty( $settings['faq_schema'] ) ) {
					$faq = self::faq_from_content( $post );
					if ( null !== $faq ) {
						$graph[] = $faq;
					}
				}
			}
		} elseif ( is_author() ) {
			// ProfilePage (Google-documented type): lets engines and AI
			// systems verify the author as a real, consistent person. Only
			// for actual authors – subscriber archives get no markup.
			$user = get_queried_object();
			if ( $user instanceof WP_User && self::is_author_user( $user->ID ) ) {
				$graph[] = array(
					'@type'      => 'ProfilePage',
					'@id'        => get_author_posts_url( $user->ID ) . '#profilepage',
					'url'        => get_author_posts_url( $user->ID ),
					'name'       => $user->display_name,
					'isPartOf'   => array( '@id' => home_url( '/#website' ) ),
					'mainEntity' => self::author_person( $user->ID ),
				);
			}
		}

		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( array_filter( $graph ) ),
		);

		/**
		 * Filters the JSON-LD graph before output.
		 *
		 * @param array $data Schema.org data.
		 */
		$data = apply_filters( 'geoins_schema_graph', $data );

		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	/**
	 * WebSite node with SearchAction.
	 *
	 * @return array<string,mixed>
	 */
	protected static function website() {
		return array(
			'@type'           => 'WebSite',
			'@id'             => home_url( '/#website' ),
			'url'             => home_url( '/' ),
			'name'            => get_bloginfo( 'name' ),
			'description'     => get_bloginfo( 'description' ),
			'publisher'       => array( '@id' => home_url( '/#publisher' ) ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
			'inLanguage'      => get_bloginfo( 'language' ),
		);
	}

	/**
	 * Organization or Person node.
	 *
	 * @return array<string,mixed>
	 */
	protected static function publisher() {
		$settings = geoins()->settings();
		$name     = ! empty( $settings['schema_name'] ) ? $settings['schema_name'] : get_bloginfo( 'name' );

		$node = array(
			'@type' => ( 'person' === $settings['schema_entity'] ) ? 'Person' : 'Organization',
			'@id'   => home_url( '/#publisher' ),
			'name'  => $name,
			'url'   => home_url( '/' ),
		);

		$icon_id = get_option( 'site_icon' );
		if ( $icon_id ) {
			$icon = wp_get_attachment_image_url( $icon_id, 'full' );
			if ( $icon ) {
				$node['logo'] = array(
					'@type' => 'ImageObject',
					'url'   => $icon,
				);
			}
		}

		// sameAs: social/company profiles strengthen entity recognition (E-E-A-T).
		if ( ! empty( $settings['schema_sameas'] ) ) {
			$same_as = array();
			$lines   = preg_split( '/\r\n|\r|\n/', $settings['schema_sameas'] );
			foreach ( is_array( $lines ) ? $lines : array() as $url ) {
				$url = trim( $url );
				if ( '' !== $url ) {
					$same_as[] = esc_url_raw( $url );
				}
			}
			if ( $same_as ) {
				$node['sameAs'] = $same_as;
			}
		}

		return $node;
	}

	/**
	 * Article / WebPage node for singular views.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string,mixed>
	 */
	protected static function singular( $post ) {
		$is_post = ( 'post' === $post->post_type );

		$node = array(
			'@type'            => $is_post ? 'Article' : 'WebPage',
			'@id'              => get_permalink( $post ) . '#main',
			'url'              => get_permalink( $post ),
			'headline'         => get_the_title( $post ),
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'mainEntityOfPage' => get_permalink( $post ),
			'isPartOf'         => array( '@id' => home_url( '/#website' ) ),
			'inLanguage'       => get_bloginfo( 'language' ),
		);

		$author = get_the_author_meta( 'display_name', (int) $post->post_author );
		if ( $author ) {
			$node['author'] = self::author_person( (int) $post->post_author );
		}

		if ( $is_post ) {
			$node['publisher'] = array( '@id' => home_url( '/#publisher' ) );
			$node['wordCount'] = GEOINS_Analysis::count_words( wp_strip_all_tags( $post->post_content ) );

			// keywords: focus term + tags help retrieval systems map the topic.
			$keywords = array();
			$focus    = (string) get_post_meta( $post->ID, GEOINS_Analysis::META_KEY, true );
			if ( '' !== $focus ) {
				$keywords[] = $focus;
			}
			$tags = get_the_tags( $post->ID );
			if ( is_array( $tags ) ) {
				foreach ( $tags as $tag ) {
					$keywords[] = $tag->name;
				}
			}
			if ( $keywords ) {
				$node['keywords'] = implode( ', ', array_slice( array_unique( $keywords ), 0, 10 ) );
			}
		}

		$thumb = get_the_post_thumbnail_url( $post, 'full' );
		if ( $thumb ) {
			$node['image'] = $thumb;
		}

		$excerpt = get_the_excerpt( $post );
		if ( $excerpt ) {
			$node['description'] = wp_strip_all_tags( $excerpt );
		}

		return $node;
	}

	/**
	 * Simple breadcrumb: Home (> category) > title.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string,mixed>
	 */
	protected static function breadcrumb( $post ) {
		$items    = array();
		$position = 1;

		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => __( 'Home', 'geo-insights-ai' ),
			'item'     => home_url( '/' ),
		);

		if ( 'post' === $post->post_type ) {
			$cats = get_the_category( $post->ID );
			if ( ! empty( $cats ) ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => $cats[0]->name,
					'item'     => get_category_link( $cats[0] ),
				);
			}
		}

		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $position,
			'name'     => get_the_title( $post ),
			'item'     => get_permalink( $post ),
		);

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => get_permalink( $post ) . '#breadcrumb',
			'itemListElement' => $items,
		);
	}

	/**
	 * Detect question headings (H2/H3 ending in "?") followed by content
	 * and build an FAQPage node. Needs at least two Q&A pairs.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string,mixed>|null
	 */
	public static function faq_from_content( $post ) {
		$html = $post->post_content;
		if ( function_exists( 'do_blocks' ) ) {
			$html = do_blocks( $html );
		}
		$html = strip_shortcodes( $html );

		if ( ! preg_match_all( '/<h([23])[^>]*>(.*?\?)\s*<\/h\1>(.*?)(?=<h[23][^>]*>|$)/is', $html, $matches, PREG_SET_ORDER ) ) {
			return null;
		}

		$questions = array();
		foreach ( $matches as $m ) {
			$question = trim( wp_strip_all_tags( $m[2] ) );
			$answer   = trim( wp_strip_all_tags( $m[3] ) );
			if ( '' === $question || strlen( $answer ) < 20 ) {
				continue;
			}
			if ( function_exists( 'mb_substr' ) ) {
				$answer = mb_substr( $answer, 0, 600 );
			} else {
				$answer = substr( $answer, 0, 600 );
			}
			$questions[] = array(
				'@type'          => 'Question',
				'name'           => $question,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer,
				),
			);
		}

		if ( count( $questions ) < 2 ) {
			return null;
		}

		return array(
			'@type'      => 'FAQPage',
			'@id'        => get_permalink( $post ) . '#faq',
			'mainEntity' => $questions,
		);
	}
}
