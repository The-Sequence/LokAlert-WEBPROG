#!/usr/bin/env python3
"""Analyze earphones-commuter.jpg to find the face region."""
import os
from PIL import Image

os.chdir(os.path.dirname(os.path.abspath(__file__)) + "/../..")

img = Image.open("assets/images/earphones-commuter.jpg")
w, h = img.size
print(f"Image size: {w}x{h}")
print()

# Check specific points to find face
points = [
    (0.25, 0.15), (0.30, 0.15), (0.35, 0.15), (0.40, 0.15), (0.45, 0.15),
    (0.25, 0.20), (0.30, 0.20), (0.35, 0.20), (0.40, 0.20), (0.45, 0.20),
    (0.25, 0.25), (0.30, 0.25), (0.35, 0.25), (0.40, 0.25), (0.45, 0.25),
    (0.50, 0.25), (0.55, 0.25), (0.50, 0.20), (0.55, 0.20), (0.60, 0.20),
    (0.50, 0.30), (0.55, 0.30), (0.60, 0.30), (0.45, 0.30), (0.40, 0.30),
    (0.50, 0.35), (0.55, 0.35), (0.60, 0.35), (0.45, 0.35), (0.40, 0.35),
]

print("Scanning for skin tones (face region):")
print(f"{'X%':>5} {'Y%':>5} {'px_x':>6} {'px_y':>6} {'Bright':>7} {'Skin':>6}")
for xp, yp in sorted(points):
    cx, cy = int(w * xp), int(h * yp)
    region = img.crop((max(0, cx-40), max(0, cy-40), min(w, cx+40), min(h, cy+40)))
    pixels = list(region.getdata())
    avg_r = sum(p[0] for p in pixels) / len(pixels)
    avg_g = sum(p[1] for p in pixels) / len(pixels)
    avg_b_ch = sum(p[2] for p in pixels) / len(pixels)
    brightness = (avg_r + avg_g + avg_b_ch) / 3
    skin = avg_r - avg_g
    marker = " <-- FACE?" if skin > 8 and brightness > 60 else ""
    print(f"{xp*100:>4.0f}% {yp*100:>4.0f}% {cx:>6} {cy:>6} {brightness:>7.1f} {skin:>6.1f}{marker}")

# Find the highest skin-score region
print()
best = None
best_score = 0
for xp in range(10, 90, 2):
    for yp in range(5, 60, 2):
        cx, cy = int(w * xp / 100), int(h * yp / 100)
        region = img.crop((max(0, cx-30), max(0, cy-30), min(w, cx+30), min(h, cy+30)))
        pixels = list(region.getdata())
        avg_r = sum(p[0] for p in pixels) / len(pixels)
        avg_g = sum(p[1] for p in pixels) / len(pixels)
        brightness = sum(sum(p[:3])/3 for p in pixels) / len(pixels)
        skin = avg_r - avg_g
        score = skin * (brightness / 100) if skin > 0 else 0
        if score > best_score:
            best_score = score
            best = (xp, yp, cx, cy, brightness, skin)

if best:
    print(f"Best face candidate: {best[0]}% x, {best[1]}% y (px {best[2]},{best[3]})")
    print(f"  Brightness: {best[4]:.1f}, Skin warmth: {best[5]:.1f}")
    print(f"  Recommended object-position: {best[0]}% {best[1]}%")
