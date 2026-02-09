#!/usr/bin/env python3
"""
LokAlert Image Validator
========================
Uses macOS built-in Vision framework (via pyobjc) to classify
every image in assets/images/ and check if it matches the expected
content described in EXPECTED_LABELS.

Prerequisites (one-time):
    pip3 install pyobjc-framework-Vision pyobjc-framework-Quartz Pillow

Usage:
    python3 scripts/validate_images.py
"""

import os
import sys
import json
from pathlib import Path

# ---------------------------------------------------------------------------
# Expected content for each image  (substring match, case-insensitive)
# ---------------------------------------------------------------------------
EXPECTED_LABELS = {
    "happy-commuter.jpg":       ["person", "woman", "train", "subway", "bus", "commut", "smile"],
    "earphones-commuter.jpg":   ["person", "headphone", "earphone", "bus", "train", "commut"],
    "person-walking-city.jpg":  ["person", "walk", "street", "city", "night", "urban"],
    "phone-gps-closeup.jpg":    ["phone", "map", "gps", "mobile", "screen", "smartphone", "hand"],
    "hero-city.jpg":            ["city", "skyline", "urban", "building", "aerial"],
    "city-skyline.jpg":         ["city", "skyline", "building"],
    "city-streets.jpg":         ["city", "street", "road", "urban"],
    "bus-commute.jpg":          ["bus", "commut", "transit", "transport", "vehicle"],
    "city-night.jpg":           ["city", "night", "light", "urban", "dark"],
    "commuter-phone.jpg":       ["phone", "commut", "person", "mobile", "train"],
    "train-window.jpg":         ["train", "window", "rail", "transport"],
    "map-phone.jpg":            ["phone", "map", "screen", "hand"],
    "night-bus.jpg":            ["bus", "night", "light", "transport"],
    "city-aerial.jpg":          ["city", "aerial", "building", "urban", "sky"],
    "phone-navigate.jpg":       ["phone", "navigate", "map", "gps", "hand"],
    "train-platform.jpg":       ["train", "platform", "station", "rail"],
    "arrival-destination.jpg":  ["city", "destination", "building", "travel"],
    "sunset-commute.jpg":       ["sunset", "sky", "commut", "light", "evening"],
    "city-morning.jpg":         ["city", "morning", "sky", "building", "sunrise"],
}

# ---------------------------------------------------------------------------
# Vision framework helpers
# ---------------------------------------------------------------------------

def classify_image_vision(image_path: str, max_results: int = 10):
    """
    Use macOS Vision VNClassifyImageRequest to get image labels.
    Returns list of (label, confidence) tuples.
    """
    import objc
    from Foundation import NSURL
    import Vision

    url = NSURL.fileURLWithPath_(image_path)
    handler = Vision.VNImageRequestHandler.alloc().initWithURL_options_(url, None)
    request = Vision.VNClassifyImageRequest.alloc().init()

    success, error = handler.performRequests_error_([request], None)
    if not success or error:
        return [("ERROR", str(error))]

    results = request.results()
    if not results:
        return [("NO_RESULTS", 0.0)]

    out = []
    for obs in results[:max_results]:
        label = obs.identifier()
        confidence = obs.confidence()
        out.append((label, float(confidence)))
    return out


def classify_image_coreml(image_path: str):
    """
    Fallback: use PIL + basic heuristics if Vision import fails.
    Returns list of (label, confidence) tuples with basic checks.
    """
    try:
        from PIL import Image
        img = Image.open(image_path)
        w, h = img.size
        mode = img.mode
        labels = []
        labels.append(("image_loaded", 1.0))
        labels.append((f"size_{w}x{h}", 1.0))
        labels.append((f"mode_{mode}", 1.0))
        # Check dominant color channel
        if mode in ("RGB", "RGBA"):
            import statistics
            pixels = list(img.resize((50, 50)).getdata())
            r_avg = statistics.mean(p[0] for p in pixels)
            g_avg = statistics.mean(p[1] for p in pixels)
            b_avg = statistics.mean(p[2] for p in pixels)
            brightness = (r_avg + g_avg + b_avg) / 3
            if brightness < 80:
                labels.append(("dark_scene", 0.8))
            elif brightness > 180:
                labels.append(("bright_scene", 0.8))
            labels.append((f"brightness_{brightness:.0f}", 1.0))
        return labels
    except Exception as e:
        return [("FALLBACK_ERROR", str(e))]


