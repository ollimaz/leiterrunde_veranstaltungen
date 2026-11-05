<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Module;

use OCA\Leiterrunde\Module\CapabilityDefinition;
use OCA\Leiterrunde\Module\IModule;
use OCA\Leiterrunde\Module\OverviewTile;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\LeiterrundeVeranstaltungen\Db\EventMapper;
use OCA\LeiterrundeVeranstaltungen\Db\RegistrationMapper;
use OCP\IUser;
use OCP\IL10N;

class VeranstaltungenModule implements IModule {
	public function __construct(
		private PermissionService $perm,
		private EventMapper $eventMapper,
		private RegistrationMapper $regMapper,
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
