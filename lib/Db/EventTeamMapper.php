<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @extends QBMapper<EventTeam> */
class EventTeamMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'lr_event_team', EventTeam::class);
	}

	public function find(int $id): EventTeam {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return EventTeam[] */
	public function findForEvent(int $eventId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('event_id', $qb->createNamedParameter($eventId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/** @return string[] distinct roles */
	public function distinctRoles(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('role')->from($this->getTableName())
			->where($qb->expr()->isNotNull('role'));
		return array_column($qb->executeQuery()->fetchAll(), 'role');
	}
}
