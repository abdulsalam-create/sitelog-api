# SiteLog API Reference

Complete documentation for the SiteLog API. Every endpoint, its permissions, request fields, example bodies and response shapes are here.

- Base URL: `http://sitelog-api.test` locally, or `http://127.0.0.1:8000` with `php artisan serve`
- All paths are prefixed with `/api/v1`
- All endpoints except `POST /auth/login` require the header `Authorization: Bearer <token>`

## Table of contents

- [Authentication](#authentication)
- [Conventions](#conventions)
- [Regions](#regions)
- [Sites](#sites)
- [Visit reports](#visit-reports)
- [Faults](#faults)
- [Photos](#photos)
- [Notifications](#notifications)
- [Summary endpoints](#summary-endpoints)
- [Error reference](#error-reference)
- [Enum values](#enum-values)

## Authentication

### Login

`POST /api/v1/auth/login`

Public. Rate limited to 5 attempts per minute per client.

Request:

```json
{
  "email": "admin@sitelog.test",
  "password": "password"
}
```

Response 200:

```json
{
  "data": {
    "token": "1|abcdef...",
    "user": {
      "id": 1,
      "name": "SiteLog Admin",
      "email": "admin@sitelog.test",
      "role": "admin",
      "phone": "...",
      "staff_id": "MTN-00001",
      "created_at": "2026-11-19T20:08:56.000000Z"
    }
  }
}
```

Errors: 422 on missing or malformed fields, 422 with an `email` error when the credentials are wrong (deliberately the same shape as a missing field, so the API does not reveal which emails exist), 429 after five failed attempts in a minute.

### Current user

`GET /api/v1/auth/me`

Returns the authenticated user, same shape as the `user` object above. 401 when the token is missing or invalid.

### Logout

`POST /api/v1/auth/logout`

Revokes the current token immediately. The same token gets a 401 on the next request. Response 200:

```json
{ "message": "Logged out." }
```

## Conventions

**Response shapes.** Single records are `{ "data": { ... } }`. Lists are `{ "data": [...], "links": {...}, "meta": {...} }` with standard Laravel pagination. Errors are `{ "message": "...", "errors": { "field": ["..."] } }` for validation and `{ "message": "..." }` otherwise. Every response, including a 405 for a wrong verb, is JSON.

**Roles and visibility.** Admin sees everything. A regional manager sees only sites, reports and faults in the regions they manage. A field engineer sees only sites they are assigned to, and the reports and faults on those sites. The list endpoints never return rows outside the caller's scope.

**Status values.** All enum fields accept only the values listed at the bottom of this document. Anything else is a 422.

## Regions

### List regions

`GET /api/v1/regions`

Any authenticated user. Returns all regions with their manager and a site count, paginated.

Response:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Lagos West",
      "code": "LAG",
      "manager": { "id": 2, "name": "...", "email": "manager1@sitelog.test", "role": "manager", "...": "..." },
      "sites_count": 9,
      "created_at": "2026-11-19T20:08:56.000000Z"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "from": 1, "last_page": 1, "per_page": 15, "to": 3, "total": 3 }
}
```

### Create a region

`POST /api/v1/regions`

Admin only, 403 otherwise.

Request:

```json
{
  "name": "Lagos West",
  "code": "lag",
  "manager_id": 2
}
```

`manager_id` is optional, but when present it must be the id of a user with the manager role, 422 otherwise. The code is uppercased automatically, so `lag` is stored and returned as `LAG`. Response is 201 with the created region.

## Sites

### List sites

`GET /api/v1/sites`

Scoped by role: engineers get assigned sites, managers get their regions' sites, admins get everything.

Query parameters, all optional:

| Parameter | Values |
|---|---|
| `region_id` | region id |
| `status` | `active`, `under_maintenance`, `decommissioned` |
| `power_source` | `grid`, `generator`, `hybrid`, `solar` |
| `site_type` | `greenfield`, `rooftop`, `indoor` |
| `search` | matches site code or name, case insensitive substring |
| `per_page` | 1 to 50, default 15 |

Response:

```json
{
  "data": [
    {
      "id": 1,
      "site_code": "LAG-0001",
      "name": "Ikeja Site",
      "region": { "id": 1, "name": "Lagos West", "code": "LAG", "manager": { "...": "..." } },
      "address": "...",
      "latitude": "6.6018000",
      "longitude": "3.3515000",
      "site_type": "greenfield",
      "power_source": "hybrid",
      "status": "active",
      "engineers": [ { "id": 3, "name": "...", "...": "..." } ],
      "created_at": "2026-11-19T20:08:56.000000Z"
    }
  ],
  "links": { "...": "..." },
  "meta": { "...": "..." }
}
```

### Create a site

`POST /api/v1/sites`

Admin only.

Request:

```json
{
  "site_code": "lag-0001",
  "name": "Ikeja Central",
  "region_id": 1,
  "address": "12 Allen Avenue",
  "latitude": 6.6018,
  "longitude": 3.3515,
  "site_type": "greenfield",
  "power_source": "hybrid"
}
```

All fields required except `status`, which defaults to `active`. Site code is uppercased automatically. Latitude must be between -90 and 90, longitude between -180 and 180. Response 201.

### Show a site

`GET /api/v1/sites/{site}`

403 when the caller has no access to that site (for example an engineer who is not assigned there).

### Update a site

`PUT /api/v1/sites/{site}`

Admin or the site's regional manager. All fields optional, only sent fields change. A manager cannot move a site into a region they do not manage, that is a 422 on `region_id`.

### Decommission a site

`DELETE /api/v1/sites/{site}`

Admin or the site's regional manager. This does not delete the row. It sets `status` to `decommissioned` and returns the updated site, so report and fault history is preserved.

### Assign an engineer

`POST /api/v1/sites/{site}/engineers/{user}`

Admin or the site's regional manager. The target user must have the engineer role, 422 otherwise. Assigning someone already assigned is a no-op. Response 201 with the updated site including its `engineers` array.

### Remove an engineer

`DELETE /api/v1/sites/{site}/engineers/{user}`

Admin or the site's regional manager. Returns the updated site. The engineer's existing reports are not touched.

## Visit reports

### List reports for a site

`GET /api/v1/sites/{site}/reports`

Requires access to the site. Supports the same filters as the global list below (except `site_id`, which is fixed), plus `per_page`.

### List reports

`GET /api/v1/reports`

Scoped by role like sites. Query parameters, all optional:

| Parameter | Values |
|---|---|
| `site_id` | site id |
| `region_id` | region id |
| `engineer_id` | user id |
| `visit_type` | `routine`, `corrective`, `emergency` |
| `status` | `draft`, `submitted`, `approved`, `rejected` |
| `from` | date `Y-m-d`, inclusive, on `visit_date` |
| `to` | date `Y-m-d`, inclusive |
| `per_page` | 1 to 50, default 15 |

Response, one item:

```json
{
  "id": 5,
  "site": { "id": 1, "site_code": "LAG-0001", "name": "Ikeja Central" },
  "engineer": { "id": 3, "name": "...", "email": "engineer1@sitelog.test", "...": "..." },
  "visit_date": "2026-11-21",
  "visit_type": "routine",
  "grid_power_available": true,
  "generator_hours": "5.50",
  "fuel_level_percent": 65,
  "battery_health": "good",
  "work_summary": "Routine inspection. Topped up diesel.",
  "status": "submitted",
  "reviewed_by": null,
  "reviewed_at": null,
  "review_comment": null,
  "reviewer": null,
  "maintenance_items": [
    { "id": 9, "item_name": "diesel", "action": "refilled", "quantity": "20.00", "unit": "litres" }
  ],
  "photos": [ { "id": 2, "path": "photos/x.jpg", "caption": "Meter reading", "uploaded_by": 3, "created_at": "..." } ],
  "created_at": "2026-11-22T10:00:00.000000Z"
}
```

`reviewer` is filled once the report has been approved or rejected, along with `reviewed_by`, `reviewed_at` and `review_comment`.

### Create a report

`POST /api/v1/sites/{site}/reports`

The caller must be an engineer assigned to that site. The report is always created as `draft`, there is no way to post a status.

Request:

```json
{
  "visit_date": "2026-11-21",
  "visit_type": "routine",
  "grid_power_available": true,
  "generator_hours": 5.5,
  "fuel_level_percent": 65,
  "battery_health": "good",
  "work_summary": "Routine inspection. Topped up diesel.",
  "maintenance_items": [
    { "item_name": "diesel", "action": "refilled", "quantity": 20, "unit": "litres" }
  ]
}
```

`maintenance_items` is optional but must be an array when present. Every item needs all four fields. The whole report and its items are written in one transaction.

Validation: `visit_date` cannot be in the future, `fuel_level_percent` must be between 0 and 100, and the same engineer cannot log the same site twice on one date. That duplicate returns 422 with the message "You already logged a report for this site on that date." on the `visit_date` field. A database unique index backs the same rule against simultaneous requests.

If the report records fuel below 20%, the regional manager is notified automatically.

### Show a report

`GET /api/v1/reports/{report}`

Scoped like the lists, 403 outside your scope.

### Update a report

`PUT /api/v1/reports/{report}`

Only the report's own engineer, and only while the report is `draft` or `rejected`. Partial updates, same rules as create for the fields sent. When `maintenance_items` is sent it replaces the whole set in one transaction. 403 once the report is submitted or approved.

### Delete a report

`DELETE /api/v1/reports/{report}`

Same restriction as update. Its items and photos are removed with it. Response: `{ "message": "Report deleted." }`

### Submit

`POST /api/v1/reports/{report}/submit`

The owner, from `draft` or `rejected`. Moves the report to `submitted` and notifies the site's regional manager. No body.

### Approve

`POST /api/v1/reports/{report}/approve`

Admin or the site's regional manager, and only on a `submitted` report. Body is optional, may carry a comment:

```json
{ "review_comment": "Looks good" }
```

Moves the report to `approved`, records the reviewer and timestamp, and notifies the engineer.

### Reject

`POST /api/v1/reports/{report}/reject`

Same actors as approve. The comment is required, 422 without it.

```json
{ "review_comment": "Fuel reading does not match the photo." }
```

Moves the report to `rejected`. The engineer can now edit and resubmit it.

## Faults

### List faults for a site

`GET /api/v1/sites/{site}/faults`

Requires access to the site. Same filters as the global list, except `site_id`.

### List faults

`GET /api/v1/faults`

Scoped by role. Query parameters, all optional:

| Parameter | Values |
|---|---|
| `severity` | `low`, `medium`, `high`, `critical` |
| `category` | `power`, `transmission`, `equipment`, `security`, `civil` |
| `status` | `open`, `in_progress`, `resolved`, `closed` |
| `region_id` | region id |
| `assigned_to` | engineer user id |
| `sla_breached` | `1` or `true`, returns only open faults whose deadline has passed |
| `per_page` | 1 to 50, default 15 |

Response, one item:

```json
{
  "id": 7,
  "site": { "id": 1, "site_code": "LAG-0001", "name": "Ikeja Central" },
  "raiser": { "id": 3, "name": "...", "...": "..." },
  "assignee": { "id": 4, "name": "...", "...": "..." },
  "title": "Generator down",
  "description": "Generator failed to start during checks.",
  "category": "power",
  "severity": "critical",
  "status": "open",
  "sla_due_at": "2026-11-22T15:00:00.000000Z",
  "resolved_at": null,
  "sla_breached": false,
  "photos": [],
  "created_at": "2026-11-22T11:00:00.000000Z"
}
```

`sla_breached` is computed per fault: true when the deadline has passed and the fault is still `open` or `in_progress`. `assignee` is null until a manager assigns the fault.

### Raise a fault

`POST /api/v1/sites/{site}/faults`

The assigned engineer, the site's regional manager, or an admin. Response 201, status forced to `open`.

Request:

```json
{
  "title": "Generator down",
  "description": "Generator failed to start during checks.",
  "category": "power",
  "severity": "critical"
}
```

`sla_due_at` is set automatically from the severity:

| Severity | Deadline |
|---|---|
| critical | 4 hours |
| high | 24 hours |
| medium | 72 hours |
| low | 7 days |

A critical fault also triggers an immediate notification to the regional manager, queued for delivery.

### Show a fault

`GET /api/v1/faults/{fault}`

Scoped like the lists.

### Update a fault

`PUT /api/v1/faults/{fault}`

Partial updates of `title`, `description`, `category` and `severity`. The raiser may edit these while the fault is `open`. Admins and the site's regional manager may edit any time.

`assigned_to` assigns the fault and is for admins and the site's regional manager only. The target must have the engineer role and must already be assigned to the fault's site, otherwise 422 on `assigned_to`.

### Change status

`PATCH /api/v1/faults/{fault}/status`

Request:

```json
{ "status": "in_progress" }
```

The state machine is strict:

| Current | Allowed next |
|---|---|
| open | in_progress |
| in_progress | resolved |
| resolved | closed, open (reopen) |
| closed | none |

An illegal jump returns 422 with a message naming the move, for example "Cannot move a fault from open to resolved."

The assigned engineer may walk the chain forward one step at a time. Admins and the site's regional manager may also close and reopen. Setting `resolved` records `resolved_at` automatically. Reopening clears it and sets a fresh `sla_due_at` from the severity, so a reopened fault is not instantly breached.

## Photos

### Upload photos

`POST /api/v1/{type}/{id}/photos`

`{type}` is either `reports` or `faults`, anything else 404s. The caller must have access to the parent: the assigned engineer, the site's regional manager, or an admin.

Multipart form request, key `photos[]` for the files, optional `caption`:

```
curl -X POST http://127.0.0.1:8000/api/v1/reports/5/photos \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json" \
  -F "photos[]=@meter.jpg" \
  -F "photos[]=@panel.jpg" \
  -F "caption=Meter reading"
```

Rules: images only, jpg, png or webp, each file 5 MB or less, and at most 10 photos per report or fault in total. The limit counts what is already stored plus the new batch, and an over-limit upload is rejected as a whole with a 422.

Response 201:

```json
{
  "data": [
    { "id": 12, "path": "photos/abc123.jpg", "caption": "Meter reading", "uploaded_by": 3, "created_at": "..." }
  ]
}
```

Files land on the `public` disk, which `.env` can point anywhere (S3 later). With `php artisan storage:link` they are served from `/storage/photos/...`.

### Delete a photo

`DELETE /api/v1/photos/{photo}`

The uploader, an admin, or the site's regional manager. Deletes the row and the file from disk in the same request. Response: `{ "message": "Photo deleted." }`

## Notifications

Notifications are database rows plus queued emails. They exist for: report submitted (to the regional manager), report approved or rejected (to the engineer), critical fault raised (to the regional manager), fuel below 20% (to the regional manager), and SLA breaches (to the regional manager, sent by the hourly scheduler).

### List notifications

`GET /api/v1/notifications`

The current user's notifications, newest first, paginated.

Response, one item:

```json
{
  "id": "uuid",
  "type": "critical_fault",
  "message": "A critical fault was raised on LAG-0001: Generator down.",
  "data": { "type": "critical_fault", "message": "...", "fault_id": 7, "site_id": 1 },
  "read_at": null,
  "created_at": "2026-11-22T11:00:00.000000Z"
}
```

### Mark read

`POST /api/v1/notifications/{notification}/read`

Marks that notification read and returns it with `read_at` filled. 404 when the id belongs to someone else or does not exist.

## Summary endpoints

### Site summary

`GET /api/v1/sites/{site}/summary`

Anyone who can view the site. Everything is computed in SQL, not by loading rows into PHP.

```json
{
  "data": {
    "total_visits": 5,
    "last_visit_date": "2026-11-21",
    "average_fuel_level_last_7_days": 52.5,
    "open_faults_by_severity": { "critical": 1, "high": 1 },
    "sla_breached_faults": 1,
    "grid_outages_last_30_days": 1
  }
}
```

`average_fuel_level_last_7_days` covers reports from the last 7 days and is rounded to 2 decimals. `grid_outages_last_30_days` counts reports where grid power was unavailable.

### Region summary

`GET /api/v1/regions/{region}/summary`

Admin or that region's manager, 403 otherwise.

```json
{
  "data": {
    "sites_by_status": { "active": 8, "decommissioned": 1 },
    "open_faults_by_category": { "power": 3, "civil": 1 },
    "sla_compliance_rate": 87.5,
    "top_5_sites_by_faults": [
      { "site": { "id": 1, "site_code": "LAG-0001", "name": "Ikeja Central" }, "faults": 4 }
    ]
  }
}
```

`sla_compliance_rate` is faults resolved within their SLA divided by total resolved, as a percentage rounded to 2 decimals, and `null` when nothing has been resolved yet. `top_5_sites_by_faults` counts faults from the last 30 days.

## Error reference

| Code | When | Body |
|---|---|---|
| 401 | Missing or invalid token | `{ "message": "Unauthenticated." }` |
| 403 | Signed in but not allowed | `{ "message": "This action is unauthorized." }` |
| 404 | Unknown resource, or a parent type outside `reports`/`faults` | `{ "message": "..." }` |
| 405 | Wrong verb for the URL | `{ "message": "The GET method is not supported for route api/v1/auth/login. Supported methods: POST." }` |
| 422 | Validation failed | `{ "message": "The fuel level percent field is required. (and 1 more error)", "errors": { "fuel_level_percent": ["..."] } }` |
| 429 | Login throttled | `{ "message": "Too Many Attempts." }` |

All error responses are JSON, including when the client sends no Accept header.

## Enum values

| Field | Values |
|---|---|
| `role` | `admin`, `manager`, `engineer` |
| `site_type` | `greenfield`, `rooftop`, `indoor` |
| `power_source` | `grid`, `generator`, `hybrid`, `solar` |
| site `status` | `active`, `under_maintenance`, `decommissioned` |
| `visit_type` | `routine`, `corrective`, `emergency` |
| `battery_health` | `good`, `fair`, `poor` |
| report `status` | `draft`, `submitted`, `approved`, `rejected` |
| maintenance `action` | `refilled`, `replaced`, `repaired`, `inspected` |
| maintenance `unit` | `litres`, `pieces` |
| fault `category` | `power`, `transmission`, `equipment`, `security`, `civil` |
| fault `severity` | `low`, `medium`, `high`, `critical` |
| fault `status` | `open`, `in_progress`, `resolved`, `closed` |

## Demo accounts

All seeded passwords are `password`. `admin@sitelog.test`, `manager1` through `manager3`, `engineer1` through `engineer8`, all at `@sitelog.test`.
