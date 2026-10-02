<?php
/**
 * Bot and referral registries: matching, integrity, company catalogue.
 *
 * @package GEO_Insights
 */

namespace GEOINS\Tests;

use GEOINS_Bots;

final class BotsTest extends TestCase {

	/**
	 * @return array<string,array{0:string,1:string|null}>
	 */
	public function userAgents(): array {
		return array(
			'GPTBot'        => array( 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', 'gptbot' ),
			'ChatGPT-User'  => array( 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot', 'chatgpt-user' ),
			'OAI-SearchBot' => array( 'Mozilla/5.0 (compatible; OAI-SearchBot/1.0; +https://openai.com/searchbot)', 'oai-searchbot' ),
			'ClaudeBot'     => array( 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 'claudebot' ),
			'PerplexityBot' => array( 'Mozilla/5.0 (compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)', 'perplexitybot' ),
			'lower case'    => array( 'gptbot/1.0', 'gptbot' ),
			'Firefox'       => array( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0', null ),
			'empty'         => array( '', null ),
		);
	}

	/**
	 * @dataProvider userAgents
	 */
	public function test_match_user_agent( string $user_agent, ?string $expected ): void {
		$match = GEOINS_Bots::match_user_agent( $user_agent );
		$this->assertSame( $expected, null === $match ? null : $match[0] );
	}

	/**
	 * Matching is substring-based and stops at the first hit, so a bot whose signature contains another bot's
	 * signature would be shadowed. Every registered bot must be found by its own signature.
	 */
	public function test_every_bot_is_reachable_by_its_own_signature(): void {
		$bots = GEOINS_Bots::trackable_bots();
		$this->assertGreaterThan( 20, count( $bots ) );
		foreach ( $bots as $slug => $bot ) {
			$match = GEOINS_Bots::match_user_agent( 'Mozilla/5.0 (compatible; ' . $bot['ua'] . '/1.0)' );
			$this->assertNotNull( $match, $slug );
			$this->assertSame( $slug, $match[0], "{$bot['ua']} is shadowed by {$match[0]}" );
		}
	}

	public function test_registry_entries_are_complete(): void {
		$categories = array_keys( GEOINS_Bots::category_labels() );
		$this->assertSame( $categories, array_keys( GEOINS_Bots::category_explainers() ), 'every category is explained' );
		$tokens = array();
		foreach ( GEOINS_Bots::bots() as $slug => $bot ) {
			$this->assertSame( sanitize_key( $slug ), $slug, 'slug is sanitize_key-stable' );
			$this->assertLessThanOrEqual( 40, strlen( $slug ), 'slug fits the source column' );
			foreach ( array( 'token', 'label', 'company', 'category' ) as $field ) {
				$this->assertArrayHasKey( $field, $bot, "{$slug}.{$field}" );
			}
			$this->assertContains( $bot['category'], $categories, $slug );
			$this->assertMatchesRegularExpression( '/^[A-Za-z0-9_\-]+$/', $bot['token'], "{$slug}: robots.txt token" );
			$tokens[] = strtolower( $bot['token'] );
		}
		$this->assertSame( array_values( array_unique( $tokens ) ), $tokens, 'robots.txt tokens are unique' );
	}

	/**
	 * @return array<string,array{0:string,1:string,2:string|null}>
	 */
	public function referrals(): array {
		return array(
			'chatgpt.com'      => array( 'https://chatgpt.com/c/abc', '', 'chatgpt' ),
			'www stripped'     => array( 'https://www.perplexity.ai/search?q=x', '', 'perplexity' ),
			'subdomain'        => array( 'https://eu.claude.ai/chat', '', 'claude' ),
			'utm only'         => array( '', 'chatgpt.com', 'chatgpt' ),
			'utm case'         => array( '', ' ChatGPT.com ', 'chatgpt' ),
			'lookalike domain' => array( 'https://notchatgpt.com/', '', null ),
			'unrelated'        => array( 'https://example.org/', 'newsletter', null ),
			'nothing'          => array( '', '', null ),
		);
	}

	/**
	 * @dataProvider referrals
	 */
	public function test_match_referral( string $referrer, string $utm, ?string $expected ): void {
		$this->assertSame( $expected, GEOINS_Bots::match_referral( $referrer, $utm ) );
	}

	public function test_valid_sources_keeps_known_slugs_only(): void {
		$this->assertSame( array( 'gptbot', 'chatgpt' ), GEOINS_Bots::valid_sources( array( 'gptbot', 'chatgpt', 'nope', 'gptbot' ) ) );
		$this->assertSame( array( 'gptbot' ), GEOINS_Bots::valid_sources( array( 'GPTBot' ) ) );
		$this->assertSame( array(), GEOINS_Bots::valid_sources( array( "' OR 1=1 --" ) ) );
	}

	public function test_companies_group_bots_and_referral_sources(): void {
		$companies = GEOINS_Bots::companies();
		$this->assertSame( 'OpenAI', $companies['openai']['label'] );
		$this->assertContains( 'gptbot', $companies['openai']['sources'] );
		$this->assertContains( 'chatgpt', $companies['openai']['sources'] );
		$this->assertContains( 'claude', $companies['anthropic']['sources'] );
	}

	public function test_label_falls_back_to_the_slug(): void {
		$this->assertSame( 'GPTBot', GEOINS_Bots::label( 'gptbot' ) );
		$this->assertSame( 'ChatGPT', GEOINS_Bots::label( 'chatgpt' ) );
		$this->assertSame( 'unknown-bot', GEOINS_Bots::label( 'unknown-bot' ) );
	}
}
