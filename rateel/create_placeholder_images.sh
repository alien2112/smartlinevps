#!/bin/bash

# Create placeholder images for the vehicle
convert -size 800x600 xc:lightblue -pointsize 60 -fill black \
  -gravity center -annotate +0+0 "Car Front\nPlate: ا ب ج-5747" \
  /root/new/vehicle/document/2026-01-09-39fba110e4279.webp 2>/dev/null

convert -size 800x600 xc:lightgreen -pointsize 60 -fill black \
  -gravity center -annotate +0+0 "Car Back\nPlate: ا ب ج-5747" \
  /root/new/vehicle/document/2026-01-09-98828dd4d0589.webp 2>/dev/null

if [ $? -eq 0 ]; then
  echo "✅ Placeholder images created successfully"
  ls -lh /root/new/vehicle/document/2026-01-09-*.webp
else
  echo "⚠️  ImageMagick not available. Creating blank files instead..."
  touch /root/new/vehicle/document/2026-01-09-39fba110e4279.webp
  touch /root/new/vehicle/document/2026-01-09-98828dd4d0589.webp
  echo "Created empty placeholder files"
fi

chmod 644 /root/new/vehicle/document/2026-01-09-*.webp
echo ""
echo "Images accessible at:"
echo "https://smartline-it.com/media/vehicle/document/2026-01-09-39fba110e4279.webp"
echo "https://smartline-it.com/media/vehicle/document/2026-01-09-98828dd4d0589.webp"

