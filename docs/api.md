# SiteLog API

## Overview

SiteLog is a backend API for logging maintenance visits and tracking faults on network cell sites. Field engineers visit sites and record what they found: power status, generator run hours, fuel level, battery health, the work they did, and anything that is broken. Regional managers review those reports, approve or reject them, assign engineers to sites and to faults, and watch SLA deadlines. Everything is exposed as a REST API so a web dashboard or a mobile app can be built on top of it later.

The system is built for three roles, and every permission decision in the API flows from this table.

| Role | Scope of access | Typical actions |
|---|---|---|
| Admin | Everything | Create users, regions and sites. Read all data. Approve any report |
| Regional manager | The regions they manage | Edit sites in their regions. Assign engineers. Approve or reject visit reports. Assign and close faults |
| Field engineer | The sites they are assigned to | Create and edit their own visit reports. Raise faults. Upload photos |

A typical day flows through the API like this:

1. An engineer visits a site and creates a draft report with the readings and any maintenance work done
2. The engineer submits the report, which notifies the site's regional manager
3. The manager approves it, or rejects it with a comment, which the engineer can act on and resubmit
4. Faults are raised when something is broken. A fault gets a deadline based on how severe it is
5. Managers assign faults to engineers, who walk them through in_progress to resolved, and managers close them
6. Critical faults and low fuel readings alert the manager immediately, and an hourly job chases SLA breaches

## Getting started

### Requirements

- PHP 8.2 or newer (developed on 8.3)
- MySQL 8
- Composer

### Setup

```
git clone <repo url>
cd sitelog-api
composer install
cp .env.example .env
```

Create a MySQL database called `sitelog` (or point `DB_DATABASE` in `.env` at any database), then:

