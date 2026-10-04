#!/usr/bin/env python3
"""
Simple CLI test runner for sample vehicle images.
Usage: python test_image.py sample_car.jpg
"""
import sys
import os

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from alpr_service import process_plate_ocr, clean_kenyan_plate, validate_kenyan_plate
import cv2

def test_file(image_path):
    if not os.path.exists(image_path):
        print(f"Error: File {image_path} does not exist.")
        return

    print(f"Loading {image_path}...")
    img = cv2.imread(image_path)
    if img is None:
        print("Failed to decode image.")
        return

    results = process_plate_ocr(img)
    if not results:
        print("No license plates recognized with current confidence threshold.")
    else:
        for clean, raw, conf, bbox in results:
            print(f"SUCCESS: Plate = {clean} (Raw OCR: '{raw}', Confidence: {conf*100:.1f}%)")

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python test_image.py <path_to_image.jpg>")
    else:
        test_file(sys.argv[1])
