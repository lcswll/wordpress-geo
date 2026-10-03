<?php
/**
 * Bot identity verification: CIDR extraction from vendor IP lists and range matching.
 *
 * @package Wille_GEO
 */

namespace GEOINS\Tests;

use GEOINS_Verify;

final class VerifyTest extends TestCase {

	public function test_extract_cidrs_is_format_agnostic(): void {
		$openai = '{"creationTime":"2026-09-01","prefixes":[{"ipv4Prefix":"20.42.10.176/28"},{"ipv4Prefix":"52.230.152.0/24"}]}';
		$this->assertSame( array( '20.42.10.176/28', '52.230.152.0/24' ), GEOINS_Verify::extract_cidrs( $openai ) );

		$mixed = GEOINS_Verify::extract_cidrs( '{"prefixes":[{"ipv6Prefix":"2a01:4b0::/32"},{"ip":"1.2.3.4"},{"ip":"2001:db8::1"}]}' );
		$this->assertContains( '2a01:4b0::/32', $mixed );
		$this->assertContains( '1.2.3.4/32', $mixed, 'bare IPv4 becomes /32' );
		$this->assertContains( '2001:db8::1/128', $mixed, 'bare IPv6 becomes /128' );
	}

	public function test_extract_cidrs_drops_invalid_addresses(): void {
		$this->assertSame( array(), GEOINS_Verify::extract_cidrs( '{"version":"1.2.3","ip":"999.1.1.1","time":"12:30:00"}' ) );
	}

	/**
	 * @return array<string,array{0:string,1:string,2:bool}>
	 */
	public function ranges(): array {
		return array(
			'v4 inside'        => array( '20.42.10.7', '20.42.10.0/24', true ),
			'v4 outside'       => array( '20.42.11.7', '20.42.10.0/24', false ),
			'v4 odd prefix'    => array( '10.0.0.129', '10.0.0.128/25', true ),
			'v4 odd prefix no' => array( '10.0.0.127', '10.0.0.128/25', false ),
			'v4 /32'           => array( '1.2.3.4', '1.2.3.4/32', true ),
			'v4 /0'            => array( '8.8.8.8', '0.0.0.0/0', true ),
			'v6 inside'        => array( '2a01:4b0::1', '2a01:4b0::/32', true ),
			'v6 outside'       => array( '2a02:4b0::1', '2a01:4b0::/32', false ),
			'mixed families'   => array( '1.2.3.4', '2a01:4b0::/32', false ),
			'bad prefix'       => array( '1.2.3.4', '1.2.3.0/33', false ),
			'garbage ip'       => array( 'not-an-ip', '1.2.3.0/24', false ),
			'no prefix equal'  => array( '1.2.3.4', '1.2.3.4', true ),
		);
	}

	/**
	 * @dataProvider ranges
	 */
	public function test_ip_in_cidr( string $ip, string $cidr, bool $expected ): void {
		$this->assertSame( $expected, GEOINS_Verify::ip_in_cidr( $ip, $cidr ) );
	}
}
