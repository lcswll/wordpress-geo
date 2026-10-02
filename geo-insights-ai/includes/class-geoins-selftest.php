<?php
/**
 * Built-in self-test: assertions over the plugin's pure functions.
 *
 * Runs via `wp geoins selftest` and via the admin-only REST route
 * geoins/v1/selftest, so the same checks can gate a CLI deploy and be
 * executed on a live site from the browser.
 *
 * @package GEO_Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Self-test runner.
 */
class GEOINS_Selftest {

	/**
	 * Run all assertions.
	 *
	 * @return array{passed:int,failures:string[]}
	 */
	public static function run() {
		$failures = array();
		$passed   = 0;

		$check = static function ( $label, $actual, $expected ) use ( &$failures, &$passed ) {
			if ( $actual === $expected ) {
				++$passed;
			} else {
				$failures[] = sprintf( '%s: expected %s, got %s', $label, wp_json_encode( $expected ), wp_json_encode( $actual ) );
			}
		};

		// Word counting (Unicode).
		$check( 'count_words empty', GEOINS_Analysis::count_words( '' ), 0 );
		$check( 'count_words simple', GEOINS_Analysis::count_words( 'one two three' ), 3 );
		$check( 'count_words umlauts', GEOINS_Analysis::count_words( 'größer schön fünf' ), 3 );
		$check( 'count_words hyphen', GEOINS_Analysis::count_words( 'state-of-the-art test' ), 2 );

		// Bot matching.
		$gpt = GEOINS_Bots::match_user_agent( 'Mozilla/5.0 (compatible; GPTBot/1.4; +https://openai.com/gptbot)' );
		$check( 'match GPTBot', is_array( $gpt ) ? $gpt[0] : null, 'gptbot' );
		$claude = GEOINS_Bots::match_user_agent( 'Mozilla/5.0 (compatible; ClaudeBot/1.0)' );
		$check( 'match ClaudeBot', is_array( $claude ) ? $claude[0] : null, 'claudebot' );
		$apple = GEOINS_Bots::match_user_agent( 'Mozilla/5.0 (compatible; Applebot/0.1; +http://www.apple.com/go/applebot)' );
		$check( 'match Applebot', is_array( $apple ) ? $apple[0] : null, 'applebot' );
		$check( 'match none', GEOINS_Bots::match_user_agent( 'Mozilla/5.0 Firefox/130.0' ), null );

		// Referral matching.
		$check( 'referral chatgpt.com', GEOINS_Bots::match_referral( 'https://chatgpt.com/c/abc', '' ), 'chatgpt' );
		$check( 'referral www strip', GEOINS_Bots::match_referral( 'https://www.perplexity.ai/search', '' ), 'perplexity' );
		$check( 'referral utm', GEOINS_Bots::match_referral( '', 'chatgpt.com' ), 'chatgpt' );
		$check( 'referral none', GEOINS_Bots::match_referral( 'https://example.org/', '' ), null );

		// Source validation + company catalog.
		$check( 'valid_sources known', GEOINS_Bots::valid_sources( array( 'gptbot', 'chatgpt', 'nope' ) ), array( 'gptbot', 'chatgpt' ) );
		$check( 'valid_sources sanitized lookup', GEOINS_Bots::valid_sources( array( 'GPTBot' ) ), array( 'gptbot' ) );
		$companies = GEOINS_Bots::companies();
		$check( 'companies openai has bots + referral', isset( $companies['openai'] ) && in_array( 'gptbot', $companies['openai']['sources'], true ) && in_array( 'chatgpt', $companies['openai']['sources'], true ), true );

		// CIDR extraction + matching.
		$cidrs = GEOINS_Verify::extract_cidrs( '{"prefixes":[{"ipv4Prefix":"20.42.10.0/24"},{"ipv6Prefix":"2a01:4b0::/32"},{"ip":"1.2.3.4"}]}' );
		$check( 'extract_cidrs count', count( $cidrs ), 3 );
		$check( 'extract_cidrs bare ip', in_array( '1.2.3.4/32', $cidrs, true ), true );
		$check( 'ip_in_cidr v4 inside', GEOINS_Verify::ip_in_cidr( '20.42.10.7', '20.42.10.0/24' ), true );
		$check( 'ip_in_cidr v4 outside', GEOINS_Verify::ip_in_cidr( '20.42.11.7', '20.42.10.0/24' ), false );
		$check( 'ip_in_cidr v6 inside', GEOINS_Verify::ip_in_cidr( '2a01:4b0::1', '2a01:4b0::/32' ), true );
		$check( 'ip_in_cidr v6 outside', GEOINS_Verify::ip_in_cidr( '2a02:4b0::1', '2a01:4b0::/32' ), false );
		$check( 'ip_in_cidr mixed families', GEOINS_Verify::ip_in_cidr( '1.2.3.4', '2a01:4b0::/32' ), false );
		$check( 'ip_in_cidr odd bits', GEOINS_Verify::ip_in_cidr( '10.0.0.129', '10.0.0.128/25' ), true );

		// robots.txt analysis.
		$sample   = "# blocked training bot\nUser-agent: GPTBot\nDisallow: /\n\nUser-agent: OAI-SearchBot\nAllow: /\nDisallow: /\n\nUser-agent: bingbot\nDisallow: /wp-admin/\n\nUser-agent: *\nDisallow: /private/\n";
		$analysis = GEOINS_Robots::analyze_content( $sample );
		$check( 'robots gptbot blocked', in_array( 'gptbot', $analysis['blocked'], true ), true );
		$check( 'robots allow overrides disallow', in_array( 'oai-searchbot', $analysis['blocked'], true ), false );
		$check( 'robots partial disallow not a block', in_array( 'bingbot', $analysis['blocked'], true ), false );
		$check( 'robots wildcard partial not blocked_all', $analysis['blocked_all'], false );
		$wild = GEOINS_Robots::analyze_content( "User-agent: *\nDisallow: /\n" );
		$check( 'robots wildcard blocked_all', $wild['blocked_all'], true );
		$check( 'robots wildcard blocks claudebot', in_array( 'claudebot', $wild['blocked'], true ), true );
		$case = GEOINS_Robots::analyze_content( "user-agent: gptbot\ndisallow: /\n" );
		$check( 'robots case-insensitive', in_array( 'gptbot', $case['blocked'], true ), true );
		$bom = GEOINS_Robots::analyze_content( "\xEF\xBB\xBFUser-agent: *\r\nDisallow: /\n" );
		$check( 'robots BOM stripped', $bom['blocked_all'], true );
		$star = GEOINS_Robots::analyze_content( "User-agent: GPTBot\nDisallow: /*\n" );
		$check( 'robots /* is a full block', in_array( 'gptbot', $star['blocked'], true ), true );
		$ver = GEOINS_Robots::analyze_content( "User-agent: GPTBot/1.0\nDisallow: /\n" );
		$check( 'robots versioned token matches', in_array( 'gptbot', $ver['blocked'], true ), true );
		$exc = GEOINS_Robots::analyze_content( "User-agent: *\nDisallow: /\n\nUser-agent: Bingbot\nDisallow:\n" );
		$check( 'robots wildcard exception flagged', $exc['has_exceptions'], true );
		$check( 'robots exception not blocked', in_array( 'bingbot', $exc['blocked'], true ), false );

		// Markdown conversion.
		$md = GEOINS_Markdown::html_to_markdown( '<h2>Title</h2><p>Text with <strong>bold</strong>.</p><ul><li>One</li><li>Two</li></ul>' );
		$check( 'markdown heading', false !== strpos( $md, '## Title' ), true );
		$check( 'markdown bold', false !== strpos( $md, '**bold**' ), true );
		$check( 'markdown list', false !== strpos( $md, '- One' ), true );
		$table_md = GEOINS_Markdown::html_to_markdown( '<table><tr><th>A</th><th>B</th></tr><tr><td>1</td><td>2</td></tr></table>' );
		$check( 'markdown table', false !== strpos( $table_md, '| A | B |' ), true );

		// TOC anchors.
		$toc = GEOINS_Content::add_heading_anchors_and_toc( '<h2 class="wp-block-heading">Was ist GEO?</h2><p>a</p><h3>Details &amp; <strong>mehr</strong></h3><p>b</p><h2>Was ist GEO?</h2><p>c</p><h2 id="custom-id">Fazit</h2>' );
		$check( 'toc nav inserted', false !== strpos( $toc, 'class="geoins-toc"' ), true );
		$check( 'toc first anchor', false !== strpos( $toc, 'id="was-ist-geo"' ), true );
		$check( 'toc duplicate suffixed', false !== strpos( $toc, 'id="was-ist-geo-2"' ), true );
		$check( 'toc existing id kept', false !== strpos( $toc, 'href="#custom-id"' ), true );
		$check( 'toc under three headings', false === strpos( GEOINS_Content::add_heading_anchors_and_toc( '<h2>A</h2><h2>B</h2>' ), 'geoins-toc' ), true );

		return array(
			'passed'   => $passed,
			'failures' => $failures,
		);
	}
}
