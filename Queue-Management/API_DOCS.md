# API Documentation

Base URL: `http://localhost:8000/api`. All requests and responses are JSON. Send `Accept: application/json`.
Protected endpoints need the header `Authorization: Bearer <token>`.

Error codes used: 401 (not logged in), 403 (wrong role / deactivated), 404, 409 (state conflict), 422 (validation), 429 (rate limited).
A 422 response looks like: `{"message": "...", "errors": {"field": ["message"]}}`.
Nothing is ever deleted through the API: history is kept, and records are retired with `is_active = false` or a status.

## Auth (Stage 2)

| Method | Path | Who | Purpose |
|---|---|---|---|
| GET | `/time` | public | Server time, so the app can compute its clock offset |
| POST | `/register` | public | Create a **student** account (role cannot be chosen) |
| POST | `/login` | public (5 per minute per IP) | Log in, receive a token |
| POST | `/logout` | any logged-in user | Delete the token used for this request |
| GET | `/me` | any logged-in user | The current user |
| POST | `/consent` | student | Accept the location notice (time recorded once) |

**GET /time** 200: `{"server_time": "2026-10-23T02:30:00.123Z", "timestamp_ms": 1792722600123}`

**POST /register** body: `name`, `email`, `student_id` (letters, digits, hyphen; unique), `password` (min 8), `password_confirmation`.
201: `{"user": {...}, "token": "..."}`. Errors: 422.

**POST /login** body: `email`, `password`. 200: `{"user": {...}, "token": "..."}`. Errors: 401 wrong email or password, 403 deactivated, 422, 429.

**POST /logout** 200: `{"message": "Logged out."}`. Errors: 401.

**GET /me** 200: `{"data": {"id", "name", "email", "student_id", "role", "is_active", "has_consented", "consent_given_at"}}`. Errors: 401, 403.

**POST /consent** no body. 200: `{"data": {...user, "has_consented": true}}`. Errors: 401, 403.

## Stations and routes (Stage 3)

| Method | Path | Who | Purpose |
|---|---|---|---|
| GET | `/stations` | any logged-in user | List stations (operators and admins see all, others only active) |
| POST | `/stations` | operator | Create a station |
| PUT | `/stations/{id}` | operator | Update a station |
| GET | `/routes` | any logged-in user | List routes with origin and destination (others see only active) |
| POST | `/routes` | operator | Create a route |
| PUT | `/routes/{id}` | operator | Update a route |

**Station body:** `name` (unique), `latitude` (-90..90), `longitude` (-180..180), `radius_m` (optional, 10..1000, default 100), `is_active` (optional boolean).
Response: `{"data": {"id", "name", "latitude", "longitude", "radius_m", "is_active"}}` (201 on create, 200 on update).

**Route body:** `name` (unique), `origin_station_id`, `destination_station_id` (must differ from origin; the pair must be unique), `is_active` (optional).
Response: `{"data": {"id", "name", "origin_station_id", "destination_station_id", "is_active", "origin": {...}, "destination": {...}}}`.

Errors: 401, 403 (not an operator), 404, 422.

## Buses (Stage 3, operator only)

| Method | Path | Purpose |
|---|---|---|
| GET | `/buses` | List buses |
| POST | `/buses` | Create a bus |
| PUT | `/buses/{id}` | Replace a bus's details (all fields required) |

**Body:** `plate_number` (unique), `capacity` (1..200), `priority_seats` (0..capacity), `status` (`active` or `maintenance`).
Response: `{"data": {"id", "plate_number", "capacity", "priority_seats", "status"}}`. Errors: 401, 403, 404, 422.

## Trips / schedule (Stage 3, operator only)

| Method | Path | Purpose |
|---|---|---|
| GET | `/trips` | List trips. Filters: `route_id`, `date` (Y-m-d), `status`, `per_page` (1..100, default 15). Paginated. |
| POST | `/trips` | Create a trip (audit-logged as `trip.created`) |
| PUT | `/trips/{id}` | Change a scheduled trip (audit-logged as `trip.updated`, with old and new values) |
| PATCH | `/trips/{id}/cancel` | Cancel a scheduled trip. Optional body `reason`. (audit-logged as `trip.cancelled`) |

**Body (POST and PUT):** `route_id` (active route), `bus_id` (bus must be `active`), `driver_id` (user with role `driver`, account active), `departs_at` (date-time, in the future).
Rules: the same bus or driver cannot have another non-cancelled trip within `trip_min_gap_minutes` (setting, default 60) of this time.
Response: `{"data": {"id", "route_id", "bus_id", "driver_id", "departs_at", "status", "route": {...}, "bus": {...}, "driver": {"id", "name"}}}`.
Errors: 401, 403, 404, 409 (trip is not `scheduled`, so it can no longer be changed or cancelled), 422.

