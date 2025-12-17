# Curriculum Upload API

## Endpoint
`POST /api/curriculum/upload`

## Authentication
- Requires authentication using Sanctum token
- User must have `CHAIRPERSON` role
- User must have access to the curriculum's department (within their faculty)

## Request Format

### Headers
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

### Body Parameters
- `file` (required): CSV file containing course data
- `curriculumId` (required): UUID of the curriculum to upload courses to

### CSV Format

#### Required Columns
- `code` - Course code (e.g., CS101)
- `name` - Course name (e.g., Introduction to Programming)
- `credits` - Number of credits (integer, e.g., 3)
- `category` - Course category (e.g., Core, Elective, General Education)

#### Optional Columns
- `creditHours` - Credit hours format (e.g., 3-0-6). If not provided, calculated as `{credits}-0-{credits*2}`
- `description` - Course description

#### Example CSV
```csv
code,name,credits,category,creditHours,description
CS101,Introduction to Programming,3,Core,3-0-6,Basic programming concepts
CS102,Data Structures,3,Core,3-0-6,Introduction to fundamental data structures
CS201,Object-Oriented Programming,3,Core,3-0-6,OOP design using Java
```

A sample CSV file is available at: `storage/app/sample-curriculum-upload.csv`

## Response Format

### Success Response (200)
```json
{
  "message": "Curriculum updated successfully",
  "coursesProcessed": 10
}
```

### Error Responses

#### 401 Unauthorized
```json
{
  "error": "Unauthorized"
}
```

#### 403 Forbidden
```json
{
  "error": "User department not found"
}
```

#### 404 Not Found
```json
{
  "error": "Curriculum not found"
}
```

#### 400 Bad Request
```json
{
  "error": "Invalid CSV format. Required columns: code, name, credits, category"
}
```

#### 422 Validation Error
```json
{
  "message": "The file field is required.",
  "errors": {
    "file": ["The file field is required."],
    "curriculumId": ["The curriculum id field is required."]
  }
}
```

#### 500 Server Error
```json
{
  "error": "Error uploading curriculum: {error message}"
}
```

## How It Works

1. **Authentication Check**: Verifies the user is authenticated and has CHAIRPERSON role
2. **Access Control**: Checks if the user has access to the curriculum's department (must be in same faculty)
3. **CSV Parsing**: Reads and parses the uploaded CSV file
4. **Validation**: Ensures all required columns are present
5. **Transaction**: 
   - Removes all existing curriculum-course relationships
   - Creates or updates courses in the global course pool
   - Creates new curriculum-course relationships
   - Logs the action in the audit log
6. **Response**: Returns success message with count of processed courses

## Notes

- **Global Course Pool**: Courses are created/updated in a global pool and can be shared across curricula
- **Existing Courses**: If a course with the same code already exists, it will be updated with the new data
- **Atomic Operation**: The entire upload is wrapped in a database transaction - if any part fails, all changes are rolled back
- **Audit Trail**: All uploads are logged in the audit_logs table with details about which courses were uploaded
- **Logging**: Detailed logs are written to help with debugging (check `storage/logs/laravel.log`)

## Example Usage with cURL

```bash
curl -X POST http://localhost:8000/api/curriculum/upload \
  -H "Authorization: Bearer your-token-here" \
  -F "file=@/path/to/courses.csv" \
  -F "curriculumId=curriculum-uuid-here"
```

## Example Usage with JavaScript (Frontend)

```javascript
const formData = new FormData();
formData.append('file', file); // File object from input[type="file"]
formData.append('curriculumId', curriculumId);

const response = await fetch('/api/curriculum/upload', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
  },
  body: formData,
});

const data = await response.json();
console.log(`Processed ${data.coursesProcessed} courses`);
```

## Testing

1. Ensure you have a curriculum created
2. Get the curriculum ID from the database or API
3. Prepare a CSV file following the format above
4. Make a POST request with your CHAIRPERSON token
5. Check the response for success or error messages
6. Verify the courses were added by calling `GET /api/curriculum/{id}/courses`

## Related Endpoints

- `GET /api/curricula` - List all curricula
- `GET /api/curricula/{id}` - Get curriculum details
- `POST /api/curricula` - Create a new curriculum
- `GET /api/curriculum/{id}/courses` - Get courses in a curriculum
- `GET /api/curriculum/template` - Get curriculum template structure
