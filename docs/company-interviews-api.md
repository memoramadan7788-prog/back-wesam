# Company Module: Interviews & Evaluation API Documentation

## Overview
This document specifies the REST APIs for the **Company Module - Interviews & Evaluation** in the Laravel 12 HR Management System.
These APIs extend the existing Interview Module to support:
1. Interviewer availability management (CRUD).
2. Candidate bookable slot discovery with real-time conflict filtering.
3. Reliable candidate interview self-booking with concurrency handling and row-level locking.
4. Universal 24-hour modification and cancellation policy.
5. Interview evaluation and candidate scoring (0-100 scale) for interviewers and authorized HR users.

---

## Authentication & Authorization
- **Middleware**: `auth:sanctum` on all operational endpoints.
- **Role & Permission Hierarchy**:
  - **Candidates**: Can discover available slots for their own applications, book interview slots, view their own interviews/rounds timeline, and modify/cancel their own bookings (subject to the 24-hour rule). Cannot manage interviewer availability, access other candidates' applications, or submit/view internal evaluations.
  - **Interviewers**: Can manage their own availability slots, view assigned interviews, and submit/publish evaluations for assigned interviews.
  - **Company HR / Recruiters**: Can manage availability on behalf of company interviewers, schedule operational interviews, reschedule/cancel bookings, and submit/publish evaluations.
  - **Platform Admins**: Managed via administrative endpoints. Denied operational direct booking/scheduling to preserve company isolation.

---

## 1. Interviewer Availability Management

### 1.1 List Availability Slots
`GET /api/interviewer/availabilities`

- **Authorization**: Interviewers (sees own slots) or HR/Recruiters (can view all or filter).
- **Query Parameters**:
  - `interviewer_id` *(integer, optional)*: Filter by interviewer (HR only).
  - `status` *(string, optional)*: Filter by `open`, `booked`, or `cancelled`.
  - `from` *(date/string, optional)*: Filter slots starting at or after this date.
  - `to` *(date/string, optional)*: Filter slots starting at or before this date.
  - `per_page` *(integer, optional)*: Pagination size (default: 15).
- **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "interviewer_id": 4,
      "start_at": "2026-10-25T09:00:00+00:00",
      "end_at": "2026-10-25T10:00:00+00:00",
      "duration_minutes": 60,
      "availability_status": "open",
      "interviewer": {
        "id": 4,
        "name": "Jane Interviewer"
      },
      "created_at": "2026-10-20T10:00:00+00:00",
      "updated_at": "2026-10-20T10:00:00+00:00"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

### 1.2 Create Availability Slot
`POST /api/interviewer/availabilities`

- **Authorization**: Interviewers or HR/Recruiters.
- **Request Body**:
```json
{
  "interviewer_id": 4,
  "start_at": "2026-10-25T09:00:00+00:00",
  "end_at": "2026-10-25T10:00:00+00:00"
}
```
- **Validation Rules**:
  - `interviewer_id`: optional integer for HR; omitted for interviewers (defaults to authenticated user).
  - `start_at`: required date, must be strictly in the future (`after:now`).
  - `end_at`: required date, must be after `start_at`.
  - Overlap prevention: Rejects slot if it overlaps with an existing non-cancelled slot for this interviewer.
- **Success Response (201 Created)**: Returns the created slot object with status `201`.

### 1.3 View Availability Slot
`GET /api/interviewer/availabilities/{id}`

- **Authorization**: Owning interviewer or HR/Recruiter.

### 1.4 Update Availability Slot
`PUT/PATCH /api/interviewer/availabilities/{id}`

- **Authorization**: Owning interviewer or HR/Recruiter.
- **Request Body**:
```json
{
  "start_at": "2026-10-25T10:00:00+00:00",
  "end_at": "2026-10-25T11:00:00+00:00",
  "availability_status": "open"
}
```
- **Business Rule**: If the slot is currently `booked`, timings cannot be modified to avoid invalidating confirmed interviews (returns `422 Unprocessable Entity`).

### 1.5 Delete Availability Slot
`DELETE /api/interviewer/availabilities/{id}`

- **Authorization**: Owning interviewer or HR/Recruiter.
- **Business Rule**: If the slot is `booked` or has active confirmed bookings, deletion is rejected with `409 Conflict`:
```json
{
  "message": "Cannot delete an availability slot that is currently booked."
}
```
- **Success Response (200 OK)**:
```json
{
  "message": "Availability slot deleted successfully."
}
```

---

## 2. Display Available Slots to Candidates

`GET /api/candidate/available-slots`

- **Authorization**: Authenticated candidates.
- **Query Parameters**:
  - `application_id` *(integer, optional)*: The candidate's application ID. If provided, verifies ownership and that the application is active (not rejected/withdrawn), and excludes slots that conflict with the candidate's existing interviews.
  - `interviewer_id` *(integer, optional)*: Filter by specific interviewer.
  - `from` *(date, optional)*: Start of date filter.
  - `to` *(date, optional)*: End of date filter.
  - `company_id` *(integer, optional)*: Filter by company.
  - `per_page` *(integer, optional)*: Default 15.
