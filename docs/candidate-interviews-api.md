# Candidate Interviews API (Candidate Module - Interview Page)

## Scope
Backend APIs supporting the **Candidate Interview Page** within the Candidate Module.
Integrates candidate interviews with Applications (`applications.id`) and Candidate Profiles (`candidate_profiles.id`), strictly scoped to the authenticated candidate (`users.id`).

## Authentication & Authorization
- Middleware: `auth` (or `auth:sanctum` once Sanctum is enabled).
- IDOR Protection: Candidates can strictly access only interviews linked to their own candidate profile.
- Data Protection: `internal_notes` and evaluator private details are NEVER exposed to candidates. `numeric_score` and `candidate_feedback` are exposed ONLY when officially published (`feedback_shared_at` is set).

---

## Domain Concepts & Status Rules

### Interview Status vs. Booking Status
- **Interview Status (`interviews.status`)**: Represents the high-level macro lifecycle of the interview round within the recruitment process (`App\Enums\InterviewStatus`). Supported persisted values: `scheduled`, `rescheduled`, `completed`, `cancelled`.
- **Booking Status (`interview_bookings.booking_status`)**: Represents the reservation state of a specific calendar time-slot with assigned interviewers. Supported persisted values: `confirmed`, `cancelled`.
  - When an interview is rescheduled, the previous booking is updated to `cancelled`, a new booking is generated with `confirmed`, and the interview round status remains active (`rescheduled` or `scheduled`).
  - When an interview round is cancelled, the interview `status` becomes `cancelled` and associated active bookings are marked as `cancelled`.

### Round State (`round_state` in `rounds` timeline)
- `in_progress`: The interview booking is actively occurring right now (`start_at <= now <= end_at`).
- `upcoming`: The interview is scheduled in the future (`start_at > now`), or is a future stage. Note: Future scheduled rounds are never incorrectly marked `current`.
- `current`: The round represents the active workflow stage awaiting scheduling (no booking created yet).
- `past`: The interview round was completed or its scheduled booking end time has already elapsed.
- `cancelled`: The interview round was cancelled.

Additionally, each round exposes:
- `is_current_stage`: Boolean indicating whether this round is the active workflow stage in the recruitment process, regardless of whether it is upcoming, in-progress, or awaiting scheduling.
- `is_current`: Boolean indicating whether this round is the interview record currently being viewed in the API response (`round.id === root.id`).

### Evaluation Status (`evaluation_status`) & Mixed Publication Rules
- `pending`: No evaluations have been submitted yet by the interviewers.
- `submitted`: Evaluators have submitted internal evaluations, but none have been officially approved/published. Internal notes, numeric score, and draft feedback remain strictly hidden.
- `published`: Evaluator feedback and numeric score have been officially published via `POST /api/interviews/{id}/evaluations/{evaluationId}/publish`.
- **Mixed Publication States**: When an interview has multiple evaluators and only a subset of evaluations has been published, `evaluation_status` returns `'published'` to make published feedback available. To strictly protect evaluator deliberations, only officially published evaluations (`feedback_shared_at != null`) are exposed in `shared_feedback`. Unpublished draft evaluations, scores, and internal notes are NEVER exposed.

### Canonical Feedback Response Contract
To provide a clean, consistent response contract for Frontend consumers without breaking existing tests:
- **Root level**: When an evaluation is published, `numeric_score`, `candidate_feedback`, and `feedback_shared_at` expose the primary published evaluation. When unpublished, these fields are omitted from the JSON.
- **`shared_feedback`**: Array of all officially published evaluations. Each item provides canonical ERD fields:
  - `id`: Evaluation ID
  - `numeric_score`: Published decimal score
  - `candidate_feedback`: Published feedback text (canonical)
  - `feedback_shared_at`: ISO-8601 publication timestamp (canonical)
  - `feedback`: Alias of `candidate_feedback` for backwards compatibility
  - `shared_at`: Alias of `feedback_shared_at` for backwards compatibility

---

## Endpoints

### 1. List Candidate Interviews
`GET /api/candidate/interviews`

