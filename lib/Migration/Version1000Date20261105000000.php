<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeVeranstaltungen\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20261105000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('lr_events')) {
			$table = $schema->createTable('lr_events');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('start_date', Types::DATE, ['notnull' => true]);
			$table->addColumn('end_date', Types::DATE, ['notnull' => false]);
			$table->addColumn('location', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('fee', Types::DECIMAL, ['notnull' => false, 'precision' => 12, 'scale' => 2]);
			$table->addColumn('capacity', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('reg_deadline', Types::DATE, ['notnull' => false]);
			$table->addColumn('reg_fields', Types::TEXT, ['notnull' => false]);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'draft']);
			$table->addColumn('remote_id', Types::STRING, ['notnull' => false, 'length' => 100]);
			$table->addColumn('last_pushed_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('last_sync_status', Types::TEXT, ['notnull' => false]);
			$table->addColumn('sync_pending', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
		}


		if (!$schema->hasTable('lr_event_team')) {
			$table = $schema->createTable('lr_event_team');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('event_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('person_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('role', Types::STRING, ['notnull' => false, 'length' => 200]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['event_id'], 'lr_eteam_evt_idx');
		}


		if (!$schema->hasTable('lr_event_costs')) {
			$table = $schema->createTable('lr_event_costs');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('event_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('label', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('amount', Types::DECIMAL, ['notnull' => true, 'precision' => 12, 'scale' => 2]);
			$table->addColumn('category', Types::STRING, ['notnull' => false, 'length' => 100]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['event_id'], 'lr_ecost_evt_idx');
		}


		if (!$schema->hasTable('lr_registrations')) {
			$table = $schema->createTable('lr_registrations');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('remote_reg_id', Types::STRING, ['notnull' => false, 'length' => 100]);
			$table->addColumn('event_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('email', Types::STRING, ['notnull' => false, 'length' => 320]);
			$table->addColumn('phone', Types::STRING, ['notnull' => false, 'length' => 100]);
			$table->addColumn('data', Types::TEXT, ['notnull' => false]);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'active']);
			$table->addColumn('payment_state', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'open']);
			$table->addColumn('payment_ref', Types::STRING, ['notnull' => false, 'length' => 200]);
			$table->addColumn('remote_updated_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('obligation_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['event_id', 'remote_reg_id'], 'lr_reg_uniq');
		}


		return $schema;
	}
}
