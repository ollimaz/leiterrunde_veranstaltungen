<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Db;

use OCA\Leiterrunde\Db\BaseEntity;

/**
 * @method int getEventId()
 * @method void setEventId(int $v)
 * @method string getLabel()
 * @method void setLabel(string $v)
 * @method string getAmount()
 * @method void setAmount(string $v)
 * @method ?string getCategory()
 * @method void setCategory(?string $v)
 */
class EventCost extends BaseEntity {
	protected int $eventId = 0;
	protected string $label = '';
	protected string $amount = '0';
	protected ?string $category = null;

	public function __construct() {
		$this->addType('eventId', 'integer');
	}
}
