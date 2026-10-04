"""
Mombasa Mall Basement Parking - Automated License Plate Recognition (ALPR) Worker
Runs as a background Windows Service on Edge PC.
Monitors CCTV streams, detects Kenyan license plates, validates formats,
and synchronizes in real-time with the local PHP API.
"""

import os
import sys
import time
import re
import json
import base64
import argparse
import threading
import logging
from logging.handlers import RotatingFileHandler
from datetime import datetime
from collections import defaultdict, deque

import cv2
import numpy as np
import requests

# Set OpenCV FFMPEG RTSP to strictly use TCP to prevent UDP packet drop
os.environ["OPENCV_FFMPEG_CAPTURE_OPTIONS"] = "rtsp_transport;tcp"

# Load environment configuration
def load_env(env_path):
    env_vars = {}
    if os.path.exists(env_path):
        with open(env_path, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith("#") and "=" in line:
                    k, v = line.split("=", 1)
                    env_vars[k.strip()] = v.strip().strip('"').strip("'")
    return env_vars

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
CONFIG = load_env(os.path.join(SCRIPT_DIR, "config.env"))

# Configuration values with robust defaults
API_URL = CONFIG.get("API_URL", "http://127.0.0.1/parking/api/gate/plate-detected.php")
HEALTH_URL = CONFIG.get("HEALTH_URL", "http://127.0.0.1/parking/api/health.php")
API_KEY = CONFIG.get("API_KEY", "MOMBASA_PARKING_ALPR_SECRET_KEY_2026")
MIN_CONFIDENCE = float(CONFIG.get("MIN_CONFIDENCE", "0.70"))
DEBOUNCE_SECONDS = int(CONFIG.get("DEBOUNCE_SECONDS", "15"))
VOTING_COUNT = int(CONFIG.get("VOTING_COUNT", "2"))
FRAME_SKIP = int(CONFIG.get("FRAME_SKIP", "3"))
HEARTBEAT_SECONDS = int(CONFIG.get("HEARTBEAT_SECONDS", "30"))

ENTRANCE_RTSP = CONFIG.get("ENTRANCE_RTSP", "rtsp://admin:password@192.168.1.1:554/Streaming/Channels/101")
EXIT_RTSP = CONFIG.get("EXIT_RTSP", "rtsp://admin:password@192.168.1.1:554/Streaming/Channels/201")

# Configure Logging
log_file = os.path.join(SCRIPT_DIR, CONFIG.get("LOG_FILE", "../storage/logs/alpr.log"))
os.makedirs(os.path.dirname(log_file), exist_ok=True)

logger = logging.getLogger("MombasaALPR")
logger.setLevel(logging.INFO)
file_handler = RotatingFileHandler(log_file, maxBytes=10 * 1024 * 1024, backupCount=5, encoding="utf-8")
file_handler.setFormatter(logging.Formatter("[%(asctime)s] [%(levelname)s] %(message)s"))
console_handler = logging.StreamHandler(sys.stdout)
console_handler.setFormatter(logging.Formatter("[%(asctime)s] %(message)s", "%H:%M:%S"))
logger.addHandler(file_handler)
logger.addHandler(console_handler)

# Kenyan Plate Correction & Validation Logic (Mirrors PlateHelper.php)
DIGIT_TO_LETTER = {'0': 'O', '1': 'I', '8': 'B', '5': 'S', '2': 'Z'}
LETTER_TO_DIGIT = {'O': '0', 'I': '1', 'B': '8', 'S': '5', 'Z': '2', 'Q': '0', 'D': '0', 'G': '6'}

def correct_string(text, mapping):
    return "".join(mapping.get(c, c) for c in text)

def clean_kenyan_plate(raw_text):
    clean = re.sub(r'[^A-Za-z0-9]', '', raw_text).upper()
    length = len(clean)

    # Standard Civilian Plate (KDA 123A) -> LLL DDD L
    if length == 7 and clean.startswith("K"):
        l1 = correct_string(clean[0:3], DIGIT_TO_LETTER)
        d  = correct_string(clean[3:6], LETTER_TO_DIGIT)
        l2 = correct_string(clean[6:7], DIGIT_TO_LETTER)
        return l1 + d + l2

    # Motorcycle (KMDA 123A) -> LLLL DDD L
    if length == 8 and clean.startswith("KM"):
        l1 = correct_string(clean[0:4], DIGIT_TO_LETTER)
        d  = correct_string(clean[4:7], LETTER_TO_DIGIT)
        l2 = correct_string(clean[7:8], DIGIT_TO_LETTER)
        return l1 + d + l2

    # Government (GK 123A or GK A123)
    if length == 6 and clean.startswith("GK"):
        mid = clean[2:5]
        end = clean[5:6]
        if mid.isdigit():
            return "GK" + correct_string(mid, LETTER_TO_DIGIT) + correct_string(end, DIGIT_TO_LETTER)
        else:
            return "GK" + correct_string(clean[2:3], DIGIT_TO_LETTER) + correct_string(clean[3:6], LETTER_TO_DIGIT)

    return clean

def validate_kenyan_plate(clean_plate):
    # 1. Standard Civilian
    if re.match(r'^K[A-Z]{2}[0-9]{3}[A-Z]$', clean_plate):
        return True
    # 2. Motorcycle
    if re.match(r'^KM[A-Z]{2}[0-9]{3}[A-Z]$', clean_plate):
        return True
    # 3. Government (GK)
    if re.match(r'^GK([0-9]{3}[A-Z]|[A-Z][0-9]{3})$', clean_plate):
        return True
    # 4. County Government
    if re.match(r'^(0[1-9]|[1-4][0-9])CG[0-9]{3}[A-Z]$', clean_plate):
        return True
    # 5. Broad fallback (4 to 10 alphanumeric)
    return bool(re.match(r'^[A-Z0-9]{4,10}$', clean_plate))

# Thread-safe Camera Capture Worker
class CameraStreamWorker(threading.Thread):
    def __init__(self, camera_name, rtsp_url, roi_coords):
        super().__init__(daemon=True)
        self.camera_name = camera_name
        self.rtsp_url = rtsp_url
        self.roi_coords = roi_coords # [ymin, ymax, xmin, xmax] normalized
        self.latest_frame = None
        self.lock = threading.Lock()
        self.running = True
        self.last_frame_time = 0
        self.is_connected = False

    def run(self):
        logger.info(f"[{self.camera_name.upper()}] Initializing RTSP stream worker...")
        backoff = 2

        while self.running:
            cap = cv2.VideoCapture(self.rtsp_url, cv2.CAP_FFMPEG)
            cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)

            if not cap.isOpened():
                self.is_connected = False
                logger.warning(f"[{self.camera_name.upper()}] Connection failed. Reconnecting in {backoff}s...")
                time.sleep(backoff)
                backoff = min(backoff * 2, 30)
                continue

            self.is_connected = True
            backoff = 2
            logger.info(f"[{self.camera_name.upper()}] RTSP Stream connected successfully.")

            while self.running:
                ret, frame = cap.read()
                if not ret or frame is None:
                    logger.warning(f"[{self.camera_name.upper()}] Stream read failure or dropped frame.")
                    break

                with self.lock:
                    self.latest_frame = frame
                    self.last_frame_time = time.time()

            self.is_connected = False
            cap.release()
            time.sleep(1)

    def get_latest_roi_frame(self):
        with self.lock:
            if self.latest_frame is None or (time.time() - self.last_frame_time > 5.0):
                return None, None

            frame = self.latest_frame.copy()

        h, w = frame.shape[:2]
        ymin, ymax, xmin, xmax = self.roi_coords
        roi = frame[int(ymin * h):int(ymax * h), int(xmin * w):int(xmax * w)]
        return frame, roi

