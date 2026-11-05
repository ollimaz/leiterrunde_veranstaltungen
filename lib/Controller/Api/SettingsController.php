<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Controller\Api;

use OCA\Leiterrunde\Controller\Api\ApiController;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\Leiterrunde\Service\SettingsService;
use OCA\LeiterrundeVeranstaltungen\AppInfo\Application;
use OCA\LeiterrundeVeranstaltungen\Service\ModuleConfigService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class SettingsController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private ModuleConfigService $config,
		private SettingsService $coreSettings,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('settings.manage');
			return array_merge($this->config->all(), [
				'shared_secret' => $this->coreSettings->get('shared_secret'),
			]);
		});
	}

	#[NoAdminRequired]
	public function update(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('settings.manage');
			foreach ($this->request->getParams() as $k => $v) {
				if (str_starts_with((string)$k, "_")) { continue; }
				if ($k === 'remote_url') {
					$this->config->set('remote_url', (string)$v);
				} elseif ($k === 'default_reg_fields') {
					$this->coreSettings->validateRegFields((string)$v);
					$this->config->set('default_reg_fields', (string)$v);
				} else {
					throw new ApiException('Unbekannte Einstellung: ' . $k, 400);
				}
			}
			return $this->config->all();
		});
	}

	#[NoAdminRequired]
	public function regenerateSecret(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('settings.manage');
			return ['shared_secret' => $this->coreSettings->regenerateSharedSecret()];
		});
	}
}
