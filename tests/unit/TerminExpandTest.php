<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Tests;

use OCA\LeiterrundeVeranstaltungen\Service\TerminService;
use PHPUnit\Framework\TestCase;

class TerminExpandTest extends TestCase {
	private static function from(string $d): \DateTimeImmutable {
		return new \DateTimeImmutable($d);
	}

	public function testOnceFutureDate(): void {
		$r = TerminService::expand(['freq' => 'once', 'start_date' => '2026-12-01'], self::from('2026-11-11'), 3);
		$this->assertSame(['2026-12-01'], $r);
	}

	public function testOncePastDateEmpty(): void {
		$r = TerminService::expand(['freq' => 'once', 'start_date' => '2026-10-01'], self::from('2026-11-11'), 3);
		$this->assertSame([], $r);
	}

	public function testWeeklyAlignsToWeekday(): void {
		// 2026-11-11 is a Wednesday; weekly on Thursday (4) -> 2026-11-12
		$r = TerminService::expand(['freq' => 'weekly', 'start_date' => '2026-01-01', 'weekday' => 4], self::from('2026-11-11'), 3);
		$this->assertSame(['2026-11-12', '2026-11-19', '2026-11-26'], $r);
	}

	public function testWeeklyRespectsStart(): void {
		$r = TerminService::expand(['freq' => 'weekly', 'start_date' => '2026-11-30', 'weekday' => 4], self::from('2026-11-11'), 3);
		$this->assertSame(['2026-12-03', '2026-12-10', '2026-12-17'], $r);
	}

	public function testMonthlyLastTuesday(): void {
		// Last Tuesday of Nov 2026 = 24 Nov, Dec 2026 = 29 Dec, Jan 2027 = 26 Jan
		$r = TerminService::expand([
			'freq' => 'monthly_weekday', 'start_date' => '2026-01-01',
			'weekday' => 2, 'month_pos' => -1,
		], self::from('2026-11-01'), 3);
		$this->assertSame(['2026-11-24', '2026-12-29', '2027-01-26'], $r);
	}

	public function testMonthlyNthWeekday(): void {
		// 2nd Friday of Nov 2026 = 13 Nov, Dec = 11 Dec
		$r = TerminService::expand([
			'freq' => 'monthly_weekday', 'start_date' => '2026-01-01',
			'weekday' => 5, 'month_pos' => 2,
		], self::from('2026-11-01'), 2);
		$this->assertSame(['2026-11-13', '2026-12-11'], $r);
	}

	public function testMonthlyDateClampsShortMonth(): void {
		// day 31 in Feb 2027 (28 days) clamps to 28 Feb
		$r = TerminService::expand([
			'freq' => 'monthly_date', 'start_date' => '2026-01-01', 'month_day' => 31,
		], self::from('2027-01-15'), 2);
		$this->assertSame(['2027-01-31', '2027-02-28'], $r);
	}

	public function testRecurUntilCutsSeries(): void {
		$r = TerminService::expand([
			'freq' => 'weekly', 'start_date' => '2026-01-01', 'weekday' => 4,
			'recur_until' => '2026-11-20',
		], self::from('2026-11-11'), 5);
		$this->assertSame(['2026-11-12', '2026-11-19'], $r);
	}

	public function testInvalidFreqEmpty(): void {
		$r = TerminService::expand(['freq' => 'bogus', 'start_date' => '2026-01-01'], self::from('2026-11-11'), 3);
		$this->assertSame([], $r);
	}
}
