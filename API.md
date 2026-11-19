# GTAV-M REST API (v1)

Base URL: `http://127.0.0.1:8000/api/v1`
Format: JSON. Authentication: Laravel Sanctum bearer tokens.

## Endpoints

| Method | URL | Who | Purpose |
| --- | --- | --- | --- |
| POST | `/auth/token` | anyone | Exchange email + password for a token |
| POST | `/auth/logout` | signed in | Revoke the current token |
| GET | `/me` | signed in | Current user profile |
| GET | `/events` | public | Published upcoming events. Filters: `chapter`, `type`, `q`, `per_page` |
| GET | `/events/{id}` | public | One event with its sub-events and seats left |
| GET | `/registrations` | attendee | My registrations (includes `qr_token`) |
| POST | `/events/{id}/registrations` | attendee | Register for an event |
| DELETE | `/registrations/{id}` | attendee | Cancel my registration |
| POST | `/checkins/scan` | gate staff, manager, admin | **Validate a pass at the gate** |
| GET | `/events/{id}/attendance` | gate staff, manager, admin | Live headcount |
| GET | `/events/{id}/attendees?q=` | gate staff, manager, admin | Find a ticket holder by name or ticket code |
| GET | `/reports/chapters` | manager, admin | Tickets and revenue per chapter |
| GET | `/reports/chapters/{id}` | manager, admin | One chapter broken down by event |

Status codes: `200` OK, `201` created, `401` no or bad token, `403` role not allowed,
`404` not found, `409` already checked in, `422` validation or business-rule failure, `429` too many requests.

Rate limits: `POST /auth/token` allows 10 attempts per minute per email address and IP; the scan and attendee-search
endpoints allow 240 requests per minute per gate staff account. Over the limit the API answers `429` with a
`Retry-After` header.

## Get a token

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/token \
  -H "Accept: application/json" \
  -d "email=gate.apac@gtavm.test" -d "password=password" -d "device_name=Gate-A-01"
```

```json
{ "token": "1|k3J...", "token_type": "Bearer", "user": { "id": 3, "role": "gate_staff", "chapter_id": 1 } }
```

Send it with every other request: `Authorization: Bearer 1|k3J...`

## Scan a pass (the gate endpoint)

`code` is the text inside the QR code, or the reference printed on the pass (for example `EX-7F3K9Q2M`).

```bash
curl -X POST http://127.0.0.1:8000/api/v1/checkins/scan \
  -H "Accept: application/json" -H "Authorization: Bearer TOKEN" \
  -d "event_id=13" -d "code=EX-7F3K9Q2M" -d "device=Gate-A-01"
```

`200` admitted:

```json
{
  "authorized": true,
  "result": "admitted",
  "instruction": "LET IN",
  "tone": "ok",
  "message": "Valid ticket. Welcome them in.",
  "attendee": { "name": "Jordan Reyes", "company": "Reyes Freight Solutions" },
  "reference_code": "EX-7F3K9Q2M",
  "checked_in_at": "2026-11-17T09:14:02+08:00",
  "activity": { "key": "ticket-42", "name": "Jordan Reyes", "status": "inside", "status_label": "Inside", "detail": "Entered 9:14 AM", "scans": 1, "repeat_note": null },
  "event": { "id": 13, "title": "Singapore Members Briefing", "capacity": 30, "confirmed": 14, "checked_in": 5, "remaining": 9 }
}
```

`instruction` is what a gate screen should show in large type: `LET IN`, `ALREADY INSIDE` or `DO NOT LET IN`.
`message` explains it in one sentence (the entry time, or why the ticket is refused). `activity` summarizes every scan
of this ticket so far, so a handheld can show one line per ticket instead of one per scan.

Other outcomes use the same shape with `"authorized": false`:

| `result` | HTTP | Meaning |
| --- | --- | --- |
| `duplicate` | 409 | Already inside. `checked_in_at` holds the first entry time; check ID before letting back in |
| `invalid` | 404 | No ticket matches the code |
| `wrong_event` | 422 | Valid pass, but for a different event |
| `cancelled` | 422 | The ticket was cancelled |

Every scan, accepted or rejected, is written to the `check_ins` table.
Gate staff can only scan events in their own chapter (`403` otherwise).

## Register as an attendee

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/token -H "Accept: application/json" \
  -d "email=attendee@gtavm.test" -d "password=password"

curl -X POST http://127.0.0.1:8000/api/v1/events/2/registrations \
  -H "Accept: application/json" -H "Authorization: Bearer TOKEN"
```

`201` returns the registration. `422` returns the rule that blocked it, for example:

```json
{ "message": "This event has reached its maximum capacity of 8." }
```

## Chapter performance

```bash
curl http://127.0.0.1:8000/api/v1/reports/chapters \
  -H "Accept: application/json" -H "Authorization: Bearer TOKEN"
```

```json
{
  "data": [
    { "id": 1, "name": "Asia-Pacific Chapter", "code": "APAC", "events": 6, "capacity": 300,
      "tickets": 73, "checked_in": 23, "revenue": 9180.0, "fill_rate": 24.3, "attendance_rate": 31.5 }
  ],
  "totals": { "chapters": 1, "events": 6, "tickets": 73, "revenue": 9180.0 }
}
```

Administrators get every chapter. Chapter managers get only their own.
