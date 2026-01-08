#!/bin/bash

BASE_URL="https://smartline-it.com/api"
TOKEN=$(curl -s -X POST "$BASE_URL/v2/driver/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"phone":"+2011767463164","password":"password123"}' | jq -r '.data.token')

echo "Testing Weekly Report..."
curl -v -X GET "$BASE_URL/driver/auth/reports/weekly" \
  -H "Authorization: Bearer $TOKEN" 2>&1 | tail -100