## Student check-in (Stage 4)

| Method | Path | Who | Purpose |
|---|---|---|---|
| POST | `/bookings` | student (10 per minute per user) | Check in at the origin station of a route: creates a **provisional** booking |
| GET | `/bookings/current` | student | The student's active booking (provisional, confirmed or called), or `{"data": null}` |
| GET | `/bookings/mine` | student | Paginated booking history for the logged-in student |

### POST /bookings
The student is always the logged-in user. A `user_id` in the body is ignored.
**Body:** `route_id` (active route), `latitude`, `longitude`, `accuracy_m` (GPS accuracy in metres), `arrived_at` (ISO 8601 with milliseconds, the phone time of the tap adjusted by the clock offset from `GET /time`), `request_id` (UUID made on the phone, one per check-in).

**Server checks:** consent accepted; no other active booking; `arrived_at` not in the future and not older than `grace_window_minutes`; `accuracy_m` not above `max_gps_accuracy_m`; position inside the radius of the route's **origin** station (haversine). The lane is `priority` only with an approved, unexpired priority claim.

**Responses**
- 201: new provisional booking. 200: the same `request_id` was already processed (same booking returned, nothing created).
- 403: not a student, or the location notice has not been accepted.
- 409: the student already has an active booking, or the `request_id` belongs to someone else.
- 422: `errors` has the field: `arrived_at`, `accuracy_m`, `location` (outside the station area), `route_id`, or the usual missing/invalid fields.
- 429: too many attempts.

```json
{"data": {
  "id": 12, "status": "provisional", "lane": "general",
  "route": {"id": 1, "name": "...", "origin": {...}, "destination": {...}},
  "queue_number": null, "queue_label": null,
  "provisional_position": 3,
  "arrived_at": "2026-10-24T22:03:00.123Z",
  "confirms_at": "2026-10-24T22:08:00.123Z",
  "confirmed_at": null,
  "call": null
}}
```
`queue_number` and `queue_label` ("7" or "P2") appear only after confirmation. `call` holds `deadline_at`, `trip_id` and `departs_at` once a driver has called the student.

### GET /bookings/current
200: `{"data": {...same shape as above...}}` or `{"data": null}`. Errors: 401, 403.

## Scheduled jobs (Stage 5)

These are Laravel commands run by the task scheduler, not HTTP endpoints.

| Command | Schedule | Purpose |
|---|---|---|
| `php artisan bookings:confirm` | every minute, without overlapping | Gives a queue number to every provisional booking whose `arrived_at` is at least `confirmation_window_minutes` old |

Numbers are given in `arrived_at` order (then `received_at`, then `id`), separately per route, day and lane (general: 1, 2, 3...; priority: P1, P2...).
Each group runs in a database transaction that locks its `queue_counters` row, so numbers are never duplicated.
After confirmation, `GET /bookings/current` returns `status: "confirmed"`, `queue_number`, `queue_label` and `confirmed_at`.

To run the scheduler while developing: `php artisan schedule:work`. In production a cron entry runs `php artisan schedule:run` every minute.

## Priority claims (Stage 6, updated in Stage 12)

Students submit a category and one supporting document. The file is stored on Laravel's private local disk, not in `public` storage. Only operators and admins can review claims and retrieve the file; only admins can approve or reject. Drivers have no access to claim endpoints or documents and receive only the priority flag on a booking. Every claim-list view, document retrieval and decision is audited. Do not put diagnosis details in audit reasons.

| Method | Path | Who | Purpose |
|---|---|---|---|
| POST | `/priority-claims` | student (10 per minute) | Submit a priority request and supporting document (`multipart/form-data`) |
| GET | `/priority-claims/mine` | student | The student's own claims and decision status |
| GET | `/priority-claims` | operator, admin | Review list. Filters: `status`, `q` (student name or ID), `per_page` (1..50). Paginated. |
| GET | `/priority-claims/{id}/document` | operator, admin | Download the private supporting document; access is audited |
| PATCH | `/priority-claims/{id}/approve` | admin only | Approve a pending claim |
| PATCH | `/priority-claims/{id}/reject` | admin only | Reject a pending claim |

**POST /priority-claims** fields: `category` (`pregnant`, `with_child`, `disability`, `sick`) and `document` (required PDF, JPG, JPEG or PNG, maximum 5 MB). Send as `multipart/form-data`. 201: claim with `status: "pending"`. Errors: 409 (a request is already waiting, or the student already has approved priority), 422 (validation), 403, 401, 429.

**Approve / reject** body (both optional): `valid_until` (Y-m-d, today or later; approve only; empty means no expiry), `reason` (text for the audit log; do not include medical details). Admin only. 200: claim with its student. Errors: 409 (already decided), 404, 403, 422.

