# Graduation Portal Backend - Frontend Integration Guide

> **For Frontend Development with Claude Opus**  
> Last Updated: January 21, 2026

---

## Overview

The Laravel backend now supports PDPA-compliant graduation portal functionality. This document provides all the information needed to implement the frontend.

---

## Authentication Flow

### Student Submission Flow (No Login Required)

```
1. Browse portals     → GET /api/public/graduation-portals
2. View portal        → GET /api/public/graduation-portals/{id}
3. Verify PIN         → POST /api/public/graduation-portals/{id}/verify-pin
                        Request: { "pin": "GRAD123ABC" }
                        Response: { "session": { "token": "xxx", "expires_in_minutes": 15 } }
4. Submit courses     → POST /api/graduation-portals/{id}/submit
                        Header: X-Graduation-Session-Token: {token}
                        Body: See payload format below
```

### Chairperson/Advisor Flow (Requires Sanctum Auth)

- All existing auth flow remains the same
- New endpoints under `/api/graduation-portals/...`

---

## API Endpoints

### Public Endpoints (No Authentication)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/public/graduation-portals` | List active portals |
| `GET` | `/api/public/graduation-portals/{id}` | Portal details |
| `POST` | `/api/public/graduation-portals/{id}/verify-pin` | Verify PIN, get session token |
| `GET` | `/api/public/graduation-portals/{id}/curricula` | Get curricula for portal |

### Session-Authenticated Endpoints (Session Token in Header)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/graduation-portals/{id}/submit` | Submit courses for validation |

**Required Header:** `X-Graduation-Session-Token: {token}`

### Sanctum-Authenticated Endpoints (Chairperson/Advisor)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/graduation-portals/{id}/close` | Close portal |
| `POST` | `/api/graduation-portals/{id}/regenerate-pin` | Regenerate PIN |
| `GET` | `/api/graduation-portals/{id}/cache-submissions` | List cached submissions |
| `GET` | `/api/graduation-portals/{id}/cache-submissions/{subId}` | Get submission details |
| `POST` | `/api/graduation-portals/{id}/cache-submissions/{subId}/validate` | Validate submission |
| `POST` | `/api/graduation-portals/{id}/cache-submissions/{subId}/approve` | Approve submission |
| `POST` | `/api/graduation-portals/{id}/cache-submissions/{subId}/reject` | Reject submission |
| `GET` | `/api/graduation-portals/{id}/cache-submissions/{subId}/report` | Download report |
| `POST` | `/api/graduation-submissions/batch-validate` | Batch validate multiple |

---

## Request/Response Formats

### Submission Payload Format

```typescript
// POST /api/graduation-portals/{id}/submit
// Header: X-Graduation-Session-Token: {token}

interface SubmissionPayload {
  student_identifier: string;  // "John Doe - 6512345"
  curriculum_id: string;       // UUID
  courses: Course[];
  metadata?: {
    parsed_at?: string;
    file_name?: string;
    total_courses?: number;
  };
}

interface Course {
  code: string;        // "CS 101"
  name?: string;       // "Introduction to Computing"
  credits: number;     // 3
  grade: string;       // "A", "B+", "IP", "P"
  status: "completed" | "in_progress" | "planned" | "failed" | "withdrawn";
  semester?: string;   // "1/2024"
  category?: string;   // "Core", "Major Elective"
}
```

### Public Portal List Response

```json
{
  "portals": [
    {
      "id": "1",
      "name": "Graduation Check 2026",
      "description": "Submit your progress for graduation review",
      "batch": "2022",
      "deadline": "2026-02-15",
      "daysRemaining": 25,
      "acceptedFormats": [".xlsx", ".xls", ".csv"],
      "maxFileSizeMb": 5,
      "curriculum": {
        "id": "uuid",
        "name": "BSCS 2022",
        "year": 2022
      },
      "department": {
        "id": "uuid",
        "name": "Computer Science"
      }
    }
  ],
  "total": 1
}
```

### PIN Verification Request/Response

**Request:**
```json
{
  "pin": "GRAD123ABC"
}
```

**Success Response (200):**
```json
{
  "message": "PIN verified successfully",
  "session": {
    "token": "abc123def456...",
    "expires_in_minutes": 15,
    "expires_at": "2026-01-21T12:30:00Z"
  },
  "portal": {
    "id": "1",
    "name": "Graduation Check 2026",
    "curriculum_id": "uuid",
    "accepted_formats": [".xlsx", ".xls", ".csv"],
    "max_file_size_mb": 5
  }
}
```

**Error Response (401):**
```json
{
  "error": {
    "message": "Invalid PIN",
    "code": "INVALID_PIN",
    "attempts_remaining": 4
  }
}
```

**Rate Limited Response (429):**
```json
{
  "error": {
    "message": "Too many failed attempts. Please try again in 15 minutes.",
    "code": "RATE_LIMITED",
    "retry_after": 900
  }
}
```

### Submission Response

