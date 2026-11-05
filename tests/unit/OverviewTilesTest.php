<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Tests;

use OCA\LeiterrundeVeranstaltungen\Module\VeranstaltungenModule;
use PHPUnit\Framework\TestCase;

class OverviewTilesTest extends TestCase {
	public function testTileIdsPresent(): void {
		$perm = $this->createMock(\OCA\Leiterrunde\Service\PermissionService::class);
		$perm->method('has')->willReturn(true);
		$events = $this->createMock(\OCA\LeiterrundeVeranstaltungen\Db\EventMapper::class);
		$events->method('findAll')->willReturn([]);
		$regs = $this->createMock(\OCA\LeiterrundeVeranstaltungen\Db\RegistrationMapper::class);
		$regs->method('findForEvent')->willReturn([]);
		$l = $this->createMock(\OCP\IL10N::class);
		$l->method('t')->willReturnCallback(fn($s, $p = []) => $p ? vsprintf($s, $p) : $s);
		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')->willReturn('u1');
		$m = new VeranstaltungenModule($perm, $events, $regs, $l);
		$ids = array_map(static fn($t) => $t->id, $m->getOverviewTiles($user));
		foreach (['events.upcoming', 'events.registrations', 'events.syncerrors'] as $id) {
			$this->assertContains($id, $ids);
		}
	}
}
