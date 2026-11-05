<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Tests\Unit;

use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\SettingsService;
use PHPUnit\Framework\TestCase;

class RegFieldsValidationTest extends TestCase {
	private SettingsService $settings;

	protected function setUp(): void {
		$this->settings = (new \ReflectionClass(SettingsService::class))->newInstanceWithoutConstructor();
	}

	private function valid(array $fields): string {
		return json_encode($fields);
	}

	public function testValidSchema(): void {
		$this->settings->validateRegFields($this->valid([
			['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
			['key' => 'ess', 'label' => 'Essen', 'type' => 'select', 'required' => false, 'options' => ['a', 'b']],
		]));
		$this->addToAssertionCount(1);
	}

	public function testInvalidJson(): void {
		$this->expectException(ApiException::class);
		$this->settings->validateRegFields('{not json');
	}

	public function testBadKey(): void {
		$this->expectException(ApiException::class);
		$this->settings->validateRegFields($this->valid([
			['key' => 'Bad Key!', 'label' => 'x', 'type' => 'text'],
		]));
	}

	public function testBadType(): void {
		$this->expectException(ApiException::class);
		$this->settings->validateRegFields($this->valid([
			['key' => 'x', 'label' => 'x', 'type' => 'checkbox'],
		]));
	}

	public function testSelectNeedsOptions(): void {
		$this->expectException(ApiException::class);
		$this->settings->validateRegFields($this->valid([
			['key' => 'x', 'label' => 'x', 'type' => 'select', 'options' => []],
		]));
	}
}