**Success (201):**
```json
{
  "message": "Submission received successfully",
  "submission": {
    "id": "uuid",
    "status": "pending",
    "expires_at": "2026-01-21T13:00:00Z",
    "course_count": 45
  }
}
```

### Validation Result Response

```json
{
  "message": "Validation completed",
  "submission": {
    "id": "uuid",
    "status": "validated",
    "validation_result": {
      "valid": true,
      "canGraduate": true,
      "summary": {
        "totalCreditsRequired": 120,
        "creditsCompleted": 118,
        "creditsInProgress": 6,
        "creditsPlanned": 3,
        "gpa": 3.45,
        "coursesMatched": 42,
        "coursesUnmatched": 3
      },
      "categoryProgress": {
        "Core": {
          "name": "Core",
          "creditsRequired": 45,
          "creditsCompleted": 45,
          "percentComplete": 100,
          "isComplete": true
        },
        "Major Elective": {
          "name": "Major Elective",
          "creditsRequired": 15,
          "creditsCompleted": 12,
          "percentComplete": 80,
          "isComplete": false
        }
      },
      "requirements": {
        "totalCredits": {
          "name": "Total Credits",
          "required": 120,
          "current": 118,
          "met": false,
          "message": "Need 2 more credits"
        },
        "gpa": {
          "name": "Minimum GPA",
          "required": 2.0,
          "current": 3.45,
          "met": true,
          "message": "GPA of 3.45 meets minimum 2.0"
        }
      },
      "errors": [],
      "warnings": ["Course CS 999 not found in curriculum"],
      "matchedCourses": [...],
      "unmatchedCourses": [...]
    }
  }
}
```

### Reject Submission Request

```json
{
  "reason": "Missing required internship course"
}
```

---

## Session Token Handling

### Storage
- Store the session token in memory or sessionStorage (NOT localStorage for security)
- Token expires in 15 minutes

### Usage
Add to all submission-related requests:
```typescript
headers: {
  'X-Graduation-Session-Token': sessionToken,
  'Content-Type': 'application/json'
}
```

### Expiry Handling
- Track `expires_at` from the response
- Show countdown to user
- On 401 with code `SESSION_EXPIRED`, redirect to PIN entry

---

## Error Codes

| Code | Description | Action |
|------|-------------|--------|
| `SESSION_TOKEN_MISSING` | No token provided | Redirect to PIN entry |
| `SESSION_EXPIRED` | Token expired | Redirect to PIN entry |
| `SESSION_IP_MISMATCH` | IP changed | Redirect to PIN entry |
| `SESSION_PORTAL_MISMATCH` | Wrong portal | Show error |
| `PORTAL_INACTIVE` | Portal closed/expired | Show message |
| `INVALID_PIN` | Wrong PIN | Show attempts remaining |
| `RATE_LIMITED` | Too many attempts | Show wait time |
| `SUBMISSION_EXPIRED` | Submission data expired | Ask to resubmit |

---

## Frontend Components Needed

### Student Side

1. **Portal Browser Page**
   - List active portals with deadline countdown
   - Filter by department/batch
   - Show portal details

2. **PIN Entry Modal**
   - PIN input field
   - Show attempts remaining on failure
   - Handle rate limiting

3. **Submission Form**
   - Student identifier input
   - Curriculum selector (pre-filled from portal)
   - File upload (Excel/CSV)
   - File preview before submit

4. **File Parser Utility**
   - Parse Excel using `xlsx` library
   - Parse CSV using `papaparse`
   - Convert to JSON format
   - Validate required columns

5. **Submission Confirmation**
   - Show submission ID
   - Show expiry countdown

### Chairperson/Advisor Side

1. **Portal Management**
   - "Close Portal" button
   - "Regenerate PIN" button (shows new PIN)
   - Show `closed_at` status

2. **Cache Submissions List**
   - List with expiry countdown
   - Status badges (pending, validated, approved, rejected)
   - "30-minute data retention" notice

3. **Submission Detail View**
   - Student identifier
   - Graduation eligibility badge
   - Credit progress charts
   - Category breakdown
   - Course list with status
   - Errors and warnings

4. **Validation Actions**
   - Validate button
   - Approve button
   - Reject button (with reason input)
   - Batch validate selected

---

## Grade to Status Mapping

Frontend should map grades to status before submission:

| Grade | Status |
|-------|--------|
| A, A-, B+, B, B-, C+, C, C-, D+, D, D-, S, P | `completed` |
| F | `failed` |
| W | `withdrawn` |
| IP, IN_PROGRESS, TAKING, CURRENT | `in_progress` |
| P (when planned), PLANNED, FUTURE, - | `planned` |

---

## Important Notes

1. **PDPA Compliance**: Submissions are stored in cache for 30 minutes only, then auto-deleted
2. **No Login Required**: Students don't need accounts, just the portal PIN
3. **File Parsing**: Must be done client-side, send JSON not files
4. **Session Duration**: 15 minutes from PIN verification
5. **Rate Limiting**: 5 PIN attempts per 15 minutes per IP