```
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

`migrate --seed` loads the fictional dataset: 12 users (1 admin, 3 regional managers, 8 field engineers), 3 regions, 25 sites, 60 visit reports and 30 faults.

### Running the app

```
php artisan serve        # the API on http://127.0.0.1:8000
php artisan queue:work   # delivers queued notifications
php artisan schedule:work # runs the hourly SLA breach check
```

### Demo accounts

All seeded passwords are `password`.

| Role | Email |
|---|---|
| Admin | `admin@sitelog.test` |
| Regional managers | `manager1@sitelog.test` through `manager3@sitelog.test` |
| Field engineers | `engineer1@sitelog.test` through `engineer8@sitelog.test` |

### Talking to the API

Every request except login carries the token from `POST /api/v1/auth/login`:

```
Authorization: Bearer <token>
Accept: application/json
```

## Conventions

Every response follows one envelope, so clients only ever learn three shapes:

```json
{ "data": { } }
```

```json
{ "data": [ ], "links": { "first": null, "last": null, "prev": null, "next": null }, "meta": { "current_page": 1, "per_page": 15, "total": 3 } }
```

```json
{ "message": "This action is unauthorized.", "errors": { "field": [ "..." ] } }
```

Single records are `data`. Lists are `data` plus standard Laravel pagination in `links` and `meta`. Errors are `message`, plus a field-level `errors` object when validation failed.

Every list endpoint is paginated (default 15 per page, 50 max) and role-scoped: the response never contains rows the caller is not allowed to see. Admin sees everything, a manager sees their regions, an engineer sees their assigned sites.

All enum fields accept only the values in the tables at the end of this document. Anything else is a 422. All responses are JSON, including errors like 405 for a wrong verb, even when the client sent no Accept header.

## Endpoint index

| Area | Method | Path | Purpose |
|---|---|---|---|
| Auth | POST | `/api/v1/auth/login` | Get a bearer token. Public, 5 attempts per minute |
| Auth | POST | `/api/v1/auth/logout` | Revoke the current token |
| Auth | GET | `/api/v1/auth/me` | Current user profile |
| Regions | GET | `/api/v1/regions` | List regions |
| Regions | POST | `/api/v1/regions` | Create a region. Admin |
| Regions | GET | `/api/v1/regions/{region}/summary` | Region dashboard figures |
| Sites | GET | `/api/v1/sites` | List sites with filters and search |
| Sites | POST | `/api/v1/sites` | Create a site. Admin |
| Sites | GET | `/api/v1/sites/{site}` | Show a site |
| Sites | PUT | `/api/v1/sites/{site}` | Update a site |
| Sites | DELETE | `/api/v1/sites/{site}` | Decommission a site |
| Sites | POST | `/api/v1/sites/{site}/engineers/{user}` | Assign an engineer |
| Sites | DELETE | `/api/v1/sites/{site}/engineers/{user}` | Remove an engineer |
| Sites | GET | `/api/v1/sites/{site}/summary` | Site health figures |
| Reports | GET | `/api/v1/sites/{site}/reports` | Reports for one site |
| Reports | POST | `/api/v1/sites/{site}/reports` | Create a draft report |
| Reports | GET | `/api/v1/reports` | List reports with filters |
| Reports | GET | `/api/v1/reports/{report}` | Show a report |
| Reports | PUT | `/api/v1/reports/{report}` | Update a draft or rejected report |
| Reports | DELETE | `/api/v1/reports/{report}` | Delete a draft or rejected report |
| Reports | POST | `/api/v1/reports/{report}/submit` | Submit for review |
| Reports | POST | `/api/v1/reports/{report}/approve` | Approve. Manager or admin |
| Reports | POST | `/api/v1/reports/{report}/reject` | Reject with comment. Manager or admin |
| Faults | GET | `/api/v1/sites/{site}/faults` | Faults for one site |
| Faults | POST | `/api/v1/sites/{site}/faults` | Raise a fault |
| Faults | GET | `/api/v1/faults` | List faults with filters |
| Faults | GET | `/api/v1/faults/{fault}` | Show a fault |
| Faults | PUT | `/api/v1/faults/{fault}` | Update a fault or assign it |
| Faults | PATCH | `/api/v1/faults/{fault}/status` | Move the fault along its state machine |
| Photos | POST | `/api/v1/{type}/{id}/photos` | Upload photos. Type is `reports` or `faults` |
| Photos | DELETE | `/api/v1/photos/{photo}` | Delete a photo and its file |
| Notifications | GET | `/api/v1/notifications` | The current user's notifications |
| Notifications | POST | `/api/v1/notifications/{id}/read` | Mark one notification read |

## Authentication

### Login

`POST /api/v1/auth/login`

Public. Rate limited to 5 attempts per minute per client, after which it returns 429 until the minute passes.

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

Errors: 422 on missing or malformed fields, and 422 with an `email` error when the credentials are wrong. The wrong-credentials response deliberately looks like a validation failure, so the API does not reveal whether an email address exists. Passwords are stored bcrypt-hashed and the token itself is stored hashed; the plain token is returned exactly once.

### Current user

`GET /api/v1/auth/me`

Returns the authenticated user, same shape as the `user` object above. 401 when the token is missing, expired or invalid.

### Logout

`POST /api/v1/auth/logout`

Revokes the current token immediately. The same token gets a 401 on the very next request. Response 200:

```json
{ "message": "Logged out." }
```

## Regions

### List regions

`GET /api/v1/regions`

Any authenticated user. Returns all regions with their manager and a site count, paginated.

```json
{
  "data": [
    {
      "id": 1,
      "name": "Lagos West",
      "code": "LAG",
      "manager": { "id": 2, "name": "...", "email": "manager1@sitelog.test", "role": "manager" },
      "sites_count": 9,
      "created_at": "2026-11-19T20:08:56.000000Z"
    }
  ],
  "links": { "first": null, "last": null, "prev": null, "next": null },
  "meta": { "current_page": 1, "per_page": 15, "total": 3 }
}
```

### Create a region

`POST /api/v1/regions`

Admin only, 403 for everyone else.

Request:

```json
{
  "name": "Lagos West",
  "code": "lag",
  "manager_id": 2
}
```

`manager_id` is optional, but when present it must be the id of a user with the manager role, 422 otherwise. The code is uppercased automatically, so `lag` is stored and returned as `LAG`. Response 201 with the created region.

## Sites

### List sites

`GET /api/v1/sites`

Scoped by role: engineers get assigned sites, managers get their regions' sites, admins get everything.

| Parameter | Values |
|---|---|
| `region_id` | Region id |
| `status` | `active`, `under_maintenance`, `decommissioned` |
| `power_source` | `grid`, `generator`, `hybrid`, `solar` |
| `site_type` | `greenfield`, `rooftop`, `indoor` |
| `search` | Case insensitive substring over site code and name |
| `per_page` | 1 to 50, default 15 |

Response, one item:

```json
{
  "id": 1,
  "site_code": "LAG-0001",
  "name": "Ikeja Site",
  "region": { "id": 1, "name": "Lagos West", "code": "LAG", "manager": { "id": 2, "name": "..." } },
  "address": "...",
  "latitude": "6.6018000",
  "longitude": "3.3515000",
  "site_type": "greenfield",
  "power_source": "hybrid",
  "status": "active",
  "engineers": [ { "id": 3, "name": "...", "email": "engineer1@sitelog.test" } ],
  "created_at": "2026-11-19T20:08:56.000000Z"
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

All fields required except `status`, which defaults to `active`. The site code is uppercased automatically. Latitude must be between -90 and 90, longitude between -180 and 180. Response 201.

### Show a site

`GET /api/v1/sites/{site}`

403 when the caller has no access to that site, for example an engineer who is not assigned there.

### Update a site

`PUT /api/v1/sites/{site}`

Admin or the site's regional manager. All fields are optional and only sent fields change. A manager cannot move a site into a region they do not manage, that returns 422 on `region_id`.

### Decommission a site

`DELETE /api/v1/sites/{site}`

Admin or the site's regional manager. This does not delete the row. It sets `status` to `decommissioned` and returns the updated site, so the site's report and fault history is preserved.

### Assign an engineer

`POST /api/v1/sites/{site}/engineers/{user}`

Admin or the site's regional manager. The target user must have the engineer role, 422 otherwise. Assigning someone who is already assigned is a no-op. Response 201 with the updated site including its `engineers` array.

### Remove an engineer

`DELETE /api/v1/sites/{site}/engineers/{user}`

Admin or the site's regional manager. Returns the updated site. The engineer's existing reports are untouched.

## Visit reports

A report moves through four states: `draft`, `submitted`, `approved`, `rejected`. An engineer creates a draft, submits it, and a manager or admin approves or rejects it. Rejected reports can be edited and resubmitted. Submitted and approved reports are locked to the engineer forever.

### List reports for a site

`GET /api/v1/sites/{site}/reports`

Requires access to the site. Supports the same filters as the global list, except `site_id` which is fixed by the URL.

### List reports

`GET /api/v1/reports`

Scoped by role like sites. Query parameters, all optional:

| Parameter | Values |
|---|---|
| `site_id` | Site id |
| `region_id` | Region id |
| `engineer_id` | User id |
| `visit_type` | `routine`, `corrective`, `emergency` |
| `status` | `draft`, `submitted`, `approved`, `rejected` |
| `from` | Date `Y-m-d`, inclusive, on `visit_date` |
| `to` | Date `Y-m-d`, inclusive |
| `per_page` | 1 to 50, default 15 |

Response, one item:

```json
{
  "id": 5,
  "site": { "id": 1, "site_code": "LAG-0001", "name": "Ikeja Central" },
  "engineer": { "id": 3, "name": "...", "email": "engineer1@sitelog.test", "role": "engineer" },
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
  "photos": [ { "id": 2, "path": "photos/x.jpg", "caption": "Meter reading", "uploaded_by": 3 } ],
  "created_at": "2026-11-22T10:00:00.000000Z"
}
```

`reviewer`, `reviewed_by`, `reviewed_at` and `review_comment` fill in once the report has been approved or rejected.

### Create a report

`POST /api/v1/sites/{site}/reports`

The caller must be an engineer assigned to that site. The report is always created as `draft`, there is no way to post a status and skip the workflow.

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

`maintenance_items` is optional, but must be an array when present and every item needs all four fields. The report and its items are written in one transaction, so a failure never leaves half a report behind.

Validation: `visit_date` cannot be in the future, `fuel_level_percent` must be 0 to 100, and the same engineer cannot log the same site twice on one date. A duplicate returns 422 on `visit_date` with "You already logged a report for this site on that date." A database unique index enforces the same rule against simultaneous requests.

When a report records fuel below 20%, the regional manager is notified automatically.

### Show a report

`GET /api/v1/reports/{report}`

Scoped like the lists, 403 outside the caller's scope.

### Update a report

`PUT /api/v1/reports/{report}`

Only the report's own engineer, and only while it is `draft` or `rejected`. All fields optional, same rules as create for the fields sent. When `maintenance_items` is sent it replaces the whole set in one transaction. 403 once the report is submitted or approved.

### Delete a report

`DELETE /api/v1/reports/{report}`

Same restriction as update. Items and photos are removed with it. Response:

```json
{ "message": "Report deleted." }
```

### Submit

`POST /api/v1/reports/{report}/submit`

The owner, from `draft` or `rejected`. Moves the report to `submitted` and notifies the site's regional manager. No body.

### Approve

`POST /api/v1/reports/{report}/approve`

Admin or the site's regional manager, and only on a `submitted` report. Body optional, may carry a comment:

```json
{ "review_comment": "Looks good" }
```

Moves the report to `approved`, records the reviewer and timestamp, and notifies the engineer.

### Reject

`POST /api/v1/reports/{report}/reject`

Same actors as approve. The comment is required, 422 without it:

```json
{ "review_comment": "Fuel reading does not match the photo." }
```

Moves the report to `rejected`. The engineer can now edit and resubmit it.

## Faults

A fault is a problem at a site: power, transmission, equipment, security or civil. It moves through `open`, `in_progress`, `resolved` and `closed`, and its deadline is set from its severity the moment it is raised.

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
| `region_id` | Region id |
| `assigned_to` | Engineer user id |
| `sla_breached` | `1` or `true`, returns only open faults whose deadline has passed |
| `per_page` | 1 to 50, default 15 |

Response, one item:

```json
{
  "id": 7,
  "site": { "id": 1, "site_code": "LAG-0001", "name": "Ikeja Central" },
  "raiser": { "id": 3, "name": "...", "email": "engineer1@sitelog.test" },
  "assignee": { "id": 4, "name": "...", "email": "engineer2@sitelog.test" },
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

`sla_breached` is computed per fault: true when the deadline has passed and the fault is still `open` or `in_progress`. `assignee` stays null until a manager assigns the fault.

### Raise a fault

`POST /api/v1/sites/{site}/faults`

An engineer assigned to the site, the site's regional manager, or an admin. Status is forced to `open`. Response 201.

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

A critical fault also triggers an immediate notification to the regional manager, delivered on the queue.

### Show a fault

`GET /api/v1/faults/{fault}`

Scoped like the lists.

### Update a fault

`PUT /api/v1/faults/{fault}`

Partial updates of `title`, `description`, `category` and `severity`. The raiser may edit these while the fault is `open`. Admins and the site's regional manager may edit at any time.

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

`{type}` is either `reports` or `faults`, anything else 404s. The caller must have access to the parent: an engineer assigned to the site, the site's regional manager, or an admin.

Multipart form request, files under the `photos[]` key, optional `caption`:

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

The uploader, an admin, or the site's regional manager. Deletes the row and the file from disk in the same request. Response:

```json
{ "message": "Photo deleted." }
```

## Notifications

Notifications are database rows plus queued emails. The triggers:

| Trigger | Who is notified | Channels |
|---|---|---|
| Report submitted | The site's regional manager | Database and email |
| Report approved or rejected | The engineer | Database and email |
| Critical fault raised | The site's regional manager | Database and email |
| Fuel below 20% recorded | The site's regional manager | Database and email |
| Fault past its SLA deadline | The site's regional manager | Database and email, hourly |

The critical fault and low fuel alerts fire on the creating request through events and listeners, but the actual delivery runs on the queue, so engineers never wait on mail. Locally mail is the log mailer and the queue is the database driver, both swappable in `.env`.

### List notifications

`GET /api/v1/notifications`

The current user's notifications, newest first, paginated.

```json
{
  "data": [
    {
      "id": "uuid",
      "type": "critical_fault",
      "message": "A critical fault was raised on LAG-0001: Generator down.",
      "data": { "type": "critical_fault", "fault_id": 7, "site_id": 1 },
      "read_at": null,
      "created_at": "2026-11-22T11:00:00.000000Z"
    }
  ],
  "links": { "first": null, "last": null, "prev": null, "next": null },
  "meta": { "current_page": 1, "per_page": 15, "total": 1 }
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

`average_fuel_level_last_7_days` covers reports from the last 7 days, rounded to 2 decimals. `grid_outages_last_30_days` counts reports where grid power was unavailable.

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

## Business rules reference

These are the rules the system enforces, and where each one lives.

- One visit report per engineer per site per day. Form Request returns 422, a unique database index catches races
- A report's visit date cannot be in the future. Form Request
- Fuel level must be 0 to 100. Form Request
- Reports are created as draft, always. The service ignores any posted status
- Submitted and approved reports are locked to the engineer. Policy
- Only the owner can edit or delete a report, and only while it is draft or rejected. Policy
- Only the site's regional manager or an admin can approve or reject, and only submitted reports. Policy
- Rejection requires a comment. Form Request
- A fault's deadline is set from severity on creation. Service, with the hours on the FaultSeverity enum
- Fault status must follow open, in_progress, resolved, closed. The service rejects illegal jumps with a 422
- Reopening a resolved fault clears resolved_at and resets the deadline. Service
- Setting resolved records resolved_at automatically. Service
- Only a manager or admin can assign a fault, the assignee must be an engineer on that site. Form Request
- Critical faults and low fuel readings notify the regional manager. Events and listeners, delivered on the queue
- SLA breaches notify the manager once, tracked by sla_notified_at. Scheduled command
- Photos must be jpg, png or webp, 5 MB each, 10 per parent. Form Request plus a stored-count check in the controller
- Deleting a photo deletes the file from disk. Controller
- Engineers see only assigned sites, managers only their regions. Policies plus query scoping on every list

## Error reference

| Code | When | Body |
|---|---|---|
| 401 | Missing or invalid token | `{ "message": "Unauthenticated." }` |
| 403 | Signed in but not allowed | `{ "message": "This action is unauthorized." }` |
| 404 | Unknown resource, or a photo parent type outside reports and faults | `{ "message": "..." }` |
| 405 | Wrong verb for the URL | `{ "message": "The GET method is not supported for route api/v1/auth/login. Supported methods: POST." }` |
| 422 | Validation failed | `{ "message": "The fuel level percent field is required.", "errors": { "fuel_level_percent": [ "..." ] } }` |
| 429 | Login throttled | `{ "message": "Too Many Attempts." }` |

All error responses are JSON, including when the client sends no Accept header.

## Enum values

| Field | Values |
|---|---|
| `role` | `admin`, `manager`, `engineer` |
| `site_type` | `greenfield`, `rooftop`, `indoor` |
| `power_source` | `grid`, `generator`, `hybrid`, `solar` |
| Site `status` | `active`, `under_maintenance`, `decommissioned` |
| `visit_type` | `routine`, `corrective`, `emergency` |
| `battery_health` | `good`, `fair`, `poor` |
| Report `status` | `draft`, `submitted`, `approved`, `rejected` |
| Maintenance `action` | `refilled`, `replaced`, `repaired`, `inspected` |
| Maintenance `unit` | `litres`, `pieces` |
| Fault `category` | `power`, `transmission`, `equipment`, `security`, `civil` |
| Fault `severity` | `low`, `medium`, `high`, `critical` |
| Fault `status` | `open`, `in_progress`, `resolved`, `closed` |

## Repository layout

| Path | What it holds |
|---|---|
| `app/Enums` | Every backed enum, including the SLA hours per severity |
| `app/Models` | Eloquent models and relationships |
| `app/Policies` | Permission rules per model |
| `app/Http/Requests` | Validation, one class per action |
| `app/Http/Resources` | Every JSON response shape |
| `app/Http/Controllers/Api/V1` | Thin controllers |
| `app/Services` | Report workflow, fault state machine, summaries |
| `app/Events`, `app/Listeners`, `app/Notifications` | The notification pipeline |
| `app/Console/Commands` | The hourly SLA breach check |
| `app/Http/Middleware` | JSON-forcing middleware for the API |
| `database/migrations` | Schema, foreign keys, indexes, unique constraints |
| `database/factories`, `database/seeders` | Fictional demo data |
| `routes/api.php` | All routes, grouped per controller |
| `tests/Feature` | The behaviour test suite |
| `docs/` | This reference, the Postman collection, ER diagram sources |

## Tests and operations

The suite runs on sqlite in memory, so it never touches a real database:

```
vendor/bin/pest          # 81 tests, all behaviour rules
vendor/bin/pint          # code style
php artisan sla:notify-breaches   # run the SLA check by hand
```

GitHub Actions runs the same suite on PHP 8.3 and 8.4 for every push to main. The acceptance check is a fresh clone, `composer install`, `migrate --seed` and the test suite, all with no manual fixes.

## Glossary

| Term | Meaning |
|---|---|
| SLA | Service level agreement. Here, the deadline a fault must be resolved by, set from its severity |
| Decommission | Marking a site inactive instead of deleting it, so its history survives |
| Polymorphic relation | One photos table attached to two parent types, reports and faults |
| Pivot table | The join table between engineers and sites, which also carries assigned_at |
| N+1 | One query per related row instead of one query for the page. The report list test proves it does not happen |
| Form Request | A class that validates an incoming request before the controller runs |
| Policy | A class that answers the permission question for one model |
| Queued notification | A notification whose delivery runs on the queue so the request does not wait on mail |
