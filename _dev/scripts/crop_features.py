#!/usr/bin/env python3
"""
Crop LokAlert screenshots for Apple iOS-style feature cards.
Each crop focuses on the most relevant UI area of the screenshot,
similar to how Apple crops their feature images on apple.com/ios.

All source images are 1080x2376 (Android screenshots).
Output: 1080x1200 crops focused on key UI areas, saved as high-quality JPEG.
"""

from PIL import Image, ImageDraw
import os

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(BASE, "App Screenshots incomplete")
OUT = os.path.join(BASE, "assets", "images", "feat-cards")
os.makedirs(OUT, exist_ok=True)

# Each entry: (filename, crop_box=(left, top, right, bottom), description)
# Source images are 1080x2376
CROPS = [
    # 1. Map with pinned location - show the map area with the pin
    # Top portion: status bar + map + pin marker
    ("app-map-pinned-location.jpg", (0, 80, 1080, 1350), "feat-map-location.jpg"),

    # 2. Search autocomplete - show search bar + dropdown results
    # Top portion with search UI
    ("app-search-autocomplete.jpg", (0, 80, 1080, 1350), "feat-search.jpg"),

    # 3. Alarm configuration - show the config panel with radius slider
    # Middle portion with the configuration UI
    ("app-new-alarm-config.jpg", (0, 450, 1080, 1720), "feat-alarm-config.jpg"),

    # 4. Alarm triggered display - show the full-screen alarm
    # Center portion showing the alarm notification
    ("app-alarm-display-slide.jpg", (0, 300, 1080, 1570), "feat-alarm-display.jpg"),

    # 5. Emoji picker - show the emoji grid
    # Lower portion with the emoji picker grid
    ("app-alarm-emoji-picker.jpg", (0, 600, 1080, 1870), "feat-emoji-picker.jpg"),

    # 6. Color themes - show the theme options
    # Middle portion with theme selector
    ("app-settings-color-themes.jpg", (0, 350, 1080, 1620), "feat-color-themes.jpg"),
]

def add_rounded_corners(img, radius=40):
    """Add rounded corners with alpha transparency."""
    # Convert to RGBA for transparency
    img = img.convert("RGBA")
    w, h = img.size
    
    # Create rounded mask
    mask = Image.new("L", (w, h), 255)
    draw = ImageDraw.Draw(mask)
    
    # Draw black corners (will be transparent)
    draw.rectangle([0, 0, radius, radius], fill=0)
    draw.rectangle([w - radius, 0, w, radius], fill=0)
    draw.rectangle([0, h - radius, radius, h], fill=0)
    draw.rectangle([w - radius, h - radius, w, h], fill=0)
    
    # Draw white circles at corners (will be opaque)
    draw.ellipse([0, 0, radius * 2, radius * 2], fill=255)
    draw.ellipse([w - radius * 2, 0, w, radius * 2], fill=255)
    draw.ellipse([0, h - radius * 2, radius * 2, h], fill=255)
    draw.ellipse([w - radius * 2, h - radius * 2, w, h], fill=255)
    
    img.putalpha(mask)
    return img

for src_name, box, out_name in CROPS:
    src_path = os.path.join(SRC, src_name)
    out_path = os.path.join(OUT, out_name)
    
    if not os.path.exists(src_path):
        print(f"  SKIP: {src_name} not found")
        continue
    
    img = Image.open(src_path)
    
    # Crop to the specified region
    cropped = img.crop(box)
    
    # Resize to a consistent width for web (540px wide = half of 1080, good for retina)
    w, h = cropped.size
    new_w = 540
    new_h = int(h * (new_w / w))
    resized = cropped.resize((new_w, new_h), Image.LANCZOS)
    
    # Save as high-quality JPEG (no rounded corners in file - CSS handles that)
    resized_rgb = resized.convert("RGB")
    resized_rgb.save(out_path, "JPEG", quality=90, optimize=True)
    
    print(f"  OK: {src_name} -> {out_name} ({new_w}x{new_h})")

print(f"\nDone! {len(CROPS)} crops saved to {OUT}")
