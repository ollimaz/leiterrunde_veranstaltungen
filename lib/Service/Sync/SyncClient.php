<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Service\Sync;

use OCA\Leiterrunde\Service\Sync\Signer;
use OCP\Http\Client\IClientService;

class SyncClient {
	public function __construct(
		private IClientService $clientService,
		private Signer $signer,
	) {
	}

	/**
	 * POST raw JSON with HMAC headers. Same string is signed and sent.
	 * @return array{code: int, body: string}
	 */
	public function post(string $url, string $rawJson): array {
		$ts = (string)time();
		$client = $this->clientService->newClient();
		$response = $client->post($url, [
			'timeout' => 15,
			'headers' => [
				'Content-Type' => 'application/json',
				'X-LR-Signature' => $this->signer->sign($rawJson, (int)$ts),
				'X-LR-Timestamp' => $ts,
			],
			'body' => $rawJson,
		]);
		return ['code' => $response->getStatusCode(), 'body' => (string)$response->getBody()];
	}
}
