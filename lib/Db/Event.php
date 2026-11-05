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
 * @method string getStartDate()
 * @method void setStartDate(string $v)
 * @method ?string getEndDate()
 * @method void setEndDate(?string $v)
 * @method ?string getLocation()
 * @method void setLocation(?string $v)
 * @method ?string getFee()
 * @method void setFee(?string $v)
 * @method ?int getCapacity()
 * @method void setCapacity(?int $v)
 * @method ?string getRegDeadline()
 * @method void setRegDeadline(?string $v)
 * @method ?string getRegFields()
 * @method void setRegFields(?string $v)
 * @method string getStatus()
 * @method void setStatus(string $v)
 * @method ?string getRemoteId()
 * @method void setRemoteId(?string $v)
 * @method ?int getLastPushedAt()
 * @method void setLastPushedAt(?int $v)
 * @method ?string getLastSyncStatus()
 * @method void setLastSyncStatus(?string $v)
 * @method bool getSyncPending()
 * @method void setSyncPending(bool $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $v)
 */
class Event extends BaseEntity {
	protected string $title = '';
	protected ?string $description = null;
	protected string $startDate = '';
	protected ?string $endDate = null;
	protected ?string $location = null;
	protected ?string $fee = null;
	protected ?int $capacity = null;
	protected ?string $regDeadline = null;
	protected ?string $regFields = null;
	protected string $status = 'draft';
	protected ?string $remoteId = null;
	protected ?int $lastPushedAt = null;
	protected ?string $lastSyncStatus = null;
	protected bool $syncPending = false;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		foreach (['capacity', 'lastPushedAt', 'createdAt', 'updatedAt'] as $p) {
			$this->addType($p, 'integer');
		}
		$this->addType('syncPending', 'boolean');
	}
}
