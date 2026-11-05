<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Tests\Unit;

use OCA\LeiterrundeVeranstaltungen\Service\Sync\RegistrationSyncService;
use PHPUnit\Framework\TestCase;

class RegistrationSyncTimestampTest extends TestCase {
	public function testInt(): void {
		$this->assertSame(1793839000, RegistrationSyncService::parseTimestamp(1793839000));
	}

	public function testNumericString(): void {
		$this->assertSame(1793839000, RegistrationSyncService::parseTimestamp('1793839000'));
	}

	public function testDateString(): void {
		$this->assertSame(strtotime('2026-11-05'), RegistrationSyncService::parseTimestamp('2026-11-05'));
	}

	public function testUnparsable(): void {
		$this->assertNull(RegistrationSyncService::parseTimestamp('kein datum'));
		$this->assertNull(RegistrationSyncService::parseTimestamp([]));
	}
}
