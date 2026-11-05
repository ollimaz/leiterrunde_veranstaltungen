<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Service;

use OCA\LeiterrundeVeranstaltungen\AppInfo\Application;
use OCP\IAppConfig;

class ModuleConfigService {
	public function __construct(private IAppConfig $appConfig) {
	}

	public function get(string $key, string $default = ''): string {
		$v = $this->appConfig->getValueString(Application::APP_ID, $key, '');
		return $v === '' ? $default : $v;
	}

	public function set(string $key, string $value): void {
		$this->appConfig->setValueString(Application::APP_ID, $key, $value);
	}

	public function all(): array {
		return [
			'remote_url' => $this->get('remote_url'),
			'default_reg_fields' => $this->get('default_reg_fields', '[]'),
		];
	}
}