**Response shape:** `{"data": {"id", "category", "status", "valid_until", "reviewed_at", "created_at", "document": {"available", "original_name", "mime_type", "size_bytes"}, "student": {"id", "name", "student_id"}}}`. `student` appears only in reviewer list and decision responses.

A student with an approved claim that has not passed `valid_until` is placed in the priority lane automatically at check-in. The uploaded file is never returned as a public URL; authorized reviewers use the authenticated document endpoint.

Audit log actions include `priority_claim.requested`, `priority_claim.viewed`, `priority_claim.document_viewed`, `priority_claim.approved`, `priority_claim.rejected`, and `priority_claim.expired`.

## Driver (Stage 7)

All driver endpoints need the role `driver`. A driver can act only on their own trips and calls: anyone else's returns 403.
Drivers see the student's name, student ID, queue number and a "Priority" flag (`is_priority`). The priority category is never shown to drivers.

| Method | Path | Purpose |
|---|---|---|
| GET | `/driver/trips` | The driver's own trips for one day. Optional `?date=Y-m-d` (default today). |
| GET | `/driver/trips/{id}/manifest` | Seats, students already called on this trip, and confirmed students still waiting |
| POST | `/driver/trips/{id}/call-next` | Call the next students into the free seats |
| POST | `/booking-calls/{id}/board` | Mark a called student as boarded |
| POST | `/booking-calls/{id}/skip` | Skip a called student whose deadline has passed |

**GET /driver/trips** 200: `{"data": [{"id", "departs_at", "status", "route": {...}, "bus": {...}, "seats_taken"}]}`

**GET /driver/trips/{id}/manifest** 200:
```json
{"data": {
  "trip": {"id": 1, "departs_at": "...", "status": "boarding", "route": {...}, "bus": {...}},
  "seats": {"capacity": 50, "priority_seats": 5, "taken": 12, "available": 38},
  "called": [{"call_id": 7, "booking_id": 31, "outcome": "pending", "called_at": "...", "deadline_at": "...", "outcome_at": null,
              "student": {"name": "...", "student_id": "..."}, "queue_label": "P1", "is_priority": true, "booking_status": "called"}],
  "waiting": [{"booking_id": 40, "student": {"name": "...", "student_id": "..."}, "queue_label": "3", "is_priority": false, "skips": 0}]
}}
```
`waiting` lists confirmed students for the trip's route and day, priority lane first, each lane in queue order.

**POST /driver/trips/{id}/call-next** optional body `count` (1..200, fewer than the free seats).
Seat rule: the free seats are `bus.capacity` minus pending and boarded calls on this trip. Up to `bus.priority_seats` go to the priority lane first;
all remaining seats go to the general lane (unused priority seats are released). Priority students beyond the priority seats wait for the next bus.
A student already called on this trip is never called again on it. The first call sets the trip to `boarding`.
Each called student gets a deadline of `call_deadline_minutes`.
200: `{"data": [ ...called students, same shape as manifest "called"... ], "meta": {"called_count": 6}}` (empty list when nobody is waiting).
Errors: 403 (not your trip), 409 (bus full, trip not `scheduled` or `boarding`, bus not active, or a student was just called by another driver), 422.

**POST /booking-calls/{id}/board** 200: the call with `outcome: "boarded"` and `booking_status: "boarded"`. Errors: 403, 404, 409 (already decided).

