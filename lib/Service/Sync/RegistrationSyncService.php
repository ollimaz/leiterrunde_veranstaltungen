<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Service\Sync;

use OCA\Leiterrunde\Module\IObligationProvider;
use OCA\Leiterrunde\Module\ISyncHandler;
use OCA\Leiterrunde\Module\ModuleRegistry;
use OCA\LeiterrundeVeranstaltungen\Db\Registration;
use OCA\LeiterrundeVeranstaltungen\Db\RegistrationMapper;
use OCA\LeiterrundeVeranstaltungen\Db\EventMapper;

use OCA\Leiterrunde\Exception\ApiException;


class RegistrationSyncService implements ISyncHandler {
	public function __construct(
		private EventMapper $eventMapper,
		private RegistrationMapper $regMapper,
		private ModuleRegistry $modules,
	) {
	}

	/**
	 * Handle inbound sync action. Returns local registration id.
	 * @throws ApiException
	 */
	public function handle(array $payload): int {
		$action = $payload['action'] ?? null;
		$reg = $payload['registration'] ?? null;
		if (!is_array($reg)) {
			throw new ApiException('Invalid sync payload', 400);
		}
		$remoteRegId = isset($reg['id']) ? trim((string)$reg['id']) : '';
		if ($remoteRegId === '') {
			throw new ApiException('missing registration id', 400);
		}
		if (isset($reg['status']) && !in_array($reg['status'], ['active', 'cancelled'], true)) {
			throw new ApiException('invalid status', 400);
		}
		if (isset($reg['payment_state']) && !in_array($reg['payment_state'], ['open', 'paid', 'waived'], true)) {
			throw new ApiException('invalid payment_state', 400);
		}
		$event = isset($reg['event_id']) ? $this->eventMapper->findByRemoteId((string)$reg['event_id']) : null;
		if ($event === null) {
			throw new ApiException('unknown event', 404);
		}
		$existing = $this->regMapper->findByRemote($event->getId(), $remoteRegId);

		return match ($action) {
			'registration' => $this->upsertRegistration($event, $existing, $reg, $remoteRegId),
			'payment' => $this->handlePayment($event, $existing, $reg),
			'cancel' => $this->handleCancel($event, $existing, $reg),
			default => throw new ApiException('unknown action', 400),
		};
	}

	private function upsertRegistration($event, ?Registration $r, array $reg, ?string $remoteRegId): int {
		if ($r === null) {
			$r = new Registration();
			$r->setEventId($event->getId());
			$r->setRemoteRegId($remoteRegId);
			$r->setCreatedAt(time());
		}
		if (isset($reg['name'])) $r->setName((string)$reg['name']);
		if (array_key_exists('email', $reg)) $r->setEmail($reg['email']);
		if (array_key_exists('phone', $reg)) $r->setPhone($reg['phone']);
		if (array_key_exists('data', $reg)) $r->setData(is_string($reg['data']) ? $reg['data'] : json_encode($reg['data']));
		if (isset($reg['status'])) $r->setStatus((string)$reg['status']);
		if (isset($reg['payment_state'])) $r->setPaymentState((string)$reg['payment_state']);
		if (isset($reg['payment_ref'])) $r->setPaymentRef((string)$reg['payment_ref']);
		if (isset($reg['updated_at'])) $r->setRemoteUpdatedAt(self::parseTimestamp($reg['updated_at']));
		$r->setUpdatedAt(time());
		$r = $r->getId() === null ? $this->regMapper->insert($r) : $this->regMapper->update($r);

		if ($r->getStatus() === 'active' && $event->getFee() !== null && (float)$event->getFee() > 0) {
			$this->ensureObligation($event, $r);
		}
		return $r->getId();
	}

	private function ensureObligation($event, Registration $r): void {
		$provider = $this->modules->provider(IObligationProvider::class);
		if ($provider === null) {
			// Finanzen-Modul nicht aktiv: keine Zahlungsverpflichtung
			return;
		}
		$ref = $r->getPaymentRef();
		if ($ref === null || $ref === '') {
			$ref = 'EVT' . $event->getId() . '-REG' . $r->getRemoteRegId();
			$r->setPaymentRef($ref);
			$this->regMapper->update($r);
		}
		$oblId = $provider->ensure(
			$ref, $r->getName(), 'Teilnahmebeitrag: ' . $event->getTitle(),
			number_format((float)$event->getFee(), 2, '.', ''),
			$event->getRegDeadline() ?? $event->getStartDate(),
			$r->getId(), null
		);
		if ($r->getObligationId() !== $oblId) {
			$r->setObligationId($oblId);
			$this->regMapper->update($r);
		}
	}

	private function handlePayment($event, ?Registration $r, array $reg): int {
		if ($r === null) {
			throw new ApiException('unknown registration', 404);
		}
		if (isset($reg['payment_state'])) {
			$r->setPaymentState((string)$reg['payment_state']);
		}
		if (isset($reg['updated_at'])) $r->setRemoteUpdatedAt(self::parseTimestamp($reg['updated_at']));
		$r->setUpdatedAt(time());
		$this->regMapper->update($r);
		return $r->getId();
	}

	public static function parseTimestamp(mixed $v): ?int {
		if (is_int($v)) {
			return $v;
		}
		if (is_string($v)) {
			$v = trim($v);
			if (is_numeric($v)) {
				return (int)$v;
			}
			$t = strtotime($v);
			return $t === false ? null : $t;
		}
		return null;
	}

	private function handleCancel($event, ?Registration $r, array $reg): int {
		if ($r === null) {
			throw new ApiException('unknown registration', 404);
		}
		$r->setStatus('cancelled');
		$r->setUpdatedAt(time());
		$this->regMapper->update($r);
		if ($r->getObligationId() !== null) {
			$provider = $this->modules->provider(IObligationProvider::class);
			$provider?->cancelIfUnpaid($r->getObligationId());
		}
		return $r->getId();
	}
}
