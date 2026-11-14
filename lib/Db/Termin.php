<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Db;

use OCA\Leiterrunde\Db\BaseEntity;

/**
 * @method string getTitle()
 * @method void setTitle(string $v)
 * @method ?string getDescription()
 * @method void setDescription(?string $v)
 * @method ?string getLocation()
 * @method void setLocation(?string $v)
 * @method string getStartDate()
 * @method void setStartDate(string $v)
 * @method bool getAllDay()
 * @method void setAllDay(bool $v)
 * @method ?string getStartTime()
 * @method void setStartTime(?string $v)
 * @method ?string getEndTime()
 * @method void setEndTime(?string $v)
 * @method string getFreq()
 * @method void setFreq(string $v)
 * @method ?int getWeekday()
 * @method void setWeekday(?int $v)
 * @method ?int getMonthPos()
 * @method void setMonthPos(?int $v)
 * @method ?int getMonthDay()
 * @method void setMonthDay(?int $v)
 * @method ?string getRecurUntil()
 * @method void setRecurUntil(?string $v)
 * @method ?string getAudienceRoles()
 * @method void setAudienceRoles(?string $v)
 * @method ?string getAudiencePersons()
 * @method void setAudiencePersons(?string $v)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $v)
 */
class Termin extends BaseEntity {
	protected string $title = '';
	protected ?string $description = null;
	protected ?string $location = null;
	protected string $startDate = '';
	protected bool $allDay = true;
	protected ?string $startTime = null;
	protected ?string $endTime = null;
	protected string $freq = 'once';
	protected ?int $weekday = null;
	protected ?int $monthPos = null;
	protected ?int $monthDay = null;
	protected ?string $recurUntil = null;
	protected ?string $audienceRoles = '[]';
	protected ?string $audiencePersons = '[]';
	protected string $createdBy = '';
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		foreach (['weekday', 'monthPos', 'monthDay', 'createdAt', 'updatedAt'] as $p) {
			$this->addType($p, 'integer');
		}
		$this->addType('allDay', 'boolean');
	}
}
