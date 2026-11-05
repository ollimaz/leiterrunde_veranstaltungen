<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Controller\Api;

use OCA\Leiterrunde\Controller\Api\ApiController;
use OCA\Leiterrunde\AppInfo\Application;
use OCA\LeiterrundeVeranstaltungen\Db\RegistrationMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\AuditService;
use OCA\Leiterrunde\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class RegistrationsController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private RegistrationMapper $mapper,
		private AuditService $audit,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(?int $event_id = null): JSONResponse {
		return $this->wrap(function () use ($event_id) {
			$this->perm->require('registrations.read');
			if ($event_id === null) {
				throw new ApiException('event_id ist erforderlich', 400);
			}
			return array_map(static fn ($r) => $r->jsonSerialize(), $this->mapper->findForEvent($event_id));
		});
	}

	#[NoAdminRequired]
	public function update(int $id, ?string $status = null, ?string $payment_state = null): JSONResponse {
		return $this->wrap(function () use ($id, $status, $payment_state) {
			$this->perm->require('registrations.read');
			$this->perm->require('events.write');
			$r = $this->mapper->find($id);
			$before = $r->jsonSerialize();
			if ($status !== null) {
				if (!in_array($status, ['active', 'cancelled'], true)) {
					throw new ApiException('Invalid status', 422);
				}
				$r->setStatus($status);
			}
			if ($payment_state !== null) {
				if (!in_array($payment_state, ['open', 'paid', 'waived'], true)) {
					throw new ApiException('Invalid payment status', 422);
				}
				$r->setPaymentState($payment_state);
			}
			$r->setUpdatedAt(time());
			$r = $this->mapper->update($r);
			$this->audit->diff('registration', $r->getId(), $before, $r->jsonSerialize());
			return $r->jsonSerialize();
		});
	}
}
