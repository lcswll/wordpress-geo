<?php
/**
 * Review request: only after two weeks of use, only when AI traffic is measured, never again after "done".
 *
 * @package Wille_GEO
 */

namespace GEOINS\Tests;

use GEOINS_Review;

final class ReviewTest extends TestCase {

	private const NOW = 1790000000;

	private function ask( array $choice, int $age_days, int $hits ): bool {
		return GEOINS_Review::should_ask( $choice, self::NOW, self::NOW - $age_days * DAY_IN_SECONDS, $hits );
	}

	public function test_asks_once_the_plugin_demonstrably_works(): void {
		$this->assertTrue( $this->ask( array(), 14, 50 ) );
		$this->assertTrue( $this->ask( array(), 200, 5000 ) );
	}

	public function test_waits_for_two_weeks_and_enough_ai_traffic(): void {
		$this->assertFalse( $this->ask( array(), 13, 5000 ), 'too new' );
		$this->assertFalse( $this->ask( array(), 60, 49 ), 'not enough AI accesses yet' );
		$this->assertFalse( GEOINS_Review::should_ask( array(), self::NOW, 0, 5000 ), 'unknown activation date' );
	}

	public function test_respects_the_users_choice(): void {
		$this->assertFalse( $this->ask( array( 'state' => 'done' ), 400, 5000 ) );
		$this->assertFalse(
			$this->ask(
				array(
					'state' => 'later',
					'until' => self::NOW + 1,
				),
				400,
				5000
			)
		);
		$this->assertTrue(
			$this->ask(
				array(
					'state' => 'later',
					'until' => self::NOW - 1,
				),
				400,
				5000
			),
			'asks again after the snooze'
		);
	}
}
