<?php
/**
 * robots.txt: the analyzer of physical files and the rules the plugin adds to the virtual one.
 *
 * @package GEO_Insights
 */

namespace GEOINS\Tests;

use GEOINS_Robots;
use GEOINS_Test_Plugin;

final class RobotsTest extends TestCase {

	public function test_full_block_partial_disallow_and_allow_override(): void {
		$result = GEOINS_Robots::analyze_content(
			"# blocked training bot\nUser-agent: GPTBot\nDisallow: /\n\nUser-agent: OAI-SearchBot\nAllow: /\nDisallow: /\n\n" .
			"User-agent: bingbot\nDisallow: /wp-admin/\n\nUser-agent: *\nDisallow: /private/\n"
		);
		$this->assertContains( 'gptbot', $result['blocked'] );
		$this->assertNotContains( 'oai-searchbot', $result['blocked'], 'Allow: / wins' );
		$this->assertNotContains( 'bingbot', $result['blocked'], 'partial disallow is no block' );
		$this->assertFalse( $result['blocked_all'] );
	}

	public function test_wildcard_block_is_inherited_unless_a_named_group_exists(): void {
		$all = GEOINS_Robots::analyze_content( "User-agent: *\nDisallow: /\n" );
		$this->assertTrue( $all['blocked_all'] );
		$this->assertContains( 'claudebot', $all['blocked'] );
		$this->assertFalse( $all['has_exceptions'] );

		$exception = GEOINS_Robots::analyze_content( "User-agent: *\nDisallow: /\n\nUser-agent: Bingbot\nDisallow:\n" );
		$this->assertTrue( $exception['has_exceptions'] );
		$this->assertNotContains( 'bingbot', $exception['blocked'] );
		$this->assertContains( 'gptbot', $exception['blocked'] );
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function gptbotBlocks(): array {
		return array(
			'lower case'       => array( "user-agent: gptbot\ndisallow: /\n" ),
			'BOM + CRLF'       => array( "\xEF\xBB\xBFUser-agent: GPTBot\r\nDisallow: /\r\n" ),
			'slash star'       => array( "User-agent: GPTBot\nDisallow: /*\n" ),
			'versioned token'  => array( "User-agent: GPTBot/1.0\nDisallow: /\n" ),
			'grouped agents'   => array( "User-agent: CCBot\nUser-agent: GPTBot\nDisallow: /\n" ),
			'trailing comment' => array( "User-agent: GPTBot # OpenAI\nDisallow: / # all\n" ),
		);
	}

	/**
	 * @dataProvider gptbotBlocks
	 */
	public function test_syntax_variants( string $content ): void {
		$this->assertContains( 'gptbot', GEOINS_Robots::analyze_content( $content )['blocked'] );
	}

	public function test_rules_before_any_user_agent_are_ignored(): void {
		$result = GEOINS_Robots::analyze_content( "Disallow: /\nSitemap: https://site.example/sitemap.xml\n" );
		$this->assertSame( array(), $result['blocked'] );
		$this->assertFalse( $result['blocked_all'] );
	}

	public function test_filter_adds_rules_for_blocked_bots_only(): void {
		GEOINS_Test_Plugin::$settings = array( 'blocked_bots' => array( 'gptbot', 'ccbot', 'not-a-bot' ) );

		$output = GEOINS_Robots::filter_robots( "User-agent: *\nDisallow: /wp-admin/\n", true );

		$this->assertStringStartsWith( "User-agent: *\nDisallow: /wp-admin/\n", $output, 'core rules stay first' );
		$this->assertStringContainsString( "\nUser-agent: GPTBot\nDisallow: /\n", $output );
		$this->assertStringContainsString( "\nUser-agent: CCBot\nDisallow: /\n", $output );
		$this->assertStringNotContainsString( 'not-a-bot', $output );

		// What the plugin writes, its own analyzer must read back as exactly those blocks.
		$read_back = GEOINS_Robots::analyze_content( $output );
		$this->assertContains( 'gptbot', $read_back['blocked'] );
		$this->assertContains( 'ccbot', $read_back['blocked'] );
		$this->assertNotContains( 'claudebot', $read_back['blocked'] );
		$this->assertFalse( $read_back['blocked_all'] );
	}

	public function test_filter_leaves_private_sites_and_disabled_control_alone(): void {
		GEOINS_Test_Plugin::$settings = array( 'blocked_bots' => array( 'gptbot' ) );
		$this->assertSame( 'X', GEOINS_Robots::filter_robots( 'X', false ) );

		GEOINS_Test_Plugin::$settings = array(
			'blocked_bots'   => array( 'gptbot' ),
			'robots_control' => 0,
		);
		$this->assertSame( 'X', GEOINS_Robots::filter_robots( 'X', true ) );
	}
}
