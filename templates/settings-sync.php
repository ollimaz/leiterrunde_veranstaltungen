<?php
declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

\OCP\Util::addStyle('leiterrunde', 'leiterrunde');
\OCP\Util::addScript('leiterrunde_veranstaltungen', 'settings');
?>
<div class="section lr" id="lr-veranstaltungen-settings">
	<h2><?php p($l->t('Events – Sync & registration')); ?></h2>
	<div id="lr-sync-form"></div>
	<button class="lr-btn" id="lr-sync-save"><?php p($l->t('Save')); ?></button>
	<button class="lr-btn secondary" id="lr-backfill"><?php p($l->t('Create missing payments')); ?></button>
	<span id="lr-sync-msg"></span>
</div>
