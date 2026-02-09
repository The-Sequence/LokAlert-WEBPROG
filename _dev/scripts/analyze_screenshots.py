#!/usr/bin/env python3
"""Analyze app screenshots using macOS Vision OCR to identify each screen."""
import objc, os, sys
from Foundation import NSURL
import Quartz
import Vision

folder = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'App Screenshots incomplete')
files = sorted([f for f in os.listdir(folder) if f.endswith('.jpg')])

print(f"Found {len(files)} screenshots\n")

for f in files:
    path = os.path.join(folder, f)
    url = NSURL.fileURLWithPath_(path)
    img_src = Quartz.CGImageSourceCreateWithURL(url, None)
    if not img_src:
        print(f"SKIP: {f}")
        continue
    cg_img = Quartz.CGImageSourceCreateImageAtIndex(img_src, 0, None)
    if not cg_img:
        print(f"SKIP: {f}")
        continue

    handler = Vision.VNImageRequestHandler.alloc().initWithCGImage_options_(cg_img, None)
    request = Vision.VNRecognizeTextRequest.alloc().init()
    request.setRecognitionLevel_(1)
    request.setUsesLanguageCorrection_(True)
    
    success, error = handler.performRequests_error_([request], None)
    
    texts = []
    if success and request.results():
        for obs in request.results():
            candidate = obs.topCandidates_(1)
            if candidate:
                texts.append(candidate[0].string())
    
    short = f.replace('Screenshot_2026-02-08-', '').replace('_62cb8f718626560d6d480c451ac19cb3.jpg', '')
    combined = ' | '.join(texts[:20])
    print(f"[{short}]")
    print(f"  {combined[:400]}")
    print()