- **Business Logic**:
  - Only returns slots with `availability_status = 'open'` and `start_at > now()`.
  - Excludes booked, cancelled, or past slots.
  - Excludes any slot where the candidate already has an active conflicting interview.
- **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 12,
      "interviewer_id": 4,
      "start_at": "2026-10-25T14:00:00+00:00",
      "end_at": "2026-10-25T15:00:00+00:00",
      "duration_minutes": 60,
      "availability_status": "open",
      "interviewer": {
        "id": 4,
        "name": "Jane Interviewer"
      }
    }
  ],
  "meta_filters": {
    "application_id": 5,
    "interviewer_id": null,
    "from": null,
    "to": null
  }
}
```

---

## 3. Candidate Interview Booking

`POST /api/candidate/interviews/book`

- **Authorization**: Authenticated candidate owning the target application.
- **Request Body**:
```json
{
  "application_id": 5,
  "availability_id": 12,
  "interview_type": "online",
  "meeting_link": "https://meet.google.com/abc-xyz",
  "location": null,
  "instructions": "Need 10 minutes screen sharing presentation"
}
```
- **Validation**:
  - `application_id`: required integer. Verified to belong to the authenticated candidate.
  - `availability_id`: required integer.
  - `interview_type`: optional string (`online`, `on_site`, `exam`, `onsite` normalized to `on_site`).
  - `meeting_link`: optional valid URL.
  - `location`: optional string up to 255 chars.
  - `instructions`: optional string up to 5000 chars.
- **Concurrency & Invariant Guarantees**:
  - Executed inside a database transaction with `lockForUpdate()` on `interviewer_availability`.
  - If the slot was taken concurrently or is no longer `open`, returns **409 Conflict**:
    ```json
    {
      "message": "This slot is no longer available."
    }
    ```
  - Prevents candidate schedule overlap: rejects request if the candidate has an existing interview spanning the proposed time.
  - Automatically calculates the next deterministic `round_no` for the application.
  - Creates parent `Interview` (`scheduled`) and `InterviewBooking` (`confirmed`), and marks slot `booked` atomically.
- **Success Response (201 Created)**: Returns `CandidateInterviewResource`.

---

## 4. Twenty-Four-Hour Modification & Cancellation Policy

Enforced universally on all rescheduling and cancellation endpoints:

- **Rule**:
  - Allowed when **$\ge$ 24 hours** remain before the scheduled interview start time (`diffInSeconds >= 86400`).
  - Rejected when **$<$ 24 hours** remain (`diffInSeconds < 86400`).
  - Exactly 24 hours in advance is allowed.
  - Past interviews cannot be rescheduled or cancelled.
- **Endpoints Enforcing the Rule**:
  - `POST /api/interviews/{id}/reschedule` (Interviewer & HR)
  - `POST /api/interviews/{id}/cancel` (Interviewer & HR)
  - `POST /api/candidate/interviews/{id}/reschedule` (Candidate self-service)
  - `POST /api/candidate/interviews/{id}/cancel` (Candidate self-service)
- **Rejection Response (422 Unprocessable Entity)**:
```json
{
  "message": "Interviews can only be rescheduled at least 24 hours before the scheduled start time.",
  "errors": {
    "booking": [
      "Interviews can only be rescheduled at least 24 hours before the scheduled start time."
    ]
  }
}
```

---

## 5. Interview Evaluation & Candidate Scoring

### 5.1 Submit Evaluation
`POST /api/interviews/{id}/evaluations`

- **Authorization**: Assigned interviewers or authorized company HR/recruiter users.
- **Request Body**:
```json
{
  "numeric_score": 88.5,
  "internal_notes": "Strong system design skills, good understanding of concurrency.",
  "candidate_feedback": "Great communication and problem solving approach."
}
```
- **Validation Rules**:
  - `numeric_score`: required numeric, between `0` and `100` (supports decimals).
  - `internal_notes`: required string, minimum 3 chars, maximum 10,000 chars.
  - `candidate_feedback`: optional string, maximum 10,000 chars.
- **Business Logic**:
  - Evaluator ID is securely derived from the authenticated user token (cannot be forged).
  - One evaluation per `(interview_id, evaluator_id)`: multiple submissions update the evaluator's existing evaluation draft idempotently.
  - Cancelled interviews cannot be evaluated.
  - `feedback_shared_at` cannot be set during submission; draft evaluations and scores remain strictly hidden from candidates.

### 5.2 Publish Evaluation
`POST /api/interviews/{id}/evaluations/{evaluationId}/publish`

- **Authorization**: Assigned interviewers, the evaluator who submitted the evaluation, or company HR.
- **Business Logic**:
  - Stamps `feedback_shared_at` with current timestamp.
  - Once published, candidate endpoints expose `numeric_score`, `candidate_feedback`, and `feedback_shared_at` in candidate resources while keeping `internal_notes` private.
