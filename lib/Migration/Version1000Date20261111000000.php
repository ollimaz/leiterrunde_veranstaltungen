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

class Version1000Date20261111000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('lr_termine')) {
			$table = $schema->createTable('lr_termine');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('location', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('start_date', Types::DATE, ['notnull' => true]);
			$table->addColumn('all_day', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$table->addColumn('start_time', Types::STRING, ['notnull' => false, 'length' => 5]);
			$table->addColumn('end_time', Types::STRING, ['notnull' => false, 'length' => 5]);
			$table->addColumn('freq', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'once']);
			$table->addColumn('weekday', Types::SMALLINT, ['notnull' => false]);
			$table->addColumn('month_pos', Types::SMALLINT, ['notnull' => false]);
			$table->addColumn('month_day', Types::SMALLINT, ['notnull' => false]);
			$table->addColumn('recur_until', Types::DATE, ['notnull' => false]);
			$table->addColumn('audience_roles', Types::TEXT, ['notnull' => false]);
			$table->addColumn('audience_persons', Types::TEXT, ['notnull' => false]);
			$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['start_date'], 'lr_term_start_idx');
		}

		return $schema;
	}
}
