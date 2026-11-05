<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Service\Sync;

use OCA\LeiterrundeVeranstaltungen\Db\Event;
use OCA\LeiterrundeVeranstaltungen\Db\EventMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\SettingsService;
use OCA\LeiterrundeVeranstaltungen\Service\ModuleConfigService;
use OCA\Leiterrunde\Service\Sync\Signer;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

class EventSyncService {
	public function __construct(
		private EventMapper $eventMapper,
		private IClientService $clientService,
		private SettingsService $settings,
		private ModuleConfigService $moduleConfig,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Push event to the remote WordPress endpoint.
	 * @return array{ok: bool, error: ?string}
	 */
	public function push(Event $event): array {
		$remoteUrl = rtrim($this->moduleConfig->get('remote_url'), '/');
		if ($remoteUrl === '') {
			return $this->fail($event, 'Remote-URL ist nicht konfiguriert');
		}
		$secret = $this->settings->get('shared_secret');
		if ($secret === '') {
			return $this->fail($event, 'Shared-Secret ist nicht konfiguriert');
		}
		$payload = [
			'id' => $event->getId(),
			'title' => $event->getTitle(),
			'description' => $event->getDescription(),
			'start_date' => $event->getStartDate(),
			'end_date' => $event->getEndDate(),
			'location' => $event->getLocation(),
			'fee' => $event->getFee() !== null ? round((float)$event->getFee(), 2) : null,
			'capacity' => $event->getCapacity(),
			'reg_deadline' => $event->getRegDeadline(),
			'reg_fields' => json_decode((string)($event->getRegFields() ?? '[]'), true) ?: [],
		];
		$rawJson = (string)json_encode($payload, JSON_UNESCAPED_SLASHES);
		try {
			$client = new SyncClient($this->clientService, new Signer($secret));
			$res = $client->post($remoteUrl . '/wp-json/leiterrunde-anmeldung/v1/event', $rawJson);
			$data = json_decode($res['body'], true);
			if ($res['code'] >= 200 && $res['code'] < 300 && is_array($data)) {
				if (isset($data['remote_id'])) {
					$event->setRemoteId((string)$data['remote_id']);
				}
				$event->setStatus('published');
				$event->setLastPushedAt(time());
				$event->setLastSyncStatus('ok');
			$event->setLastPushedAt(time());
				$event->setSyncPending(false);
				$event->setUpdatedAt(time());
				$this->eventMapper->update($event);
				return ['ok' => true, 'error' => null];
			}
			return $this->fail($event, 'Sync-Fehler (HTTP ' . $res['code'] . ')');
		} catch (\Throwable $e) {
			$this->logger->warning('Leiterrunde Sync: ' . $e->getMessage());
			return $this->fail($event, 'Sync-Error: ' . $e->getMessage());
		}
	}

	private function fail(Event $event, string $message): array {
		$event->setLastSyncStatus('error: ' . $message);
		$event->setSyncPending(true);
		$event->setUpdatedAt(time());
		$this->eventMapper->update($event);
		return ['ok' => false, 'error' => $message];
	}

	/** Retry all sync_pending events (called by SyncRetryJob). */
	public function retryPending(): int {
		$n = 0;
		foreach ($this->eventMapper->findSyncPending() as $event) {
			try {
				$this->push($event);
				$n++;
			} catch (\Throwable $e) {
				$this->logger->warning('Leiterrunde SyncRetry: ' . $e->getMessage());
			}
		}
		return $n;
	}
}