# Initialize OCR Engine
reader = None
try:
    import easyocr
    reader = easyocr.Reader(['en'], gpu=False, verbose=False)
    logger.info("EasyOCR CPU engine loaded successfully.")
except Exception as e:
    logger.warning(f"EasyOCR initialization warning: {e}. Falling back to OpenCV contour detector.")

# Multi-frame voting and debounce state
debounce_history = defaultdict(lambda: 0) # {plate: last_detected_timestamp}
voting_buffers   = defaultdict(lambda: deque(maxlen=VOTING_COUNT)) # {camera: deque([plates])}

def process_plate_ocr(roi_image):
    """Preprocesses cropped image and runs OCR."""
    if roi_image is None or roi_image.size == 0:
        return []

    # 1. Grayscale & Contrast Enhancement (CLAHE)
    gray = cv2.cvtColor(roi_image, cv2.COLOR_BGR2GRAY)
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    enhanced = clahe.apply(gray)

    # 2. Denoising
    denoised = cv2.bilateralFilter(enhanced, 9, 75, 75)

    results = []
    if reader:
        # EasyOCR detection on enhanced image
        ocr_out = reader.readtext(denoised, detail=1, paragraph=False)
        for bbox, raw_text, conf in ocr_out:
            if conf >= MIN_CONFIDENCE:
                clean = clean_kenyan_plate(raw_text)
                if validate_kenyan_plate(clean):
                    results.append((clean, raw_text, conf, bbox))
    return results

