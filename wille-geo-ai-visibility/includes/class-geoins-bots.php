<?php
/**
 * Registry of known AI crawlers and AI referral sources.
 *
 * Categories follow the four jobs an AI bot can do:
 *  - agent:     fetches a page live because a human just asked an AI about it (highest intent).
 *  - retrieval: builds the index AI search engines cite from (decides IF you can be cited).
 *  - training:  bulk collection for model training (no citations, no visitors).
 *  - search:    classic search crawling that also feeds AI answers (e.g. Bing -> Copilot).
 *
 * @package Wille_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static registry for bots and referral sources.
 */
class GEOINS_Bots {

	const CAT_TRAINING  = 1;
	const CAT_RETRIEVAL = 2;
	const CAT_AGENT     = 3;
	const CAT_SEARCH    = 4;

	/**
	 * Known AI crawlers.
	 *
	 * 'ua' is the substring matched (case-insensitive) against the User-Agent header.
	 * 'token' is the robots.txt user-agent token. 'ua' === null means the entry is a
	 * robots.txt opt-out token only and never sends requests (Google-Extended, Applebot-Extended).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function bots() {
		$bots = array(
			// OpenAI.
			'chatgpt-user'         => array(
				'ua'       => 'ChatGPT-User',
				'token'    => 'ChatGPT-User',
				'label'    => 'ChatGPT-User',
				'company'  => 'OpenAI',
				'category' => self::CAT_AGENT,
			),
			'oai-searchbot'        => array(
				'ua'       => 'OAI-SearchBot',
				'token'    => 'OAI-SearchBot',
				'label'    => 'OAI-SearchBot',
				'company'  => 'OpenAI',
				'category' => self::CAT_RETRIEVAL,
			),
			'gptbot'               => array(
				'ua'       => 'GPTBot',
				'token'    => 'GPTBot',
				'label'    => 'GPTBot',
				'company'  => 'OpenAI',
				'category' => self::CAT_TRAINING,
			),
			// Anthropic.
			'claude-user'          => array(
				'ua'       => 'Claude-User',
				'token'    => 'Claude-User',
				'label'    => 'Claude-User',
				'company'  => 'Anthropic',
				'category' => self::CAT_AGENT,
			),
			'claude-searchbot'     => array(
				'ua'       => 'Claude-SearchBot',
				'token'    => 'Claude-SearchBot',
				'label'    => 'Claude-SearchBot',
				'company'  => 'Anthropic',
				'category' => self::CAT_RETRIEVAL,
			),
			'claudebot'            => array(
				'ua'       => 'ClaudeBot',
				'token'    => 'ClaudeBot',
				'label'    => 'ClaudeBot',
				'company'  => 'Anthropic',
				'category' => self::CAT_TRAINING,
			),
			// Perplexity.
			'perplexity-user'      => array(
				'ua'       => 'Perplexity-User',
				'token'    => 'Perplexity-User',
				'label'    => 'Perplexity-User',
				'company'  => 'Perplexity',
				'category' => self::CAT_AGENT,
			),
			'perplexitybot'        => array(
				'ua'       => 'PerplexityBot',
				'token'    => 'PerplexityBot',
				'label'    => 'PerplexityBot',
				'company'  => 'Perplexity',
				'category' => self::CAT_RETRIEVAL,
			),
			// Mistral.
			'mistralai-user'       => array(
				'ua'       => 'MistralAI-User',
				'token'    => 'MistralAI-User',
				'label'    => 'MistralAI-User',
				'company'  => 'Mistral AI',
				'category' => self::CAT_AGENT,
			),
			// DuckDuckGo.
			'duckassistbot'        => array(
				'ua'       => 'DuckAssistBot',
				'token'    => 'DuckAssistBot',
				'label'    => 'DuckAssistBot',
				'company'  => 'DuckDuckGo',
				'category' => self::CAT_RETRIEVAL,
			),
			// Apple (feeds Siri and Apple Intelligence answers).
			'applebot'             => array(
				'ua'       => 'Applebot',
				'token'    => 'Applebot',
				'label'    => 'Applebot',
				'company'  => 'Apple',
				'category' => self::CAT_SEARCH,
			),
			// You.com AI search.
			'youbot'               => array(
				'ua'       => 'YouBot',
				'token'    => 'YouBot',
				'label'    => 'YouBot',
				'company'  => 'You.com',
				'category' => self::CAT_SEARCH,
			),
			// Huawei Petal Search (feeds Petal AI answers).
			'petalbot'             => array(
				'ua'       => 'PetalBot',
				'token'    => 'PetalBot',
				'label'    => 'PetalBot',
				'company'  => 'Huawei',
				'category' => self::CAT_SEARCH,
			),
			// Microsoft (feeds Copilot and parts of ChatGPT search).
			'bingbot'              => array(
				'ua'       => 'bingbot',
				'token'    => 'Bingbot',
				'label'    => 'Bingbot',
				'company'  => 'Microsoft',
				'category' => self::CAT_SEARCH,
			),
			// Meta.
			'meta-externalagent'   => array(
				'ua'       => 'meta-externalagent',
				'token'    => 'Meta-ExternalAgent',
				'label'    => 'Meta-ExternalAgent',
				'company'  => 'Meta',
				'category' => self::CAT_TRAINING,
			),
			'meta-externalfetcher' => array(
				'ua'       => 'meta-externalfetcher',
				'token'    => 'Meta-ExternalFetcher',
				'label'    => 'Meta-ExternalFetcher',
				'company'  => 'Meta',
				'category' => self::CAT_AGENT,
			),
			'facebookbot'          => array(
				'ua'       => 'FacebookBot',
				'token'    => 'FacebookBot',
				'label'    => 'FacebookBot',
				'company'  => 'Meta',
				'category' => self::CAT_TRAINING,
			),
			// ByteDance.
			'bytespider'           => array(
				'ua'       => 'Bytespider',
				'token'    => 'Bytespider',
				'label'    => 'Bytespider',
				'company'  => 'ByteDance',
				'category' => self::CAT_TRAINING,
			),
			// Amazon.
			'amazonbot'            => array(
				'ua'       => 'Amazonbot',
				'token'    => 'Amazonbot',
				'label'    => 'Amazonbot',
				'company'  => 'Amazon',
				'category' => self::CAT_TRAINING,
			),
			// Common Crawl.
			'ccbot'                => array(
				'ua'       => 'CCBot',
				'token'    => 'CCBot',
				'label'    => 'CCBot',
				'company'  => 'Common Crawl',
				'category' => self::CAT_TRAINING,
			),
			// Allen Institute for AI.
			'ai2bot'               => array(
				'ua'       => 'AI2Bot',
				'token'    => 'AI2Bot',
				'label'    => 'AI2Bot',
				'company'  => 'Allen Institute for AI',
				'category' => self::CAT_TRAINING,
			),
			// Cohere.
			'cohere-training'      => array(
				'ua'       => 'cohere-training-data-crawler',
				'token'    => 'cohere-training-data-crawler',
				'label'    => 'Cohere Training Crawler',
				'company'  => 'Cohere',
				'category' => self::CAT_TRAINING,
			),
			// Diffbot (structured web data, sold for AI training).
			'diffbot'              => array(
				'ua'       => 'Diffbot',
				'token'    => 'Diffbot',
				'label'    => 'Diffbot',
				'company'  => 'Diffbot',
				'category' => self::CAT_TRAINING,
			),
			// Timpi decentralized index.
			'timpibot'             => array(
				'ua'       => 'Timpibot',
				'token'    => 'Timpibot',
				'label'    => 'Timpibot',
				'company'  => 'Timpi',
				'category' => self::CAT_TRAINING,
			),
			// Webz.io (data feeds used for AI training).
			'omgilibot'            => array(
				'ua'       => 'omgili',
				'token'    => 'omgilibot',
				'label'    => 'Omgilibot',
				'company'  => 'Webz.io',
				'category' => self::CAT_TRAINING,
			),
			// Google R&D crawler (also used for AI products).
			'googleother'          => array(
				'ua'       => 'GoogleOther',
				'token'    => 'GoogleOther',
				'label'    => 'GoogleOther',
				'company'  => 'Google',
				'category' => self::CAT_TRAINING,
			),
			// Robots.txt opt-out tokens only – these never send requests.
			'google-extended'      => array(
				'ua'       => null,
				'token'    => 'Google-Extended',
				'label'    => 'Google-Extended',
				'company'  => 'Google',
				'category' => self::CAT_TRAINING,
			),
			'applebot-extended'    => array(
				'ua'       => null,
				'token'    => 'Applebot-Extended',
				'label'    => 'Applebot-Extended',
				'company'  => 'Apple',
				'category' => self::CAT_TRAINING,
			),
		);

		/**
		 * Filters the AI bot registry, e.g. to add new crawlers.
		 *
		 * @param array $bots Bot registry.
		 */
		return apply_filters( 'geoins_bots', $bots );
	}

	/**
	 * AI referral sources: humans clicking a link inside an AI answer.
	 *
	 * Detected via the Referer host and/or the utm_source parameter
	 * (ChatGPT appends utm_source=chatgpt.com to cited links, which
	 * survives even when the Referer header is stripped).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function referral_sources() {
		$sources = array(
			'chatgpt'    => array(
				'label'   => 'ChatGPT',
				'company' => 'OpenAI',
				'domains' => array( 'chatgpt.com', 'chat.openai.com' ),
				'utm'     => array( 'chatgpt.com', 'chatgpt', 'openai' ),
			),
			'perplexity' => array(
				'label'   => 'Perplexity',
				'company' => 'Perplexity',
				'domains' => array( 'perplexity.ai' ),
				'utm'     => array( 'perplexity', 'perplexity.ai' ),
			),
			'claude'     => array(
				'label'   => 'Claude',
				'company' => 'Anthropic',
				'domains' => array( 'claude.ai' ),
				'utm'     => array( 'claude', 'claude.ai' ),
			),
			'gemini'     => array(
				'label'   => 'Google Gemini',
				'company' => 'Google',
				'domains' => array( 'gemini.google.com', 'bard.google.com' ),
				'utm'     => array( 'gemini' ),
			),
			'copilot'    => array(
				'label'   => 'Microsoft Copilot',
				'company' => 'Microsoft',
				'domains' => array( 'copilot.microsoft.com', 'copilot.cloud.microsoft' ),
				'utm'     => array( 'copilot' ),
			),
			'meta-ai'    => array(
				'label'   => 'Meta AI',
				'company' => 'Meta',
				'domains' => array( 'meta.ai' ),
				'utm'     => array( 'meta.ai', 'meta_ai' ),
			),
			'mistral'    => array(
				'label'   => 'Mistral Le Chat',
				'company' => 'Mistral AI',
				'domains' => array( 'chat.mistral.ai' ),
				'utm'     => array( 'mistral' ),
			),
			'deepseek'   => array(
				'label'   => 'DeepSeek',
				'company' => 'DeepSeek',
				'domains' => array( 'chat.deepseek.com' ),
				'utm'     => array( 'deepseek' ),
			),
			'grok'       => array(
				'label'   => 'Grok',
				'company' => 'xAI',
				'domains' => array( 'grok.com', 'x.ai' ),
				'utm'     => array( 'grok' ),
			),
		);

		/**
		 * Filters the AI referral source registry.
		 *
		 * @param array $sources Referral sources.
		 */
		return apply_filters( 'geoins_referral_sources', $sources );
	}

	/**
	 * Bots that actually send requests (trackable).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function trackable_bots() {
		$out = array();
		foreach ( self::bots() as $slug => $bot ) {
			if ( ! empty( $bot['ua'] ) ) {
				$out[ $slug ] = $bot;
			}
		}
		return $out;
	}

	/**
	 * Human-readable category labels.
	 *
	 * @return array<int,string>
	 */
	public static function category_labels() {
		return array(
			self::CAT_TRAINING  => __( 'Training', 'wille-geo-ai-visibility' ),
			self::CAT_RETRIEVAL => __( 'Retrieval (citation index)', 'wille-geo-ai-visibility' ),
			self::CAT_AGENT     => __( 'Agent (live user request)', 'wille-geo-ai-visibility' ),
			self::CAT_SEARCH    => __( 'Search (feeds AI answers)', 'wille-geo-ai-visibility' ),
		);
	}

	/**
	 * Short benefit / consequence explanation per category.
	 *
	 * @return array<int,string>
	 */
	public static function category_explainers() {
		return array(
			self::CAT_TRAINING  => __( 'Collects content for model training. Blocking costs you nothing in AI search – it is purely a content-rights decision.', 'wille-geo-ai-visibility' ),
			self::CAT_RETRIEVAL => __( 'Builds the index AI search engines cite from. Blocking removes you from cited AI answers – usually a bad idea.', 'wille-geo-ai-visibility' ),
			self::CAT_AGENT     => __( 'Fetches your page live because a real person just asked an AI about it. Highest-intent traffic – blocking turns these readers away.', 'wille-geo-ai-visibility' ),
			self::CAT_SEARCH    => __( 'Classic search crawling that also feeds AI assistants (Bing feeds Copilot and parts of ChatGPT search). Blocking removes you from that search engine too.', 'wille-geo-ai-visibility' ),
		);
	}

	/**
	 * Look up a bot by matching the User-Agent string.
	 *
	 * @param string $user_agent Raw User-Agent header.
	 * @return array{0:string,1:array<string,mixed>}|null array( slug, bot ) or null.
	 */
	public static function match_user_agent( $user_agent ) {
		if ( '' === $user_agent ) {
			return null;
		}
		foreach ( self::trackable_bots() as $slug => $bot ) {
			if ( false !== stripos( $user_agent, $bot['ua'] ) ) {
				return array( $slug, $bot );
			}
		}
		return null;
	}

	/**
	 * Look up a referral source by referrer URL and utm_source value.
	 *
	 * @param string $referrer   Full referrer URL (may be empty).
	 * @param string $utm_source utm_source query value (may be empty).
	 * @return string|null Source slug or null.
	 */
	public static function match_referral( $referrer, $utm_source ) {
		$host = '';
		if ( '' !== $referrer ) {
			$host = strtolower( (string) wp_parse_url( $referrer, PHP_URL_HOST ) );
			$host = (string) preg_replace( '/^www\./', '', $host );
		}
		$utm = strtolower( trim( $utm_source ) );

		foreach ( self::referral_sources() as $slug => $source ) {
			if ( '' !== $host ) {
				foreach ( $source['domains'] as $domain ) {
					$suffix = '.' . $domain;
					if ( $host === $domain || substr( $host, -strlen( $suffix ) ) === $suffix ) {
						return $slug;
					}
				}
			}
			if ( '' !== $utm && in_array( $utm, $source['utm'], true ) ) {
				return $slug;
			}
		}
		return null;
	}

	/**
	 * All companies with their source slugs (bots + referral sources),
	 * for the per-AI dashboard filter.
	 *
	 * @return array<string,array{label:string,sources:string[]}> Keyed by sanitized company key.
	 */
	public static function companies() {
		$companies = array();

		foreach ( self::bots() as $slug => $bot ) {
			$key = sanitize_title( $bot['company'] );
			if ( ! isset( $companies[ $key ] ) ) {
				$companies[ $key ] = array(
					'label'   => $bot['company'],
					'sources' => array(),
				);
			}
			$companies[ $key ]['sources'][] = $slug;
		}

		foreach ( self::referral_sources() as $slug => $source ) {
			$company = isset( $source['company'] ) ? $source['company'] : $source['label'];
			$key     = sanitize_title( $company );
			if ( ! isset( $companies[ $key ] ) ) {
				$companies[ $key ] = array(
					'label'   => $company,
					'sources' => array(),
				);
			}
			$companies[ $key ]['sources'][] = $slug;
		}

		return $companies;
	}

	/**
	 * Validate a list of source slugs against the registries.
	 *
	 * Returns the canonical stored form: registry keys as the tracker writes
	 * them (40-char cap), looked up via their sanitize_key() form so that
	 * filter-registered slugs that are not sanitize_key-stable still match.
	 *
	 * @param string[] $slugs Raw slugs (untrusted input).
	 * @return string[] Known slugs in stored form (empty array = no valid filter).
	 */
	public static function valid_sources( $slugs ) {
		$known = array_merge( array_keys( self::bots() ), array_keys( self::referral_sources() ) );
		$map   = array();
		foreach ( $known as $raw ) {
			// Sanitized form => the value actually stored in the source column.
			$map[ sanitize_key( $raw ) ] = substr( $raw, 0, 40 );
		}
		$out = array();
		foreach ( (array) $slugs as $slug ) {
			$slug = sanitize_key( $slug );
			if ( isset( $map[ $slug ] ) ) {
				$out[] = $map[ $slug ];
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Label for any source slug (bot or referral).
	 *
	 * @param string $slug Source slug.
	 * @return string
	 */
	public static function label( $slug ) {
		$bots = self::bots();
		if ( isset( $bots[ $slug ] ) ) {
			return $bots[ $slug ]['label'];
		}
		$refs = self::referral_sources();
		if ( isset( $refs[ $slug ] ) ) {
			return $refs[ $slug ]['label'];
		}
		return $slug;
	}
}
