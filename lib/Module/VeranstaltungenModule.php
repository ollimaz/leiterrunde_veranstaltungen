<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Module;

use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Module\CapabilityDefinition;
use OCA\Leiterrunde\Module\IModule;
use OCA\Leiterrunde\Module\OverviewTile;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\LeiterrundeVeranstaltungen\Db\EventMapper;
use OCA\LeiterrundeVeranstaltungen\Db\RegistrationMapper;
use OCP\IUser;
use OCP\IL10N;

class VeranstaltungenModule implements IModule, \OCA\Leiterrunde\Module\IEventCatalog {
	public function __construct(
		private PermissionService $perm,
		private EventMapper $eventMapper,
		private RegistrationMapper $regMapper,
		private \OCA\Leiterrunde\Service\AuditService $audit,
		private ?IL10N $l10n = null,
	) {
	}

	public function getId(): string {
		return 'leiterrunde_veranstaltungen';
	}

	public function getLabel(): string {
		return 'Veranstaltungen';
	}

	public function getNavUrl(): string {
		return '/apps/leiterrunde_veranstaltungen/';
	}

	public function getCapabilities(): array {
		return [
			new CapabilityDefinition('events.read', $this->l10n->t('Read events'), $this->l10n->t('Events'), ['any']),
			new CapabilityDefinition('events.write', $this->l10n->t('Write events'), $this->l10n->t('Events'), ['vorstand', 'admin']),
			new CapabilityDefinition('registrations.read', $this->l10n->t('Read registrations'), $this->l10n->t('Events'), ['vorstand', 'kasse', 'admin']),
		];
	}

	public function eventOptions(): array {
		$this->perm->require('events.read');
		return array_map(static fn ($e) => ['id' => $e->getId(), 'title' => $e->getTitle()], $this->eventMapper->findAll());
	}

	public function requireEvent(int $id): void {
		$this->perm->require('events.read');
		$this->eventMapper->find($id);
	}

	public function createEventOption(string $title): int {
		$this->perm->require('events.write');
		$title = trim($title);
		if ($title === '' || mb_strlen($title) > 200) {
			throw new ApiException('Invalid event title', 422);
		}
		$e = new \OCA\LeiterrundeVeranstaltungen\Db\Event();
		$e->setTitle($title);
		$e->setStartDate((new \DateTimeImmutable('today'))->format('Y-m-d'));
		$e->setStatus('draft');
		$e->setCreatedAt(time());
		$e->setUpdatedAt(time());
		$e = $this->eventMapper->insert($e);
		$this->audit->diff('event', $e->getId(), null, ['_action' => 'created']);
		return $e->getId();
	}

	public function getDocumentTypes(): array {
		return [];
	}

	public function getOverviewTiles(IUser $user): array {
		$tiles = [];
		$uid = $user->getUID();
		if ($this->perm->has('events.read', $uid)) {
			$upcoming = [];
			$syncBad = [];
			foreach ($this->eventMapper->findAll(200) as $e) {
				if ($e->getStartDate() >= date('Y-m-d')) {
					$upcoming[] = $e;
				}
				if ($e->getSyncPending()) {
					$syncBad[] = $e;
				}
			}
			usort($upcoming, static fn ($a, $b) => strcmp($a->getStartDate(), $b->getStartDate()));
			$items = [];
			foreach (array_slice($upcoming, 0, 5) as $e) {
				$cnt = count(array_filter($this->regMapper->findForEvent($e->getId()), static fn ($r) => $r->getStatus() === 'active'));
				$cap = $e->getCapacity();
				$items[] = ['label' => $e->getTitle(), 'date' => $e->getStartDate(), 'sublabel' => $cnt . '/' . ($cap ?? '?')];
			}
			$tiles[] = new OverviewTile('events.upcoming', $this->l10n->t('Upcoming events'), 'ok', count($upcoming), $items, '/apps/leiterrunde_veranstaltungen/#/liste');
			$series = [];
			foreach (array_slice($upcoming, 0, 5) as $e) {
				$active = count(array_filter($this->regMapper->findForEvent($e->getId()), static fn ($r) => $r->getStatus() === 'active'));
				$series[] = ['label' => $e->getTitle(), 'value' => $active, 'secondary' => $e->getCapacity() ?? 0, 'state' => 'info'];
			}
			$tiles[] = new OverviewTile('events.registrations', $this->l10n->t('Registrations'), 'info', null, [], '/apps/leiterrunde_veranstaltungen/#/liste', null,
				['type' => 'meters', 'series' => $series]);
			if ($this->perm->has('events.write', $uid)) {
				$items = array_map(static fn ($e) => ['label' => $e->getTitle(), 'sublabel' => $e->getLastSyncStatus()], $syncBad);
				$tiles[] = new OverviewTile('events.syncerrors', $this->l10n->t('Sync errors'), count($syncBad) > 0 ? 'fail' : 'ok', count($syncBad), array_slice($items, 0, 5), '/apps/leiterrunde_veranstaltungen/#/liste');
			}
		}
		return $tiles;
	}
}
