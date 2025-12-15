#!/bin/bash

# Test script for curriculum upload
# This script tests the Laravel API curriculum upload endpoint

API_URL="http://localhost:8000/api"
CSV_FILE="test-upload.csv"

echo "🧪 Testing Curriculum Upload API"
echo "=================================="
echo ""

# Step 1: Login
echo "📝 Step 1: Logging in as chairperson..."
LOGIN_RESPONSE=$(curl -s -X POST "$API_URL/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "chairperson@example.com",
    "password": "password"
  }')

TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*' | grep -o '[^"]*$')

if [ -z "$TOKEN" ]; then
  echo "❌ Login failed. Response:"
  echo "$LOGIN_RESPONSE"
  exit 1
fi

echo "✅ Login successful! Token: ${TOKEN:0:20}..."
echo ""

# Step 2: Get curricula list
echo "📚 Step 2: Fetching curricula list..."
CURRICULA_RESPONSE=$(curl -s -X GET "$API_URL/curricula" \
  -H "Authorization: Bearer $TOKEN")

CURRICULUM_ID=$(echo $CURRICULA_RESPONSE | grep -o '"id":"[^"]*' | head -1 | grep -o '[^"]*$')

if [ -z "$CURRICULUM_ID" ]; then
  echo "❌ No curricula found. Response:"
  echo "$CURRICULA_RESPONSE"
  exit 1
fi

echo "✅ Found curriculum ID: $CURRICULUM_ID"
echo ""

# Step 3: Upload CSV
echo "📁 Step 3: Uploading CSV to curriculum..."
if [ ! -f "$CSV_FILE" ]; then
  echo "❌ CSV file not found: $CSV_FILE"
  exit 1
fi

UPLOAD_RESPONSE=$(curl -s -X POST "$API_URL/curriculum/upload" \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@$CSV_FILE" \
  -F "curriculumId=$CURRICULUM_ID")

echo "Upload response: $UPLOAD_RESPONSE"
echo ""

# Step 4: Verify courses were added
echo "🔍 Step 4: Verifying courses were added..."
COURSES_RESPONSE=$(curl -s -X GET "$API_URL/curriculum/$CURRICULUM_ID/courses" \
  -H "Authorization: Bearer $TOKEN")

COURSES_COUNT=$(echo $COURSES_RESPONSE | grep -o '"code":"TEST' | wc -l)

echo "Found $COURSES_COUNT TEST courses"
echo ""

if [ "$COURSES_COUNT" -gt 0 ]; then
  echo "✅ SUCCESS! Courses were uploaded and are visible in the curriculum"
else
  echo "⚠️  No TEST courses found. Full response:"
  echo "$COURSES_RESPONSE"
fi
