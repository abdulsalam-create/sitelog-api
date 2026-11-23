# SiteLog API

Backend API for logging maintenance visits and tracking faults on network cell sites. Field engineers record visit reports, regional managers review and approve them, and faults carry automatic SLA deadlines. Built as the MTN Laravel internship task. All data is fictional.

## Requirements

- PHP 8.2 or newer (developed on 8.3)
- MySQL 8
- Composer

## Setup

```
git clone <repo url>
cd sitelog-api
composer install
cp .env.example .env
```

Create a MySQL database called `sitelog` (or change `DB_DATABASE` in `.env`), then:

```
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

`migrate --seed` loads the fictional dataset: 12 users (1 admin, 3 regional managers, 8 field engineers), 3 regions, 25 sites, 60 visit reports and 30 faults.

## Running the app

```
php artisan serve
```

The API lives under `/api/v1`. Requests carry a bearer token from `POST /api/v1/auth/login`, except login itself.

## Tests

```
vendor/bin/pest
```

81 tests, all against sqlite in memory, so they never touch the local database. The same suite runs on GitHub Actions for every push, on PHP 8.3 and 8.4.

## Queue worker and scheduler

Notifications are queued on the database driver:

```
php artisan queue:work
```

The scheduler runs the hourly SLA breach check:

```
php artisan schedule:work
```

Or trigger it by hand with `php artisan sla:notify-breaches`.

Mail goes to the log file (`storage/logs/laravel.log`) via the log mailer, which the task brief explicitly allows for local work. Swap `MAIL_*` in `.env` for real SMTP later.

## Demo accounts

All seeded passwords are `password`.

| Role | Email |
|---|---|
| Admin | `admin@sitelog.test` |
| Regional managers | `manager1@sitelog.test` to `manager3@sitelog.test` |
| Field engineers | `engineer1@sitelog.test` to `engineer8@sitelog.test` |

## API surface

| Area | Endpoints |
|---|---|
| Auth | `POST /auth/login`, `POST /auth/logout`, `GET /auth/me` |
| Regions | `GET /regions`, `POST /regions`, `GET /regions/{region}/summary` |
| Sites | `GET/POST /sites`, `GET/PUT/DELETE /sites/{site}`, `POST/DELETE /sites/{site}/engineers/{user}`, `GET /sites/{site}/summary` |
| Reports | `GET/POST /sites/{site}/reports`, `GET/PUT/DELETE /reports/{report}`, `POST /reports/{report}/submit`, `/approve`, `/reject` |
| Faults | `GET/POST /sites/{site}/faults`, `GET/PUT /faults/{fault}`, `PATCH /faults/{fault}/status` |
| Photos | `POST /{type}/{id}/photos` where type is `reports` or `faults`, `DELETE /photos/{photo}` |
| Notifications | `GET /notifications`, `POST /notifications/{id}/read` |

Lists are paginated (`data`, `links`, `meta`) and filterable. Sites filter by region, status, power source and site type, with search over code and name. Reports filter by site, region, engineer, visit type, status and date range. Faults filter by severity, category, status, region and assignee, plus `sla_breached=1`.

A full API reference covering every endpoint, permission, request field and response shape is in `docs/api.md`. A walkthrough of the API lives in the writeup folder (`postman-testing.md`) and a ready-to-import Postman collection is in `docs/`.

## Design decisions

- **Laravel 12 on the 11 skeleton.** The brief allows Laravel 11+. Laravel 11 stopped receiving security fixes in March 2026, so the project was upgraded to 12 before any feature code was written.
- **Pest over PHPUnit.** Laravel's current testing default, less boilerplate, same engine underneath.
- **Backed enums for every fixed value set.** Roles, statuses, severities, categories, visit types, battery health, maintenance actions and units are PHP 8.1 enums cast on the models. The database stores plain strings, so queries stay readable and invalid values get stopped in code.
- **One report per engineer per site per day, two layers deep.** The form request returns a 422 when the slot is taken, and a unique database index backs it against races. The database is the last line of defense.
- **Policies for every permission, query scoping for lists.** Policies answer "can this user touch this record" and list queries never fetch rows outside the user's scope, so an engineer cannot see another site's reports or faults, and managers are pinned to their regions.
- **Service classes own the workflow.** Submission, review, the fault status machine, SLA math and summaries live in `app/Services`. Controllers validate, delegate and return resources.
- **Consistent JSON everywhere.** Success is `data`, lists add `meta` and `links`, errors are `message` plus `errors` on validation. A middleware forces the JSON accept header and the exception handler renders every `api/*` exception as JSON, so even a wrong-verb 405 or a 401 with no headers is JSON.
- **Fault reopen resets the SLA clock.** Reopening a resolved fault clears `resolved_at` and sets a fresh `sla_due_at` from the severity, so a reopened fault is not instantly SLA-breached.
- **Decommission, not delete.** Deleting a site would cascade away its history. The delete endpoint sets the site to `decommissioned`, keeping reports and faults intact.
- **Notifications are queued, database plus mail.** The critical fault and low fuel alerts fire on the creating request through events and listeners, but the actual notification sends run on the queue, so engineers never wait on mail.
- **Test data never touches production.** The suite runs on sqlite in memory and the seeder only exists for local development.

## Repo layout

- `app/Enums` - every backed enum
- `app/Models` - Eloquent models and relationships
- `app/Policies` - permissions per model
- `app/Http/Requests` - validation, one class per action
- `app/Http/Resources` - every JSON response shape
- `app/Http/Controllers/Api/V1` - thin controllers
- `app/Services` - report workflow, fault machine, summaries
- `app/Events`, `app/Listeners`, `app/Notifications` - the notification pipeline
- `app/Console/Commands` - the hourly SLA breach check
- `database/migrations`, `database/factories`, `database/seeders` - schema and fictional data
- `docs/` - ER diagram sources, SQL dump and the Postman collection
- `tests/Feature` - 81 behaviour tests
