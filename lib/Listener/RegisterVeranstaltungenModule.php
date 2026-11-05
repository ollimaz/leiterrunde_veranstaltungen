<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Listener;

use OCA\Leiterrunde\Module\ISyncHandler;
use OCA\Leiterrunde\Module\RegisterModulesEvent;
use OCA\LeiterrundeVeranstaltungen\Module\VeranstaltungenModule;
use OCA\LeiterrundeVeranstaltungen\Service\Sync\RegistrationSyncService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class RegisterVeranstaltungenModule implements IEventListener {
	public function __construct(
		private VeranstaltungenModule $module,
		private RegistrationSyncService $syncHandler,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof RegisterModulesEvent) {
			return;
		}
		$event->registerModule($this->module);
		$event->registerProvider(ISyncHandler::class, $this->syncHandler);
	}
}
