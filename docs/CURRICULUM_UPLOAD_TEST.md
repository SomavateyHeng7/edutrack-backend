# Curriculum Upload API - Quick Test Guide

## Prerequisites
- Laravel server running (`php artisan serve`)
- Database connection configured
- At least one test user with CHAIRPERSON role
- At least one curriculum in the database

## Step 1: Get Authentication Token

Login as a CHAIRPERSON user:

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "chairperson@example.com",
    "password": "password"
  }'
```

Save the token from the response.

## Step 2: Get or Create a Curriculum

List curricula:
```bash
curl -X GET http://localhost:8000/api/curricula \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

Or create a new one:
```bash
curl -X POST http://localhost:8000/api/curricula \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Curriculum",
    "departmentId": "YOUR_DEPARTMENT_ID",
    "description": "Test curriculum for upload"
  }'
```

Save the curriculum ID.

## Step 3: Upload CSV

Using the sample CSV file:

```bash
curl -X POST http://localhost:8000/api/curriculum/upload \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -F "file=@storage/app/sample-curriculum-upload.csv" \
  -F "curriculumId=YOUR_CURRICULUM_ID"
```

Expected response:
```json
{
  "message": "Curriculum updated successfully",
  "coursesProcessed": 10
}
```

## Step 4: Verify Upload

Check the courses were added:

```bash
curl -X GET http://localhost:8000/api/curriculum/YOUR_CURRICULUM_ID/courses \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

You should see all 10 courses from the sample CSV.

## Step 5: Check Audit Log

You can verify the audit log was created by checking the database:

```sql
SELECT * FROM audit_logs 
WHERE entity_type = 'Curriculum' 
AND action = 'IMPORT' 
ORDER BY created_at DESC 
LIMIT 1;
```

## Common Issues

### 401 Unauthorized
- Check your token is valid
- Make sure you're logged in as a CHAIRPERSON

### 404 Curriculum not found
- Verify the curriculum ID is correct
- Make sure the curriculum belongs to a department in your faculty

### 400 Invalid CSV format
- Check your CSV has the required columns: code, name, credits, category
- Ensure no empty rows in the CSV
- Verify CSV is properly formatted (no extra commas, quotes properly escaped)

### 422 Validation Error
- Check the file is a valid CSV file
- Make sure curriculumId is provided
- Verify the file size isn't too large

## Testing with Postman

1. Create a new POST request to `http://localhost:8000/api/curriculum/upload`
2. Add Authorization header: `Bearer YOUR_TOKEN`
3. In Body, select `form-data`
4. Add key `file` with type `File` and select your CSV
5. Add key `curriculumId` with type `Text` and enter your curriculum ID
6. Send the request

## Sample Test Data

Create a simple test CSV (test-courses.csv):

```csv
code,name,credits,category
TEST101,Test Course 1,3,Core
TEST102,Test Course 2,3,Elective
TEST103,Test Course 3,4,General Education
```

Upload this first to test with fewer records.
