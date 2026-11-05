# Leiterrunde – Veranstaltungen (Events)

Module app of the [Leiterrunde](https://github.com/ollimaz/leiterrunde) suite:
events with team, costs and registrations, synchronized with the public
website.

> **Requires** the core app [`leiterrunde`](https://github.com/ollimaz/leiterrunde)
> to be enabled first.

## Features

- **Events** — full event management with dates, location, fee, capacity, registration deadline and configurable registration fields
- **Team & costs** — team assignment with role checks (`team-check`) and cost tracking per event
- **Registrations** — inbound registration sync from the public website, verified by the core app's HMAC-signed sync endpoint
- **Outbound sync** — published events are pushed to the website (`POST <remote_url>/wp-json/leiterrunde-anmeldung/v1/event`, signed JSON payload); failed pushes are retried by `SyncRetryJob` (15 min)
- **Payment obligations** — registrations create/cancel obligations via the finances module (`IObligationProvider`) when it is active
- **Dashboard widget** — upcoming events on the Nextcloud dashboard

## Requirements

- Nextcloud 29 – 36
- Enabled [`leiterrunde`](https://github.com/ollimaz/leiterrunde) app
- For website sync: the `leiterrunde-anmeldung` WordPress plugin and a shared
  secret configured in the admin settings

## Installation

```bash
cd /path/to/nextcloud/apps
git clone https://github.com/ollimaz/leiterrunde_veranstaltungen.git
occ app:enable leiterrunde          # core, once
occ app:enable leiterrunde_veranstaltungen
```

## API

Base path `/apps/leiterrunde_veranstaltungen/api` — authentication as in the
core app (session + `requesttoken`, or HTTP Basic + `OCS-APIRequest: true`).

| Verb | Paths |
|---|---|
| GET | `/events`, `/events/team-roles`, `/events/{id}`, `/events/{id}/calculation`, `/events/{id}/team-check`, `/events/{id}/team`, `/events/{id}/costs`, `/registrations`, `/settings` |
| POST | `/events`, `/events/{id}/publish`, `/events/{id}/team`, `/events/{id}/costs`, `/backfill-obligations` |
| PUT | `/events/{id}`, `/registrations/{id}`, `/settings` |
| DELETE | `/events/{id}`, `/events/{id}/costs/{cost_id}`, `/events/{id}/team/{member_id}` |

The inbound sync endpoint itself lives in the core app:
`POST /apps/leiterrunde/api/sync` — see the
[core README](https://github.com/ollimaz/leiterrunde#sync-protocol) for the
payload and signature format.

## Localization

Source strings are English; the maintained translation is German (informal
"du"). Only `l10n/de.json` is maintained — the generated `de.js`,
`de_DE.json` and `de_DE.js` are built with
[`l10n-tools`](https://github.com/ollimaz/leiterrunde/tree/main/l10n-tools)
from the core app.

## Development

```bash
cd tests && phpunit -c phpunit.xml
```

## License

[AGPL-3.0-or-later](LICENSE)
