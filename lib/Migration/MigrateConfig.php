<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Migration;

use OCA\LeiterrundeVeranstaltungen\AppInfo\Application;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class MigrateConfig implements IRepairStep {
	private const KEYS = ['remote_url', 'default_reg_fields'];

	public function __construct(private IAppConfig $appConfig) {
	}

	public function getName(): string {
		return 'Leiterrunde Events: migrate settings';
	}

	public function run(IOutput $output): void {
		foreach (self::KEYS as $key) {
			if ($this->appConfig->getValueString(Application::APP_ID, $key, '') !== '') {
				continue;
			}
			$old = $this->appConfig->getValueString('leiterrunde', $key, '');
			if ($old !== '') {
				$this->appConfig->setValueString(Application::APP_ID, $key, $old);
			}
		}
	}
}
