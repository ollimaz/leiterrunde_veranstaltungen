<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Controller\Api;

use OCA\Leiterrunde\Controller\Api\ApiController;
use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\AuditService;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\Leiterrunde\Service\RoleService;
use OCA\LeiterrundeVeranstaltungen\Db\Termin;
use OCA\LeiterrundeVeranstaltungen\Db\TerminMapper;
use OCA\LeiterrundeVeranstaltungen\Service\TerminService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class TermineController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private TerminMapper $mapper,
		private TerminService $termine,
		private PersonMapper $personMapper,
		private IUserSession $userSession,
		private AuditService $audit,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('events.read');
			$canWrite = $this->perm->has('events.write');
			$from = new \DateTimeImmutable('today');
			$out = [];
			foreach ($this->mapper->findAll() as $t) {
				if (!$this->termine->visible($t)) {
					continue;
				}
				$row = $t->jsonSerialize();
				$row['next_dates'] = $this->termine->occurrences($t, $from, 3);
				if (!$canWrite) {
					unset($row['audience_roles'], $row['audience_persons']);
				}
				$out[] = $row;
			}
			return $out;
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function audienceMeta(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('events.read');
			return ['roles' => RoleService::ROLES];
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('events.read');
			$t = $this->findVisible($id);
			$row = $t->jsonSerialize();
			$row['next_dates'] = $this->termine->occurrences($t, new \DateTimeImmutable('today'), 12);
			if (!$this->perm->has('events.write')) {
				unset($row['audience_roles'], $row['audience_persons']);
			}
			return $row;
		});
	}

	#[NoAdminRequired]
	public function create(
		string $title,
		string $start_date,
		?string $description = null,
		?string $location = null,
		?bool $all_day = null,
		?string $start_time = null,
		?string $end_time = null,
		?string $freq = null,
		?int $weekday = null,
		?int $month_pos = null,
		?int $month_day = null,
		?string $recur_until = null,
		?string $audience_roles = null,
		?string $audience_persons = null,
	): JSONResponse {
		return $this->wrap(function () use ($title, $start_date, $description, $location, $all_day, $start_time, $end_time, $freq, $weekday, $month_pos, $month_day, $recur_until, $audience_roles, $audience_persons) {
			$this->perm->require('events.write');
			$t = new Termin();
			$t->setCreatedBy((string)$this->userSession->getUser()?->getUID());
			$data = array_filter(
				compact('title', 'start_date', 'description', 'location', 'all_day', 'start_time', 'end_time', 'freq', 'weekday', 'month_pos', 'month_day', 'recur_until', 'audience_roles', 'audience_persons'),
				static fn ($v) => $v !== null
			);
			$this->applyFields($t, $data);
			$t->setCreatedAt(time());
			$t->setUpdatedAt(time());
			$t = $this->mapper->insert($t);
			$this->audit->diff('termin', $t->getId(), null, ['_action' => 'created']);
			return new JSONResponse($t->jsonSerialize(), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	public function update(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('events.write');
			$t = $this->mapper->find($id);
			$before = $t->jsonSerialize();
			$allowed = ['title', 'start_date', 'description', 'location', 'all_day', 'start_time', 'end_time', 'freq', 'weekday', 'month_pos', 'month_day', 'recur_until', 'audience_roles', 'audience_persons'];
			$params = $this->request->getParams();
			$data = [];
			foreach ($allowed as $k) {
				if (array_key_exists($k, $params)) {
					$data[$k] = $params[$k];
				}
			}
			$this->applyFields($t, $data, true);
			$t->setUpdatedAt(time());
			$t = $this->mapper->update($t);
			$this->audit->diff('termin', $t->getId(), $before, $t->jsonSerialize());
			return $t->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('events.write');
			$this->mapper->delete($this->mapper->find($id));
			$this->audit->diff('termin', $id, null, ['_action' => 'deleted']);
			return ['ok' => true];
		});
	}

	private function findVisible(int $id): Termin {
		try {
			$t = $this->mapper->find($id);
		} catch (\Throwable) {
			throw new ApiException('Not found', 404);
		}
		if (!$this->termine->visible($t)) {
			throw new ApiException('Not found', 404);
		}
		return $t;
	}

	private function applyFields(Termin $t, array $data, bool $partial = false): void {
		if (array_key_exists('title', $data)) {
			$v = trim((string)$data['title']);
			if ($v === '' || mb_strlen($v) > 200) {
				throw new ApiException('Title required (max 200 chars)', 422);
			}
			$t->setTitle($v);
		}
		foreach (['description' => 'setDescription', 'location' => 'setLocation'] as $k => $setter) {
			if (array_key_exists($k, $data)) {
				$v = $data[$k] !== null ? trim((string)$data[$k]) : null;
				$t->$setter($v === '' ? null : $v);
			}
		}
		foreach (['start_date' => 'setStartDate', 'recur_until' => 'setRecurUntil'] as $k => $setter) {
			if (array_key_exists($k, $data)) {
				$v = $data[$k];
				if ($v === null || $v === '') {
					if ($k === 'start_date') {
						throw new ApiException('Start date required', 422);
					}
					$t->$setter(null);
				} else {
					if (\DateTimeImmutable::createFromFormat('!Y-m-d', (string)$v) === false) {
						throw new ApiException('Invalid date: %s', 422, [$k]);
					}
					$t->$setter((string)$v);
				}
			}
		}
		if (array_key_exists('all_day', $data)) {
			$t->setAllDay(filter_var($data['all_day'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$data['all_day']);
		}
		foreach (['start_time' => 'setStartTime', 'end_time' => 'setEndTime'] as $k => $setter) {
			if (array_key_exists($k, $data)) {
				$v = $data[$k];
				if ($v === null || $v === '') {
					$t->$setter(null);
				} else {
					if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$v)) {
						throw new ApiException('Invalid time: %s', 422, [$k]);
					}
					$t->$setter((string)$v);
				}
			}
		}
		if (array_key_exists('freq', $data)) {
			if (!in_array($data['freq'], TerminService::FREQS, true)) {
				throw new ApiException('Invalid frequency', 422);
			}
			$t->setFreq($data['freq']);
		}
		if (array_key_exists('weekday', $data)) {
			$v = $data['weekday'];
			if ($v === null || $v === '') {
				$t->setWeekday(null);
			} else {
				$v = (int)$v;
				if ($v < 1 || $v > 7) {
					throw new ApiException('Invalid weekday', 422);
				}
				$t->setWeekday($v);
			}
		}
		if (array_key_exists('month_pos', $data)) {
			$v = $data['month_pos'];
			if ($v === null || $v === '') {
				$t->setMonthPos(null);
			} else {
				$v = (int)$v;
				if (!in_array($v, [1, 2, 3, 4, -1], true)) {
					throw new ApiException('Invalid month position', 422);
				}
				$t->setMonthPos($v);
			}
		}
		if (array_key_exists('month_day', $data)) {
			$v = $data['month_day'];
			if ($v === null || $v === '') {
				$t->setMonthDay(null);
			} else {
				$v = (int)$v;
				if ($v < 1 || $v > 31) {
					throw new ApiException('Invalid month day', 422);
				}
				$t->setMonthDay($v);
			}
		}
		foreach (['audience_roles' => 'setAudienceRoles', 'audience_persons' => 'setAudiencePersons'] as $k => $setter) {
			if (array_key_exists($k, $data)) {
				$v = $data[$k];
				$list = is_array($v) ? $v : json_decode((string)$v, true);
				if (!is_array($list)) {
					throw new ApiException('Invalid audience: %s', 422, [$k]);
				}
				if ($k === 'audience_roles') {
					foreach ($list as $r) {
						if (!isset(RoleService::ROLES[$r])) {
							throw new ApiException('Unknown role: %s', 422, [(string)$r]);
						}
					}
					$t->$setter(json_encode(array_values(array_unique(array_map('strval', $list)))));
				} else {
					if (count($list) > 200) {
						throw new ApiException('Too many persons in audience', 422);
					}
					foreach ($list as $pid) {
						if (!is_numeric($pid)) {
							throw new ApiException('Invalid person in audience', 422);
						}
						$this->personMapper->find((int)$pid);
					}
					$t->$setter(json_encode(array_values(array_unique(array_map('intval', $list)))));
				}
			}
		}
		// consistency: freq-specific requirements on the effective (merged) config
		$freq = $t->getFreq();
		if ($freq === 'weekly' && $t->getWeekday() === null) {
			throw new ApiException('Weekday required for weekly series', 422);
		}
		if ($freq === 'monthly_weekday' && ($t->getWeekday() === null || $t->getMonthPos() === null)) {
			throw new ApiException('Weekday and position required for monthly series', 422);
		}
		if ($freq === 'monthly_date' && $t->getMonthDay() === null) {
			throw new ApiException('Month day required for monthly series', 422);
		}
		if (!$partial) {
			if ($t->getStartDate() === '') {
				throw new ApiException('Start date required', 422);
			}
			if ($t->getCreatedBy() === '') {
				throw new ApiException('Not logged in', 401);
			}
		}
		if ($t->getRecurUntil() !== null && $t->getStartDate() !== '' && $t->getRecurUntil() < $t->getStartDate()) {
			throw new ApiException('Series end before start date', 422);
		}
		if ($t->getAllDay()) {
			$t->setStartTime(null);
			$t->setEndTime(null);
		} elseif ($t->getStartTime() !== null && $t->getEndTime() !== null && $t->getEndTime() <= $t->getStartTime()) {
			throw new ApiException('End time must be after start time', 422);
		}
	}
}
