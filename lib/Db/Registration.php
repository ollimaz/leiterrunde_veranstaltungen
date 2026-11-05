<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Db;

use OCA\Leiterrunde\Db\BaseEntity;

/**
 * @method ?string getRemoteRegId()
 * @method void setRemoteRegId(?string $v)
 * @method int getEventId()
 * @method void setEventId(int $v)
 * @method string getName()
 * @method void setName(string $v)
 * @method ?string getEmail()
 * @method void setEmail(?string $v)
 * @method ?string getPhone()
 * @method void setPhone(?string $v)
 * @method ?string getData()
 * @method void setData(?string $v)
 * @method string getStatus()
 * @method void setStatus(string $v)
 * @method string getPaymentState()
 * @method void setPaymentState(string $v)
 * @method ?string getPaymentRef()
 * @method void setPaymentRef(?string $v)
 * @method ?int getRemoteUpdatedAt()
 * @method void setRemoteUpdatedAt(?int $v)
 * @method ?int getObligationId()
 * @method void setObligationId(?int $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $v)
 */
class Registration extends BaseEntity {
	protected ?string $remoteRegId = null;
	protected int $eventId = 0;
	protected string $name = '';
	protected ?string $email = null;
	protected ?string $phone = null;
	protected ?string $data = null;
	protected string $status = 'active';
	protected string $paymentState = 'open';
	protected ?string $paymentRef = null;
	protected ?int $remoteUpdatedAt = null;
	protected ?int $obligationId = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		foreach (['eventId', 'remoteUpdatedAt', 'obligationId', 'createdAt', 'updatedAt'] as $p) {
			$this->addType($p, 'integer');
		}
	}
}
