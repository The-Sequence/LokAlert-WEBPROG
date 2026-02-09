#!/usr/bin/env python3
"""Rename app screenshots to descriptive names."""
import os, shutil

folder = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'App Screenshots incomplete')

renames = {
    "Screenshot_2026-02-08-11-36-38-81_62cb8f718626560d6d480c451ac19cb3.jpg": "app-onboarding-privacy.jpg",
    "Screenshot_2026-02-08-11-37-13-09_62cb8f718626560d6d480c451ac19cb3.jpg": "app-map-search-home.jpg",
    "Screenshot_2026-02-08-11-37-17-90_62cb8f718626560d6d480c451ac19cb3.jpg": "app-alarms-empty.jpg",
    "Screenshot_2026-02-08-11-37-45-48_62cb8f718626560d6d480c451ac19cb3.jpg": "app-search-autocomplete.jpg",
    "Screenshot_2026-02-08-11-37-49-86_62cb8f718626560d6d480c451ac19cb3.jpg": "app-search-results.jpg",
    "Screenshot_2026-02-08-11-38-20-24_62cb8f718626560d6d480c451ac19cb3.jpg": "app-map-pinned-location.jpg",
    "Screenshot_2026-02-08-11-38-25-11_62cb8f718626560d6d480c451ac19cb3.jpg": "app-new-alarm-config.jpg",
    "Screenshot_2026-02-08-11-38-43-42_62cb8f718626560d6d480c451ac19cb3.jpg": "app-alarms-list.jpg",
    "Screenshot_2026-02-08-11-38-49-40_62cb8f718626560d6d480c451ac19cb3.jpg": "app-settings-cooldown.jpg",
    "Screenshot_2026-02-08-11-38-52-15_62cb8f718626560d6d480c451ac19cb3.jpg": "app-settings-sound-haptics.jpg",
    "Screenshot_2026-02-08-11-38-56-53_62cb8f718626560d6d480c451ac19cb3.jpg": "app-settings-vibration-theme.jpg",
    "Screenshot_2026-02-08-11-39-02-44_62cb8f718626560d6d480c451ac19cb3.jpg": "app-alarm-display-slide.jpg",
    "Screenshot_2026-02-08-11-39-05-03_62cb8f718626560d6d480c451ac19cb3.jpg": "app-alarm-display-swipe.jpg",
    "Screenshot_2026-02-08-11-39-07-17_62cb8f718626560d6d480c451ac19cb3.jpg": "app-alarm-display-tap.jpg",
    "Screenshot_2026-02-08-11-39-11-51_62cb8f718626560d6d480c451ac19cb3.jpg": "app-alarm-triggered.jpg",
    "Screenshot_2026-02-08-11-39-23-08_62cb8f718626560d6d480c451ac19cb3.jpg": "app-alarm-display-options.jpg",
    "Screenshot_2026-02-08-11-39-25-91_62cb8f718626560d6d480c451ac19cb3.jpg": "app-alarm-emoji-picker.jpg",
    "Screenshot_2026-02-08-11-39-37-87_62cb8f718626560d6d480c451ac19cb3.jpg": "app-settings-appearance.jpg",
    "Screenshot_2026-02-08-11-39-45-73_62cb8f718626560d6d480c451ac19cb3.jpg": "app-settings-color-themes.jpg",
    "Screenshot_2026-02-08-11-39-57-09_62cb8f718626560d6d480c451ac19cb3.jpg": "app-map-alarm-setup.jpg",
    "Screenshot_2026-02-08-11-40-18-84_62cb8f718626560d6d480c451ac19cb3.jpg": "app-demo-national-harbor.jpg",
    "Screenshot_2026-02-08-11-40-33-92_62cb8f718626560d6d480c451ac19cb3.jpg": "app-demo-national-city.jpg",
}

count = 0
for old, new in renames.items():
    old_path = os.path.join(folder, old)
    new_path = os.path.join(folder, new)
    if os.path.exists(old_path):
        os.rename(old_path, new_path)
        print(f"  {old[:40]}... -> {new}")
        count += 1
    elif os.path.exists(new_path):
        print(f"  Already renamed: {new}")
        count += 1
    else:
        print(f"  NOT FOUND: {old[:40]}...")

print(f"\nRenamed {count}/{len(renames)} files")
