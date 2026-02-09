#!/usr/bin/env python3
"""Quick HTML validator for index-premium.html"""
import re, os, sys, unicodedata

os.chdir(os.path.dirname(os.path.abspath(__file__)) + "/../..")

with open("index-premium.html", "r") as fh:
    f = fh.read()

lines = f.count("\n") + 1
print(f"Line count: {lines}")

# Emojis
emojis = [c for c in f if unicodedata.category(c).startswith("So")]
print(f"Emojis found: {len(emojis)}")
if emojis:
    print("  Emoji chars:", emojis[:10])

# HTML balance
opens = len(re.findall(r"<(?:div|section|header|footer|main|nav|article)[\s>]", f, re.I))
closes = len(re.findall(r"</(?:div|section|header|footer|main|nav|article)>", f, re.I))
print(f"Opening block tags: {opens}, Closing: {closes}, Balanced: {opens == closes}")

# Asset references
imgs = re.findall(r'(?:src|poster)=["\x27](assets/[^"\x27]+)["\x27]', f)
vids = re.findall(r'<source\s+src=["\x27](assets/[^"\x27]+)["\x27]', f)
all_assets = set(imgs + vids)
print(f"Total unique asset refs: {len(all_assets)}")
missing = [a for a in sorted(all_assets) if not os.path.exists(a)]
if missing:
    print("MISSING assets:")
    for m in missing:
        print(f"  - {m}")
else:
    print("All assets exist!")

# Leftover references
float_refs = re.findall(r"floatImgs|sh-float|stickyPhotos|sticky-photo", f)
if float_refs:
    print(f"WARNING: Leftover float/photo refs: {float_refs}")
else:
    print("No leftover float/photo references - clean!")

# Check video element
vid_els = re.findall(r"commuter-journey\.mp4", f)
print(f"commuter-journey.mp4 references: {len(vid_els)}")

# Check card-compact logic
compact_refs = re.findall(r"compact|borderRadius|card", f, re.I)
print(f"Card-compact JS references: {len(compact_refs)}")
