<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Service;

use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\Leiterrunde\Service\RoleService;
use OCA\LeiterrundeVeranstaltungen\Db\Termin;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;

class TerminService {
	public const FREQS = ['once', 'weekly', 'monthly_weekday', 'monthly_date'];

	private const WEEKDAY_NAMES = [
		1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday',
		5 => 'friday', 6 => 'saturday', 7 => 'sunday',
	];
	private const ORDINALS = [1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth'];

	public function __construct(
		private PermissionService $perm,
		private RoleService $roles,
		private IUserSession $userSession,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private PersonMapper $personMapper,
	) {
	}

	/**
	 * Expand a recurrence rule into upcoming occurrence dates (Y-m-d), starting
	 * at or after $from, bounded by the rule's recur_until. $rule keys: freq,
	 * start_date, weekday (1=Mon..7=Sun), month_pos (1-4, -1=last), month_day,
	 * recur_until.
	 * @return string[]
	 */
	public static function expand(array $rule, \DateTimeImmutable $from, int $count): array {
		$start = \DateTimeImmutable::createFromFormat('!Y-m-d', (string)$rule['start_date']);
		if ($start === false) {
			return [];
		}
		$until = null;
		if (!empty($rule['recur_until'])) {
			$until = \DateTimeImmutable::createFromFormat('!Y-m-d', (string)$rule['recur_until']) ?: null;
		}
		$freq = $rule['freq'] ?? 'once';
		$out = [];

		if ($freq === 'once') {
			if ($start >= $from && ($until === null || $start <= $until)) {
				$out[] = $start->format('Y-m-d');
			}
			return $out;
		}

		if ($freq === 'weekly') {
			$wd = (int)($rule['weekday'] ?? 0);
			$d = $start > $from ? $start : $from;
			$d = $d->modify('+' . (($wd - (int)$d->format('N') + 7) % 7) . ' days');
			$guard = 0;
			while (count($out) < $count && $guard++ < 5200) {
				if ($until !== null && $d > $until) {
					break;
				}
				if ($d >= $start) {
					$out[] = $d->format('Y-m-d');
				}
				$d = $d->modify('+7 days');
			}
			return $out;
		}

		if ($freq !== 'monthly_weekday' && $freq !== 'monthly_date') {
			return [];
		}
		$cursor = ($start > $from ? $start : $from)->modify('first day of this month');
		$guard = 0;
		while (count($out) < $count && $guard++ < 500) {
			$d = self::monthlyOccurrence($rule, $cursor);
			if ($d !== null && $until !== null && $d > $until) {
				break;
			}
			if ($d !== null && $d >= $start && $d >= $from) {
				$out[] = $d->format('Y-m-d');
			}
			$cursor = $cursor->modify('first day of next month');
		}
		return $out;
	}

	/** Occurrence date within the given month (1st of month), or null if the rule has none. */
	private static function monthlyOccurrence(array $rule, \DateTimeImmutable $month): ?\DateTimeImmutable {
		if (($rule['freq'] ?? '') === 'monthly_date') {
			$day = (int)($rule['month_day'] ?? 0);
			if ($day < 1 || $day > 31) {
				return null;
			}
			return $month->setDate((int)$month->format('Y'), (int)$month->format('m'), min($day, (int)$month->format('t')));
		}
		if (($rule['freq'] ?? '') === 'monthly_weekday') {
			$wd = (int)($rule['weekday'] ?? 0);
			$pos = (int)($rule['month_pos'] ?? 0);
			$ym = $month->format('Y-m');
			if ($pos === -1 && isset(self::WEEKDAY_NAMES[$wd])) {
				$d = new \DateTimeImmutable('last ' . self::WEEKDAY_NAMES[$wd] . ' of ' . $ym);
				return $d->format('Y-m') === $ym ? $d : null;
			}
			if (isset(self::ORDINALS[$pos], self::WEEKDAY_NAMES[$wd])) {
				$d = new \DateTimeImmutable(self::ORDINALS[$pos] . ' ' . self::WEEKDAY_NAMES[$wd] . ' of ' . $ym);
				return $d->format('Y-m') === $ym ? $d : null;
			}
		}
		return null;
	}

	/** @return string[] next $count occurrence dates of $t at or after $from */
	public function occurrences(Termin $t, \DateTimeImmutable $from, int $count = 3): array {
		return self::expand([
			'freq' => $t->getFreq(),
			'start_date' => $t->getStartDate(),
			'weekday' => $t->getWeekday(),
			'month_pos' => $t->getMonthPos(),
			'month_day' => $t->getMonthDay(),
			'recur_until' => $t->getRecurUntil(),
		], $from, $count);
	}

	/** Audience check enforced server-side: writers and the creator see everything,
	 *  others only if their roles or their linked person are in the audience. */
	public function visible(Termin $t, ?string $uid = null): bool {
		$uid ??= $this->userSession->getUser()?->getUID();
		if ($uid === null) {
			return false;
		}
		if ($t->getCreatedBy() === $uid || $this->perm->has('events.write', $uid)) {
			return true;
		}
		$audRoles = json_decode($t->getAudienceRoles() ?? '[]', true);
		if (is_array($audRoles) && $audRoles !== []) {
			$user = $this->userManager->get($uid);
			if ($user !== null) {
				$userRoles = $this->roles->userRoles($this->groupManager->getUserGroupIds($user));
				if (array_intersect($userRoles, $audRoles) !== []) {
					return true;
				}
			}
		}
		$audPersons = json_decode($t->getAudiencePersons() ?? '[]', true);
		if (is_array($audPersons) && $audPersons !== []) {
			$person = $this->personMapper->findByUserId($uid);
			if ($person !== null && in_array($person->getId(), array_map('intval', $audPersons), true)) {
				return true;
			}
		}
		return false;
	}
}