# ---------------------------------------------------------------------------
# Main validation logic
# ---------------------------------------------------------------------------

def validate_images():
    project_root = Path(__file__).resolve().parent.parent
    images_dir = project_root / "assets" / "images"

    if not images_dir.exists():
        print(f"ERROR: Images directory not found: {images_dir}")
        sys.exit(1)

    # Determine classifier
    use_vision = False
    try:
        import Vision  # noqa: F401
        use_vision = True
        print("Using macOS Vision framework for classification\n")
    except ImportError:
        print("Vision framework not available. Trying to install pyobjc...")
        os.system(f"{sys.executable} -m pip install pyobjc-framework-Vision pyobjc-framework-Quartz --quiet")
        try:
            import Vision  # noqa: F811, F401
            use_vision = True
            print("Using macOS Vision framework for classification\n")
        except ImportError:
            print("WARNING: Could not load Vision framework. Using basic PIL fallback.\n")
            try:
                from PIL import Image  # noqa: F401
            except ImportError:
                os.system(f"{sys.executable} -m pip install Pillow --quiet")

    classify = classify_image_vision if use_vision else classify_image_coreml

    image_files = sorted(images_dir.glob("*.jpg")) + sorted(images_dir.glob("*.png"))
    if not image_files:
        print("No images found in", images_dir)
        sys.exit(1)

    print(f"Found {len(image_files)} images in {images_dir}\n")
    print("=" * 70)

    results = {}
    pass_count = 0
    fail_count = 0
    skip_count = 0

    for img_path in image_files:
        name = img_path.name
        print(f"\n  {name}")
        print(f"  {'─' * 40}")

        # Classify
        try:
            labels = classify(str(img_path))
        except Exception as e:
            print(f"    CLASSIFICATION ERROR: {e}")
            results[name] = {"status": "error", "error": str(e)}
            fail_count += 1
            continue

        # Print top labels
        top_labels = []
        for label, conf in labels[:8]:
            conf_str = f"{conf:.1%}" if isinstance(conf, float) else str(conf)
            top_labels.append(f"{label} ({conf_str})")

        print(f"    Labels: {', '.join(top_labels)}")

        # Check against expected
        expected = EXPECTED_LABELS.get(name)
        if not expected:
            print(f"    STATUS: SKIP (no expected labels defined)")
            results[name] = {"status": "skip", "labels": top_labels}
            skip_count += 1
            continue

        all_label_text = " ".join(lbl.lower() for lbl, _ in labels)
        matched = [kw for kw in expected if kw.lower() in all_label_text]
        match_ratio = len(matched) / len(expected)

        if match_ratio >= 0.15:  # At least 15% keyword hit = plausible
            status = "PASS"
            pass_count += 1
            color = "\033[92m"  # green
        else:
            status = "FAIL"
            fail_count += 1
            color = "\033[91m"  # red

        reset = "\033[0m"
        print(f"    Expected keywords: {expected}")
        print(f"    Matched: {matched} ({match_ratio:.0%})")
        print(f"    {color}STATUS: {status}{reset}")

        results[name] = {
            "status": status.lower(),
            "labels": top_labels,
            "expected": expected,
            "matched": matched,
            "match_ratio": match_ratio,
        }

    # Summary
    print("\n" + "=" * 70)
    print(f"\n  SUMMARY: {pass_count} passed, {fail_count} failed, {skip_count} skipped")
    print(f"  Total images: {len(image_files)}\n")

    # Save JSON report
    report_path = project_root / "scripts" / "image_report.json"
    with open(report_path, "w") as f:
        json.dump(results, f, indent=2, default=str)
    print(f"  Full report saved to: {report_path}\n")

    if fail_count > 0:
        print("  Some images may not match their expected content!")
        print("  Review the FAIL entries above and consider replacing them.\n")
        sys.exit(1)
    else:
        print("  All validated images look correct!\n")


if __name__ == "__main__":
    validate_images()
