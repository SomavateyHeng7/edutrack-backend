# Curriculum Constraints API Documentation

## Overview
The Curriculum Constraints API allows chairpersons to manage custom constraints for curricula, including banned course combinations, GPA requirements, and credit thresholds.

## Endpoints

### 1. List All Constraints for a Curriculum
**GET** `/api/curricula/{id}/constraints`

Retrieves all constraints associated with a specific curriculum.

**Response:**
```json
{
  "success": true,
  "constraints": [
    {
      "id": "constraint-uuid",
      "curriculum_id": "curriculum-uuid",
      "type": "CUSTOM",
      "name": "Banned: CSX 1001 + CSX 2005",
      "description": "Students cannot take CSX 1001 and CSX 2005 together",
      "is_required": true,
      "config": {
        "type": "banned_combination",
        "courses": [
          {"id": "course-id-1", "code": "CSX 1001", "name": "Introduction to CS"},
          {"id": "course-id-2", "code": "CSX 2005", "name": "Programming Fundamentals"}
        ]
      },
      "created_at": "2025-12-30T10:00:00Z",
      "updated_at": "2025-12-30T10:00:00Z"
    }
  ]
}
```

---

### 2. Create a New Constraint
**POST** `/api/curricula/{id}/constraints`

Creates a new constraint for the specified curriculum.

**Request Body:**
```json
{
  "type": "CUSTOM",
  "name": "Banned: CSX 1001 + CSX 2005",
  "description": "Students cannot take CSX 1001 and CSX 2005 together",
  "isRequired": true,
  "config": {
    "type": "banned_combination",
    "courses": [
      {"id": "course-id-1", "code": "CSX 1001", "name": "Introduction to CS"},
      {"id": "course-id-2", "code": "CSX 2005", "name": "Programming Fundamentals"}
    ]
  }
}
```

**Constraint Types:**
- `MINIMUM_GPA` - Minimum GPA requirement
- `SENIOR_STANDING` - Senior standing requirement
- `TOTAL_CREDITS` - Total credit requirement
- `CATEGORY_CREDITS` - Category-specific credit requirement
- `CUSTOM` - Custom constraints (e.g., banned combinations)

**Success Response (201):**
```json
{
  "success": true,
  "message": "Constraint created successfully",
  "constraint": {
    "id": "new-constraint-uuid",
    "curriculum_id": "curriculum-uuid",
    "type": "CUSTOM",
    "name": "Banned: CSX 1001 + CSX 2005",
    "description": "Students cannot take CSX 1001 and CSX 2005 together",
    "is_required": true,
    "config": {...},
    "created_at": "2025-12-30T10:00:00Z",
    "updated_at": "2025-12-30T10:00:00Z"
  }
}
```

**Error Response (409) - Duplicate:**
```json
{
  "success": false,
  "message": "A constraint with this type and name already exists for this curriculum"
}
```

**Error Response (422) - Validation:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "type": ["The type field is required."],
    "name": ["The name field is required."]
  }
}
```

---

### 3. Delete a Constraint
**DELETE** `/api/curricula/{id}/constraints/{constraintId}`

Removes a specific constraint from the curriculum.

**Success Response:**
```json
{
  "success": true,
  "message": "Constraint deleted successfully"
}
```

**Error Response (404):**
```json
{
  "message": "No query results for model [App\\Models\\CurriculumConstraint]."
}
```

---

## Usage Examples

### Example 1: Create a Banned Combination Constraint
```javascript
const response = await fetch(`${API_BASE}/curricula/${curriculumId}/constraints`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  credentials: 'include',
  body: JSON.stringify({
    type: 'CUSTOM',
    name: 'Banned: CSX 3001 + CSX 3002',
    description: 'Students cannot take CSX 3001 and CSX 3002 together due to scheduling conflicts',
    isRequired: true,
    config: {
      type: 'banned_combination',
      courses: [
        { id: 'course-1-id', code: 'CSX 3001', name: 'Advanced Algorithms' },
        { id: 'course-2-id', code: 'CSX 3002', name: 'Data Structures II' }
      ]
    }
  }),
});
```

### Example 2: Create a Minimum GPA Constraint
```javascript
const response = await fetch(`${API_BASE}/curricula/${curriculumId}/constraints`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  credentials: 'include',
  body: JSON.stringify({
    type: 'MINIMUM_GPA',
    name: 'Minimum GPA for Major Courses',
    description: 'Students must maintain a minimum 2.5 GPA in major courses',
    isRequired: true,
    config: {
      minimum_gpa: 2.5,
      applies_to: 'major_courses'
    }
  }),
});
```

### Example 3: List All Constraints
```javascript
const response = await fetch(`${API_BASE}/curricula/${curriculumId}/constraints`, {
  credentials: 'include',
});
const data = await response.json();
console.log('Constraints:', data.constraints);
```

### Example 4: Delete a Constraint
```javascript
const response = await fetch(
  `${API_BASE}/curricula/${curriculumId}/constraints/${constraintId}`,
  {
    method: 'DELETE',
    credentials: 'include',
  }
);
```

---

## Database Schema

The `curriculum_constraints` table:
```
- id (string, primary key)
- curriculum_id (string, foreign key -> curricula.id)
- type (enum: MINIMUM_GPA, SENIOR_STANDING, TOTAL_CREDITS, CATEGORY_CREDITS, CUSTOM)
- name (string, max 255 characters)
- description (text, nullable)
- is_required (boolean, default true)
- config (json, nullable)
- created_at (timestamp)
- updated_at (timestamp)
```

**Unique Constraint:** `[curriculum_id, type, name]`

---

## Authentication
All endpoints require authentication via Laravel Sanctum. Include credentials in requests:
```javascript
fetch(url, { credentials: 'include' })
```

---

## Frontend Integration
These endpoints are used by `ConstraintsTab.tsx` in the course-audit frontend to manage curriculum-specific constraints like banned course combinations.
