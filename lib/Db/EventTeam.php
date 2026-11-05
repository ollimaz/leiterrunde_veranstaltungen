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
 * @method int getPersonId()
 * @method void setPersonId(int $v)
 * @method ?string getRole()
 * @method void setRole(?string $v)
 */
class EventTeam extends BaseEntity {
	protected int $eventId = 0;
	protected int $personId = 0;
	protected ?string $role = null;

	public function __construct() {
		$this->addType('eventId', 'integer');
		$this->addType('personId', 'integer');
	}
}