**POST /booking-calls/{id}/skip** allowed only after the call's `deadline_at`. 200: the call with `outcome: "skipped"`.
`booking_status` is `confirmed` (same queue number, included in the next bus's call) or `expired` once the student has been skipped `max_skips` times.
Errors: 403, 404, 409 (deadline not reached yet, or already decided).

## Cancel, manual check-in and booking lists (Stage 8)

| Method | Path | Who | Purpose |
|---|---|---|---|
| POST | `/bookings/{id}/cancel` | student | Cancel the student's own booking, before being called |
| POST | `/bookings/manual` | operator | Check a student in by hand (no smartphone, or the phone failed) |
| GET | `/bookings` | operator, admin | List bookings with filters |

**POST /bookings/{id}/cancel** no body. Only `provisional` and `confirmed` bookings can be cancelled. 200: the booking with `status: "cancelled"`.
The student can then check in again. Errors: 403 (somebody else's booking, or not a student), 404, 409 (already called, boarded, expired or cancelled).

**POST /bookings/manual** body: `student_id` (the university student ID number), `route_id`, `reason` (required, 3..255 characters),
`request_id` (optional UUID, so a double click counts once).
Same queue rules as a phone check-in, but there is no GPS reading (coordinates are stored empty), the location notice is not required,
the arrival time is the server time when the operator submits, `checked_in_by` is the operator, and a student with approved priority goes to the priority lane.
The action is written to the audit log as `booking.manual_checkin` with the reason.
201: the booking (200 if the same `request_id` was already processed). Errors: 403, 409 (the student already has an active booking), 422 (no active student with that ID, inactive route or station, missing reason).

**GET /bookings** filters: `route_id`, `date` (queue date, Y-m-d), `status`, `lane`, `q` (student ID or name), `per_page` (1..100, default 20). Paginated.
Order: newest queue date first, then queue order inside each day.
```json
{"data": [{
  "id": 31, "student": {"id": 5, "name": "...", "student_id": "2020001"}, "route": {"id": 1, "name": "..."},
  "lane": "general", "status": "confirmed", "queue_date": "2026-10-26", "queue_number": 3, "queue_label": "3",
  "arrived_at": "2026-10-26T07:01:12.345Z", "confirmed_at": "2026-10-26T07:06:00.000Z",
  "manual": false, "checked_in_by": null
}], "links": {...}, "meta": {...}}
```
The list never shows GPS coordinates or the priority category (only the lane).

## Admin (Stage 9)

All endpoints in this section need the role `admin`.

### Users
| Method | Path | Purpose |
|---|---|---|
| GET | `/users` | List accounts. Filters: `role`, `is_active`, `q` (name, email or student ID), `per_page` (1..100). Paginated. |
| POST | `/users` | Create a driver, operator or admin account |
| PUT | `/users/{id}` | Edit an account, including deactivating it |

**POST /users** body: `name`, `email`, `role` (`driver`, `operator` or `admin`), `password` (min 8), `is_active` (optional, default true). 201: the user.
**PUT /users/{id}** body: `name`, `email` (required), and optional `role`, `password` (empty keeps the old one), `is_active`, `student_id` (students only).
Accounts are never deleted: deactivate them with `is_active: false`, which also logs the person out everywhere.
Rules (422): a student cannot become staff and staff cannot become a student; an admin cannot deactivate themselves or change their own role; the system must keep at least one active admin.
Audit log: `user.created`, `user.updated` (old and new values; the password is never logged, only `password_changed: true`).

### Settings
| Method | Path | Purpose |
|---|---|---|
| GET | `/settings` | All settings with their allowed range |
| PUT | `/settings` | Change one or more settings. Body: `{"max_skips": 3}`. Send only what changes. |

200 (both): `{"data": [{"name", "value", "description", "min", "max"}]}`. New values apply immediately.
Settings: `grace_window_minutes` (1..60), `confirmation_window_minutes` (1..60), `max_gps_accuracy_m` (5..500), `call_deadline_minutes` (1..60),
`max_skips` (1..10), `location_retention_days` (1..365), `trip_min_gap_minutes` (1..720).
Errors: 422 for an unknown setting, a value out of range, or a confirmation window shorter than the grace window.
Audit log: `settings.updated` with old and new values.

### Reports
| Method | Path | Purpose |
|---|---|---|
| GET | `/reports/summary` | Totals for the range |
| GET | `/reports/hourly` | Bookings per hour of arrival (0 to 23) |
| GET | `/reports/routes` | Bookings, boardings and average wait per route |

Query: `from`, `to` (Y-m-d, `to` not before `from`, at most one year; default the last 7 days), `route_id` (optional). The range is on the booking's queue date.
All figures are computed from the bookings and booking calls tables. Hours use server time (UTC, the same as The Gambia).

**summary** `data`: `bookings_issued`, `by_status` (all six statuses), `priority_bookings`, `manual_checkins`,
`average_wait_minutes` (arrival to the driver marking the student boarded; null when nobody boarded),
`calls` (`boarded`, `skipped`, `pending`), `no_show_rate_percent` (skipped calls as a percentage of boarded plus skipped calls; null when no call was decided).
**hourly** `data.hours`: 24 items `{"hour", "label", "bookings"}`.
**routes** `data.routes`: `{"route_id", "name", "bookings", "boarded", "average_wait_minutes"}`.

### Audit log
`GET /audit-logs` filters: `action` (exact, or the start of one such as `priority_claim`), `user_id`, `from`, `to`, `per_page` (1..100, default 25). Newest first. Read only.
Item: `{"id", "action", "user": {"id", "name", "role"} or null for system jobs, "subject_type", "subject_id", "reason", "details", "ip_address", "created_at"}`.

### Nightly jobs (scheduled commands)
| Command | Time | Purpose |
|---|---|---|
| `bookings:expire-stale` | 00:05 | Active bookings from earlier days become `expired` (an unanswered call is marked skipped) so students are not blocked |
| `priority:expire` | 00:10 | Approved priority claims past `valid_until` become `expired` |
| `locations:clear` | 00:20 | GPS coordinates older than `location_retention_days` are erased; the bookings are kept |
The two system jobs that change data write one audit entry (`bookings.expired_stale`, `locations.cleared`) when they act.