#### Query Parameters:
- `filter`: `all` (default) | `upcoming` | `past`
- `status`: optional (`scheduled`, `completed`, `cancelled`, `rescheduled`)
- `from`: optional start date (e.g. `2026-10-01`)
- `to`: optional end date (e.g. `2026-10-31`)
- `sort`: `date_asc` (default) | `date_desc` | `round_asc` | `round_desc`
- `page`: integer (default 1)
- `per_page`: integer (default 15)

#### Response Structure:
```json
{
  "data": [
    {
      "id": 2,
      "application_id": 10,
      "round_no": 2,
      "interview_type": "online",
      "is_online": true,
      "status": "scheduled",
      "location": null,
      "meeting_link": "https://meet.google.com/xyz-uvw-rst",
      "start_at": "2026-10-25T14:00:00+00:00",
      "end_at": "2026-10-25T15:00:00+00:00",
      "booking_status": "confirmed",
      "instructions": null,
      "evaluation_status": "pending",
      "application": {
        "id": 10,
        "status": "interviewing",
        "job_title": "Senior Backend Engineer",
        "company_name": "Acme Corporation"
      },
      "rounds": [
        {
          "id": 1,
          "round_no": 1,
          "interview_type": "online",
          "is_online": true,
          "status": "completed",
          "location": null,
          "meeting_link": "https://meet.google.com/round-1",
          "start_at": "2026-10-10T10:00:00+00:00",
          "end_at": "2026-10-10T11:00:00+00:00",
          "booking_status": "confirmed",
          "is_current": false,
          "is_current_stage": false,
          "round_state": "past"
        },
        {
          "id": 2,
          "round_no": 2,
          "interview_type": "online",
          "is_online": true,
          "status": "scheduled",
          "location": null,
          "meeting_link": "https://meet.google.com/xyz-uvw-rst",
          "start_at": "2026-10-25T14:00:00+00:00",
          "end_at": "2026-10-25T15:00:00+00:00",
          "booking_status": "confirmed",
          "is_current": true,
          "is_current_stage": true,
          "round_state": "upcoming"
        },
        {
          "id": 3,
          "round_no": 3,
          "interview_type": "onsite",
          "is_online": false,
          "status": "scheduled",
          "location": "HQ Building, Room 402",
          "meeting_link": null,
          "start_at": null,
          "end_at": null,
          "booking_status": null,
          "is_current": false,
          "is_current_stage": false,
          "round_state": "upcoming"
        }
      ],
      "bookings": [
        {
          "id": 2,
          "start_at": "2026-10-25T14:00:00+00:00",
          "end_at": "2026-10-25T15:00:00+00:00",
          "booking_status": "confirmed"
        }
      ],
      "created_at": "2026-10-19T10:00:00+00:00"
    }
  ],
  "applied_filters": {
    "filter": "all",
    "status": null,
    "from": null,
    "to": null,
    "sort": "date_asc"
  },
  "links": { ... },
  "meta": { ... }
}
```

---

### 2. Candidate Interview Details
`GET /api/candidate/interviews/{id}`

Returns the full details of a specific interview for the candidate interview detail view / modal, with the identical resource structure as above.
- When an evaluation is published, `numeric_score`, `candidate_feedback`, and `feedback_shared_at` are provided, and `evaluation_status` equals `published`.
- When an evaluation is pending or submitted (unpublished), `numeric_score`, `candidate_feedback`, and `internal_notes` are strictly omitted from the payload to protect evaluator privacy.
- Unscheduled interviews safely return `null` for `start_at`, `end_at`, and `booking_status`.
- Unauthorized access returns `403 Forbidden`.

---

### 3. Publish Interview Evaluation (Interviewer & HR)
`POST /api/interviews/{id}/evaluations/{evaluationId}/publish`

Publishes an evaluation to make the score and candidate feedback visible to the candidate. Internal notes remain private under all circumstances.

#### Authorization:
- Assigned interviewers for this interview.
- The evaluator who created the evaluation.
- Authorized HR / Admin users.

#### Response:
```json
{
  "message": "Evaluation feedback published successfully.",
  "data": {
    "id": 1,
    "interview_id": 10,
    "feedback_shared_at": "2026-10-26T10:00:00+00:00",
    "is_published": true
  }
}
```

