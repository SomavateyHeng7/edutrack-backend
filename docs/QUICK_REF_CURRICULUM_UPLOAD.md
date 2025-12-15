# Quick Reference: Curriculum CSV Upload

## Endpoint
```
POST /api/curriculum/upload
```

## Required Headers
```
Authorization: Bearer {your-token}
Content-Type: multipart/form-data
```

## Request Body
```
file: CSV file
curriculumId: UUID string
```

## CSV Format
```csv
code,name,credits,category,creditHours,description
CS101,Intro to Programming,3,Core,3-0-6,Basic concepts
```

**Required**: code, name, credits, category  
**Optional**: creditHours, description

## Success Response (200)
```json
{
  "message": "Curriculum updated successfully",
  "coursesProcessed": 10
}
```

## Error Responses
- **401**: Unauthorized (not logged in or not CHAIRPERSON)
- **403**: User department not found
- **404**: Curriculum not found
- **400**: Invalid CSV format
- **422**: Validation error (missing file/curriculumId)
- **500**: Server error

## Quick Test
```bash
# 1. Login
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"chair@example.com","password":"password"}'

# 2. Upload (replace TOKEN and CURRICULUM_ID)
curl -X POST http://localhost:8000/api/curriculum/upload \
  -H "Authorization: Bearer TOKEN" \
  -F "file=@sample.csv" \
  -F "curriculumId=CURRICULUM_ID"
```

## Sample CSV Location
```
storage/app/sample-curriculum-upload.csv
```

## Documentation
- Full API docs: `docs/CURRICULUM_UPLOAD_API.md`
- Testing guide: `docs/CURRICULUM_UPLOAD_TEST.md`
- Implementation: `docs/CURRICULUM_UPLOAD_IMPLEMENTATION.md`

## Key Features
✅ CHAIRPERSON role required  
✅ Faculty-level access control  
✅ Creates/updates courses globally  
✅ Atomic transaction (all-or-nothing)  
✅ Audit logging  
✅ Detailed error messages  
✅ Debug logging enabled
