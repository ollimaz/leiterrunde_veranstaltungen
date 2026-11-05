<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\BackgroundJob;

use OCA\LeiterrundeVeranstaltungen\Service\Sync\EventSyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class SyncRetryJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private EventSyncService $syncService,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(15 * 60);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		try {
			$this->syncService->retryPending();
		} catch (\Throwable $e) {
			$this->logger->warning('Leiterrunde SyncRetryJob: ' . $e->getMessage());
		}
	}
}