def send_detection_to_api(camera, clean_plate, raw_plate, confidence, full_frame):
    """Encodes snapshot and dispatches detection to PHP API."""
    now = time.time()
    last_seen = debounce_history[f"{camera}_{clean_plate}"]

    # Debounce check
    if (now - last_seen) < DEBOUNCE_SECONDS:
        return

    debounce_history[f"{camera}_{clean_plate}"] = now
    logger.info(f"[{camera.upper()}] ACCEPTED: {clean_plate} (Conf: {confidence*100:.1f}%)")

    # Encode full frame as JPEG Base64
    _, buffer = cv2.imencode(".jpg", full_frame, [cv2.IMWRITE_JPEG_QUALITY, 85])
    img_b64 = base64.b64encode(buffer).decode("utf-8")

    payload = {
        "camera": camera,
        "plate": clean_plate,
        "raw_plate": raw_plate,
        "confidence": round(float(confidence), 2),
        "image_base64": img_b64,
        "api_key": API_KEY,
    }

    try:
        res = requests.post(API_URL, json=payload, headers={"X-API-Key": API_KEY}, timeout=4)
        if res.status_code == 200:
            logger.info(f"[{camera.upper()}] Dispatched to API successfully.")
        else:
            logger.warning(f"[{camera.upper()}] API response {res.status_code}: {res.text}")
    except Exception as e:
        logger.error(f"[{camera.upper()}] Failed to contact PHP API: {e}")

# Background Health Heartbeat Worker
def heartbeat_worker(workers):
    while True:
        time.sleep(HEARTBEAT_SECONDS)
        try:
            cam_statuses = {}
            for name, w in workers.items():
                cam_statuses[name] = {
                    "status": "OK" if w.is_connected else "WARNING",
                    "message": "Stream active" if w.is_connected else "RTSP reconnecting",
                }

            payload = {
                "api_key": API_KEY,
                "message": "ALPR Python worker running on Windows Edge PC",
                "cameras": cam_statuses,
            }
            requests.post(HEALTH_URL, json=payload, headers={"X-API-Key": API_KEY}, timeout=3)
        except Exception:
            pass

# Main Runner
def main():
    parser = argparse.ArgumentParser(description="Mombasa Mall Basement Parking ALPR Worker")
    parser.add_argument("--test-image", type=str, help="Path to single image for OCR test")
    parser.add_argument("--show", action="store_true", help="Show live OpenCV visual preview window")
    args = parser.parse_args()

    # Mode 1: Test Image Diagnostics
    if args.test_image:
        logger.info(f"Running OCR diagnostic on test image: {args.test_image}")
        if not os.path.exists(args.test_image):
            logger.error("Test image not found!")
            sys.exit(1)
        img = cv2.imread(args.test_image)
        detections = process_plate_ocr(img)
        print("\n--- OCR TEST RESULTS ---")
        if not detections:
            print("No valid license plates detected.")
        for clean, raw, conf, bbox in detections:
            print(f"Clean Plate: {clean} | Raw: {raw} | Confidence: {conf*100:.1f}%")
        sys.exit(0)

    # Mode 2: Multi-camera Live RTSP Production Service
    logger.info("Starting Mombasa Mall ALPR Production Worker...")

    ent_roi = [float(x) for x in CONFIG.get("ENTRANCE_ROI", "0.40,0.90,0.15,0.85").split(",")]
    ext_roi = [float(x) for x in CONFIG.get("EXIT_ROI", "0.40,0.90,0.15,0.85").split(",")]

    workers = {
        "entrance": CameraStreamWorker("entrance", ENTRANCE_RTSP, ent_roi),
        "exit": CameraStreamWorker("exit", EXIT_RTSP, ext_roi),
    }

    for w in workers.values():
        w.start()

    # Start Heartbeat Thread
    threading.Thread(target=heartbeat_worker, args=(workers,), daemon=True).start()

    frame_counter = 0

    try:
        while True:
            frame_counter += 1
            time.sleep(0.04) # ~25 FPS check loop

            # Process every Nth frame to keep CPU load optimal
            if frame_counter % FRAME_SKIP != 0:
                continue

            for cam_name, worker in workers.items():
                full_frame, roi_frame = worker.get_latest_roi_frame()
                if roi_frame is None:
                    continue

                detections = process_plate_ocr(roi_frame)
                for clean, raw, conf, _ in detections:
                    buf = voting_buffers[cam_name]
                    buf.append(clean)

                    # Multi-frame voting verification
                    if buf.count(clean) >= VOTING_COUNT:
                        send_detection_to_api(cam_name, clean, raw, conf, full_frame)
                        buf.clear()

                if args.show:
                    cv2.imshow(f"Mombasa Mall - {cam_name.upper()} ROI", roi_frame)
                    if cv2.waitKey(1) & 0xFF == ord('q'):
                        raise KeyboardInterrupt

    except KeyboardInterrupt:
        logger.info("ALPR Worker stopped by user.")
        for w in workers.values():
            w.running = False
        if args.show:
            cv2.destroyAllWindows()

if __name__ == "__main__":
    main()
