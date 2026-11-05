<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'Api\\Events#index', 'url' => '/api/events', 'verb' => 'GET'],
		['name' => 'Api\\Events#create', 'url' => '/api/events', 'verb' => 'POST'],
		['name' => 'Api\\Events#teamRoles', 'url' => '/api/events/team-roles', 'verb' => 'GET'],
		['name' => 'Api\\Events#show', 'url' => '/api/events/{id}', 'verb' => 'GET'],
		['name' => 'Api\\Events#update', 'url' => '/api/events/{id}', 'verb' => 'PUT'],
		['name' => 'Api\\Events#destroy', 'url' => '/api/events/{id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Events#publish', 'url' => '/api/events/{id}/publish', 'verb' => 'POST'],
		['name' => 'Api\\Events#calculation', 'url' => '/api/events/{id}/calculation', 'verb' => 'GET'],
		['name' => 'Api\\Events#teamCheck', 'url' => '/api/events/{id}/team-check', 'verb' => 'GET'],
		['name' => 'Api\\Events#team', 'url' => '/api/events/{id}/team', 'verb' => 'GET'],
		['name' => 'Api\\Events#teamAdd', 'url' => '/api/events/{id}/team', 'verb' => 'POST'],
		['name' => 'Api\\Events#teamRemove', 'url' => '/api/events/{id}/team/{member_id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Events#costs', 'url' => '/api/events/{id}/costs', 'verb' => 'GET'],
		['name' => 'Api\\Events#costAdd', 'url' => '/api/events/{id}/costs', 'verb' => 'POST'],
		['name' => 'Api\\Events#costRemove', 'url' => '/api/events/{id}/costs/{cost_id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Events#backfillObligations', 'url' => '/api/backfill-obligations', 'verb' => 'POST'],
		['name' => 'Api\\Registrations#index', 'url' => '/api/registrations', 'verb' => 'GET'],
		['name' => 'Api\\Registrations#update', 'url' => '/api/registrations/{id}', 'verb' => 'PUT'],
		['name' => 'Api\\Registrations#destroy', 'url' => '/api/registrations/{id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Settings#show', 'url' => '/api/settings', 'verb' => 'GET'],
		['name' => 'Api\\Settings#update', 'url' => '/api/settings', 'verb' => 'PUT'],
		['name' => 'Api\\Settings#regenerate_secret', 'url' => '/api/settings/secret/regenerate', 'verb' => 'POST'],
	],
];
