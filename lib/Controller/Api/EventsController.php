<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Controller\Api;

use OCA\Leiterrunde\Controller\Api\ApiController;
use OCA\Leiterrunde\AppInfo\Application;
use OCA\LeiterrundeVeranstaltungen\Db\Event;
use OCA\LeiterrundeVeranstaltungen\Db\EventCost;
use OCA\LeiterrundeVeranstaltungen\Db\EventCostMapper;
use OCA\LeiterrundeVeranstaltungen\Db\EventMapper;
use OCA\LeiterrundeVeranstaltungen\Db\EventTeam;
use OCA\LeiterrundeVeranstaltungen\Db\EventTeamMapper;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\AuditService;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\Leiterrunde\Service\SettingsService;
use OCA\LeiterrundeVeranstaltungen\Service\Sync\EventSyncService;
use OCA\Leiterrunde\Module\ITrainingCheckProvider;
use OCA\Leiterrunde\Module\ModuleRegistry;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class EventsController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private EventMapper $mapper,
		private EventTeamMapper $teamMapper,
		private \OCA\LeiterrundeVeranstaltungen\Db\RegistrationMapper $regMapper,
		private EventCostMapper $costMapper,
		private PersonMapper $personMapper,
		private ModuleRegistry $modules,
		private EventSyncService $syncService,
		private SettingsService $settings,
		private AuditService $audit,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('events.read');
			return array_map(static fn ($e) => $e->jsonSerialize(), $this->mapper->findAll());
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('events.read');
			return $this->mapper->find($id)->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function create(
		string $title,
		string $start_date,
		?string $description = null,
		?string $end_date = null,
		?string $location = null,
		?float $fee = null,
		?int $capacity = null,
		?string $reg_deadline = null,
		?string $reg_fields = null,
	): JSONResponse {
		return $this->wrap(function () use ($title, $start_date, $description, $end_date, $location, $fee, $capacity, $reg_deadline, $reg_fields) {
			$this->perm->require('events.write');
			$e = new Event();
			$this->applyFields($e, compact('title', 'start_date', 'description', 'end_date', 'location', 'fee', 'capacity', 'reg_deadline', 'reg_fields'));
			$e->setStatus('draft');
			$e->setCreatedAt(time());
			$e->setUpdatedAt(time());
			$e = $this->mapper->insert($e);
			$this->audit->diff('event', $e->getId(), null, ['_action' => 'created']);
			return new JSONResponse($e->jsonSerialize(), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	public function update(
		int $id,
		?string $title = null,
		?string $start_date = null,
		?string $description = null,
		?string $end_date = null,
		?string $location = null,
		?float $fee = null,
		?int $capacity = null,
		?string $reg_deadline = null,
		?string $reg_fields = null,
		?string $status = null,
	): JSONResponse {
		return $this->wrap(function () use ($id, $title, $start_date, $description, $end_date, $location, $fee, $capacity, $reg_deadline, $reg_fields, $status) {
			$this->perm->require('events.write');
			$e = $this->mapper->find($id);
			$before = $e->jsonSerialize();
			$params = $this->request->getParams();
			$data = compact('title', 'start_date', 'description', 'end_date', 'location', 'fee', 'capacity', 'reg_deadline', 'reg_fields');
			foreach (array_keys($data) as $k) {
				if (!array_key_exists($k, $params)) {
					unset($data[$k]);
				}
			}
			$this->applyFields($e, $data, true);
			if ($status !== null && in_array($status, ['draft', 'published'], true)) {
				$e->setStatus($status);
			}
			$e->setUpdatedAt(time());
			$sync = null;
			if ($e->getStatus() === 'published') {
				$e->setSyncPending(true);
				$sync = $this->syncService->push($e);
			} else {
				$e = $this->mapper->update($e);
			}
			$this->audit->diff('event', $e->getId(), $before, $e->jsonSerialize());
			$out = $e->jsonSerialize();
			if ($sync !== null) {
				$out['sync_result'] = $sync;
			}
			return $out;
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('events.write');
			$this->mapper->delete($this->mapper->find($id));
			$this->audit->diff('event', $id, null, ['_action' => 'deleted']);
			return ['ok' => true];
		});
	}

	#[NoAdminRequired]
	public function publish(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('events.write');
			$e = $this->mapper->find($id);
			$res = $this->syncService->push($e);
			return ['ok' => $res['ok'], 'error' => $res['error'], 'event' => $this->mapper->find($id)->jsonSerialize()];
		});
	}

	/* ---- Team ---- */

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function team(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('events.read');
			$event = $this->mapper->find($id);
			$at = \DateTimeImmutable::createFromFormat('!Y-m-d', $event->getStartDate()) ?: new \DateTimeImmutable('today');
			$canFz = $this->perm->has('fz.read');
			$out = [];
			foreach ($this->teamMapper->findForEvent($id) as $t) {
				$row = $t->jsonSerialize();
				try {
					$p = $this->personMapper->find($t->getPersonId());
					$row['person_name'] = trim($p->getFirstName() . ' ' . $p->getLastName());
					$checkProvider = $this->modules->provider(ITrainingCheckProvider::class);
					if ($checkProvider !== null) {
						$check = $checkProvider->betreuerCheck($t->getPersonId(), $at, $canFz);
						$row['check'] = ['overall' => $check['overall'], 'fz' => $check['fz']];
					}
				} catch (\Throwable) {
					$row['check'] = null;
				}
				$out[] = $row;
			}
			return $out;
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function teamRoles(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('events.read');
			return array_values(array_unique(array_merge(
				['Leitung', 'Betreuer/in', 'Kitchen', 'Erste Hilfe'],
				array_filter($this->teamMapper->distinctRoles())
			)));
		});
	}

	#[NoAdminRequired]
	public function teamAdd(int $id, int $person_id, ?string $role = null): JSONResponse {
		return $this->wrap(function () use ($id, $person_id, $role) {
			$this->perm->require('events.write');
			$this->mapper->find($id);
			$this->personMapper->find($person_id);
			$t = new EventTeam();
			$t->setEventId($id);
			$t->setPersonId($person_id);
			$t->setRole($role);
			return new JSONResponse($this->teamMapper->insert($t)->jsonSerialize(), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	public function teamRemove(int $id, int $member_id): JSONResponse {
		return $this->wrap(function () use ($id, $member_id) {
			$this->perm->require('events.write');
			$t = $this->teamMapper->find($member_id);
			if ($t->getEventId() !== $id) {
				throw new ApiException('Not found', 404);
			}
			$this->teamMapper->delete($t);
			return ['ok' => true];
		});
	}

	/* ---- Kosten / Kalkulation ---- */

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function costs(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('events.read');
			return array_map(static fn ($c) => $c->jsonSerialize(), $this->costMapper->findForEvent($id));
		});
	}

	#[NoAdminRequired]
	public function costAdd(int $id, string $label, float $amount, ?string $category = null): JSONResponse {
		return $this->wrap(function () use ($id, $label, $amount, $category) {
			$this->perm->require('events.write');
			$this->mapper->find($id);
			$c = new EventCost();
			$c->setEventId($id);
			$c->setLabel($label);
			$c->setAmount(number_format($amount, 2, '.', ''));
			$c->setCategory($category);
			return new JSONResponse($this->costMapper->insert($c)->jsonSerialize(), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	public function costRemove(int $id, int $cost_id): JSONResponse {
		return $this->wrap(function () use ($id, $cost_id) {
			$this->perm->require('events.write');
			$c = $this->costMapper->find($cost_id);
			if ($c->getEventId() !== $id) {
				throw new ApiException('Not found', 404);
			}
			$this->costMapper->delete($c);
			return ['ok' => true];
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function calculation(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('events.read');
			$event = $this->mapper->find($id);
			$total = 0.0;
			foreach ($this->costMapper->findForEvent($id) as $c) {
				$total += (float)$c->getAmount();
			}
			$capacity = $event->getCapacity();
			return [
				'total' => round($total, 2),
				'capacity' => $capacity,
				'suggested_fee' => ($capacity !== null && $capacity > 0) ? round($total / $capacity, 2) : null,
				'fee' => $event->getFee(),
			];
		});
	}

	#[NoAdminRequired]
	public function backfillObligations(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('events.write');
			$provider = $this->modules->provider(\OCA\Leiterrunde\Module\IObligationProvider::class);
			if ($provider === null) {
				throw new ApiException('Finances module not active', 503);
			}
			$created = 0;
			foreach ($this->mapper->findAll() as $event) {
				if ($event->getFee() === null || (float)$event->getFee() <= 0) {
					continue;
				}
				foreach ($this->regMapper->findForEvent($event->getId()) as $r) {
					if ($r->getStatus() !== 'active' || $r->getObligationId() !== null) {
						continue;
					}
					$ref = $r->getPaymentRef();
					if ($ref === null || $ref === '') {
						$ref = 'EVT' . $event->getId() . '-REG' . $r->getRemoteRegId();
						$r->setPaymentRef($ref);
					}
					$oid = $provider->ensure(
						$ref, $r->getName(), 'Teilnahmebeitrag: ' . $event->getTitle(),
						number_format((float)$event->getFee(), 2, '.', ''),
						$event->getRegDeadline() ?? $event->getStartDate(),
						$r->getId(), null
					);
					$r->setObligationId($oid);
					$r->setUpdatedAt(time());
					$this->regMapper->update($r);
					$created++;
				}
			}
			return ['ok' => true, 'created' => $created];
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function teamCheck(int $id): JSONResponse {
		return $this->team($id);
	}

	private function applyFields(Event $e, array $data, bool $partial = false): void {
		if (isset($data['title'])) {
			if (trim($data['title']) === '') {
				throw new ApiException('Titel ist erforderlich', 422);
			}
			$e->setTitle(trim($data['title']));
		}
		foreach (['start_date' => 'setStartDate', 'end_date' => 'setEndDate', 'reg_deadline' => 'setRegDeadline'] as $k => $setter) {
			if (array_key_exists($k, $data)) {
				$v = $data[$k];
				if ($v !== null && $v !== '') {
					if (\DateTimeImmutable::createFromFormat('!Y-m-d', $v) === false) {
						throw new ApiException('Invalid date: %s', 422, [$k]);
					}
					$e->$setter($v);
				} else {
					$e->$setter(null);
				}
			}
		}
		foreach (['description' => 'setDescription', 'location' => 'setLocation'] as $k => $setter) {
			if (array_key_exists($k, $data)) {
				$e->$setter($data[$k]);
			}
		}
		if (array_key_exists('fee', $data)) {
			$e->setFee($data['fee'] === null ? null : number_format((float)$data['fee'], 2, '.', ''));
		}
		if (array_key_exists('capacity', $data)) {
			$e->setCapacity($data['capacity'] === null ? null : (int)$data['capacity']);
		}
		if (array_key_exists('reg_fields', $data)) {
			$v = (string)($data['reg_fields'] ?? '');
			if ($v === '') {
				$v = '[]';
			}
			$this->settings->validateRegFields($v);
			$e->setRegFields($v);
		}
		if (!$partial && $e->getStartDate() === '') {
			throw new ApiException('Start date required', 422);
		}
	}
}
