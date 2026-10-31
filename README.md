# Mombasa Mall Basement Parking Management System (60 Slots)
## Complete Installation, Setup & Production Deployment Guide

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![Database](https://img.shields.io/badge/Database-MariaDB%20%2F%20MySQL-orange.svg)](https://mariadb.org)
[![Platform](https://img.shields.io/badge/Platform-Windows%2010%20%2F%2011%20Edge%20PC-brightgreen.svg)](https://microsoft.com)
[![Offline First](https://img.shields.io/badge/Architecture-Offline--First%20Edge-success.svg)](#)

An automated, offline-first parking operations system designed for the **Mombasa Mall Basement Parking (60 slots capacity)**. The system runs locally on an on-premise Windows Edge PC, orchestrating Dahua dual-camera ANPR, guard tablet kiosks, QR self-check-in for visitors, thermal receipt printing, Meta WhatsApp Cloud API ticketing, and asynchronous cloud sync.

---

## Table of Contents

1. [System Architecture & Network Topology](#1-system-architecture--network-topology)
2. [Hardware & Software Prerequisites](#2-hardware--software-prerequisites)
3. [Step 1: Network & Dual NIC Configuration](#step-1-network--dual-nic-configuration)
4. [Step 2: XAMPP Installation & PHP Configuration](#step-2-xampp-installation--php-configuration)
5. [Step 3: Project Deployment & PHP Dependencies](#step-3-project-deployment--php-dependencies)
6. [Step 4: Database Setup & Migration](#step-4-database-setup--migration)
7. [Step 5: System Configuration (`config.php`)](#step-5-system-configuration-configphp)
8. [Step 6: ESC/POS Thermal Receipt Printer Setup](#step-6-escpos-thermal-receipt-printer-setup)
9. [Step 7: Windows Firewall & Tablet Kiosk Setup](#step-7-windows-firewall--tablet-kiosk-setup)
10. [Step 8: Dahua ANPR Worker & Python ALPR Setup](#step-8-dahua-anpr-worker--python-alpr-setup)
11. [Step 9: Background Workers & Task Scheduler](#step-9-background-workers--task-scheduler)
12. [Step 10: Remote Cloud Mirror Setup (cPanel)](#step-10-remote-cloud-mirror-setup-cpanel)
13. [Default Credentials & Access URLs](#default-credentials--access-urls)
14. [Verification & Acceptance Testing](#verification--acceptance-testing)
15. [Troubleshooting & Emergency Maintenance](#troubleshooting--emergency-maintenance)

---

## 1. System Architecture & Network Topology

The Windows Edge PC connects to two isolated physical networks via **Dual Network Interface Cards (NICs)** to keep high-bandwidth CCTV traffic completely off the mall's LAN:

```text
       [ MALL LAN / INTERNET ]                     [ CCTV NVR NETWORK ]
     192.168.0.1 (Default Gateway)                192.168.1.1 (NVR / Cameras)
                 │                                            │
                 ▼                                            ▼
         ┌───────────────┐                            ┌───────────────┐
         │ NIC 1 (LAN)   │                            │ NIC 2 (CCTV)  │
         │ 192.168.0.50  │                            │ 192.168.1.50  │
         │ Mask: /24     │                            │ Mask: /24     │
         │ GW:192.168.0.1│                            │ NO GATEWAY!   │
         └───────┬───────┘                            └───────┬───────┘
                 │                                            │
                 └──────────────────┬─────────────────────────┘
                                    ▼
                         [ WINDOWS EDGE PC ]
                         XAMPP (Apache + MariaDB)
                         Dahua ANPR / ALPR Workers
                         ESC/POS Printer Service (POS80)
                                    │
                    WiFi 192.168.0.x│ (Port 80)
                    ┌───────────────┴───────────────┐
                    ▼                               ▼
          [ GUARD TABLET(S) ]             [ DRIVER SMARTPHONES ]
     http://192.168.0.50/parking/guard/   http://192.168.0.50/parking/driver/
```

- **NIC 1 (Mall LAN / Internet)**: Communicates with guard tablets, driver QR scans, WhatsApp API, and cloud mirror.
- **NIC 2 (Isolated CCTV Network)**: Direct connection to Dahua NVR and ANPR cameras. **Must NEVER have a default gateway configured.**

---

## 2. Hardware & Software Prerequisites

### Hardware Requirements
- **Edge PC**: Intel Core i5 / AMD Ryzen 5 or higher, 8GB+ RAM, 256GB SSD, Dual Gigabit Ethernet NICs.
- **UPS**: 1000VA+ line-interactive UPS powering the Edge PC, network switch, and receipt printer.
- **Cameras**: 
  - Entrance ANPR: Dahua IP Camera (`192.168.1.230`)
  - Exit ANPR: Dahua IP Camera (`192.168.1.210`)
  - Dahua NVR: `192.168.1.3` (CCTV) / `192.168.0.3` (LAN)
- **Printer**: 80mm or 58mm USB ESC/POS Thermal Receipt Printer (e.g., Xprinter, POS-80).
- **Tablet**: Android tablet or iPad for Gate 1 & Gate 2 guard kiosks.

### Software Requirements
- **OS**: Windows 10 Pro or Windows 11 Pro 64-bit.
- **XAMPP for Windows**: Version 8.2.x (with Apache 2.4, MySQL / MariaDB 10.4+, and PHP 8.2).
- **Composer**: PHP package manager (`composer.phar` is included in the project root).
- **Python (Optional for local OCR ALPR fallback)**: Python 3.10+ 64-bit.

---

## Step 1: Network & Dual NIC Configuration

### 1.1 Configure NIC 1 (Mall LAN & Internet)
1. Press `Win + R`, type `ncpa.cpl`, and press **Enter**.
2. Right-click the **Mall LAN** adapter > **Properties** > Double-click **Internet Protocol Version 4 (TCP/IPv4)**.
3. Configure the static IP:
   - **IP address**: `192.168.0.50`
   - **Subnet mask**: `255.255.255.0`
   - **Default gateway**: `192.168.0.1`
   - **Preferred DNS server**: `192.168.0.1` (or `8.8.8.8`)
4. Click **OK**.

### 1.2 Configure NIC 2 (Isolated CCTV NVR Network)
1. In `ncpa.cpl`, right-click the **CCTV Network** adapter > **Properties** > **TCP/IPv4**.
2. Configure the camera subnet:
   - **IP address**: `192.168.1.50`
   - **Subnet mask**: `255.255.255.0`
   - **Default gateway**: **LEAVE COMPLETELY BLANK** (Setting a gateway here breaks internet access!).
   - **DNS servers**: **LEAVE BLANK**.
3. Click **OK**.

### 1.3 Verify Camera Connectivity
Open PowerShell and run:
```powershell
ping 192.168.1.230
ping 192.168.1.210
Test-NetConnection -ComputerName 192.168.1.3 -Port 554
```
*Expected: Responses received and `TcpTestSucceeded : True`.*

---

## Step 2: XAMPP Installation & PHP Configuration

1. Download and run the **XAMPP for Windows** installer (PHP 8.2.x). Install to `C:\xampp`.
2. Launch `C:\xampp\xampp-control.exe` as **Administrator**.
3. Register Apache and MySQL as persistent Windows Services:
   - Click the red **X** next to **Apache** > Click **Yes** to install the `Apache2.4` service.
   - Click the red **X** next to **MySQL** > Click **Yes** to install the `mysql` service.
4. Open `C:\xampp\php\php.ini` in a text editor.
5. Verify or enable the required extensions (remove any leading `;`):
   ```ini
   extension=pdo_mysql
   extension=curl
   extension=gd
   extension=mbstring
   extension=openssl
   extension=zip
   extension=intl
   extension=fileinfo
   ```
6. Set correct error logging and timezone settings in `php.ini`:
   ```ini
   date.timezone = "Africa/Nairobi"
   memory_limit = 256M
   upload_max_filesize = 32M
   post_max_size = 32M
   ```
7. Click **Start** for both Apache and MySQL in the XAMPP Control Panel.
8. Verify in your browser: `http://localhost/phpmyadmin/`.

---

## Step 3: Project Deployment & PHP Dependencies

1. Clone or extract the repository into the XAMPP web root:
   ```text
   C:\xampp\htdocs\parking\
   ```
2. Open Command Prompt (`cmd`) and navigate to the project directory:
   ```cmd
   cd C:\xampp\htdocs\parking
   ```
3. Install PHP dependencies using the bundled `composer.phar`:
   ```cmd
   C:\xampp\php\php.exe composer.phar install --no-dev --optimize-autoloader
   ```
   *This installs `mike42/escpos-php` for ESC/POS receipt thermal printing.*

4. Ensure required storage directories exist and have write permissions:
   ```cmd
   mkdir storage\logs
   mkdir storage\snapshots
   mkdir storage\backups
   ```

---

## Step 4: Database Setup & Migration

### Option A: Automated One-Click Setup (Recommended)
Run the bundled database setup script:
```cmd
C:\xampp\htdocs\parking\scripts\setup_database.bat
```
This batch script automatically:
1. Imports `database/schema.sql` into database `mombasa_parking`.
2. Loads baseline mall directory, default settings, and system users.
3. Imports `database/seed_demo.sql` (sample vehicles, active check-ins, VIP tags).
4. Initializes the local mirror database `mombasa_parking_cloud` from `cloud/schema_cloud.sql`.

### Option B: Manual Command-Line Import
Open Command Prompt and run:
```cmd
REM 1. Import main parking database
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\parking\database\schema.sql

REM 2. (Optional) Load sample demo data
C:\xampp\mysql\bin\mysql.exe -u root mombasa_parking < C:\xampp\htdocs\parking\database\seed_demo.sql

REM 3. Create cloud mirror database
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\parking\cloud\schema_cloud.sql
```

---

## Step 5: System Configuration (`config.php`)

All operational parameters are centralized in `config/config.php`. For local machine overrides, the system automatically merges `config/config.local.php`.

### 5.1 Local Database Override (`config/config.local.php`)
Ensure `config/config.local.php` matches your local XAMPP MySQL configuration:
```php
<?php
return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'dbname'   => 'mombasa_parking',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],
];
```

### 5.2 Key Settings in `config/config.php`
- **Application**:
  - `capacity`: `60` (Total basement bay capacity).
  - `overstay_hours`: `8` (Threshold to highlight parked cars exceeding 8 hours).
  - `timezone`: `'Africa/Nairobi'`.
- **Dahua ANPR / Cameras**:
  - `source_mode`: `'dahua_anpr'` (Listens directly to Dahua HTTP event streams).
  - Master Camera credentials: `admin` / `Mall@2024`.
- **Printer**:
  - `mode`: `'windows'` (or `'dummy'` for testing without physical hardware).
  - `printer_name`: `'POS80'`.
  - `paper_width`: `80`.

---

## Step 6: ESC/POS Thermal Receipt Printer Setup

1. Connect the USB receipt printer to the Edge PC.
2. Install the manufacturer's Windows driver (e.g., POS-80 series driver).
3. Open **Windows Settings > Bluetooth & Devices > Printers & Scanners**.
4. Select the installed printer > Click **Printer Properties** > Go to the **Sharing** tab:
   - Check **Share this printer**.
   - Set **Share name** to: `POS80`.
   - Click **Apply** and **OK**.
5. In `config/config.php`, set:
   ```php
   'printer' => [
       'mode'         => 'windows',
       'printer_name' => 'POS80',
       'paper_width'  => 80,
       ...
   ],
   ```
6. **Test Print**: Open `http://localhost/parking/admin/` > Navigate to **Hardware & Health** > Click **Test Print**.

---

## Step 7: Windows Firewall & Tablet Kiosk Setup

### 7.1 Windows Firewall Configuration
To allow the guard tablets to access the Edge PC on the mall subnet, open **PowerShell as Administrator** and run:
```powershell
New-NetFirewallRule -DisplayName "Mombasa Mall Parking HTTP" `
    -Direction Inbound `
    -Protocol TCP `
    -LocalPort 80 `
    -RemoteAddress 192.168.0.0/24 `
    -Action Allow
```

### 7.2 Guard Tablet Setup (Kiosk Mode)
1. Connect the guard tablet to the mall Wi-Fi network (Subnet `192.168.0.x`).
2. Open Google Chrome on the tablet and navigate to:
   ```text
   http://192.168.0.50/parking/guard/
   ```
3. Enable Standalone Kiosk Mode:
   - **Android Chrome**: Tap the 3 dots menu > Tap **Add to Home screen** > Opens in fullscreen without an address bar.
   - **iPad Safari**: Tap Share icon > Tap **Add to Home Screen**.
4. Test login using 4-digit PIN: `1234` (Guard 1) or `5678` (Guard 2).

### 7.3 Driver Entrance QR Code Stand
Place a sign or QR kiosk at the basement boom gate:
- URL encoded in QR Code: `http://192.168.0.50/parking/driver/`
- Mobile visitors scanning the code can self-register their plate, phone number, and store destination within 15 seconds.

---

## Step 8: Dahua ANPR Worker & Python ALPR Setup

The system includes two ANPR ingestion methods:

### Method 1: Native Dahua ANPR Stream Worker (Recommended)
The PHP Dahua worker connects directly to the event streams on both cameras:
- Entrance Camera: `http://192.168.1.230/cgi-bin/eventManager.cgi`
- Exit Camera: `http://192.168.1.210/cgi-bin/eventManager.cgi`

To test the worker interactively in Command Prompt:
```cmd
C:\xampp\php\php.exe C:\xampp\htdocs\parking\workers\dahua_anpr_worker.php
```

To run the Dahua Worker as a permanent Windows Service using **NSSM**:
```cmd
REM Download nssm.exe and run:
nssm install MombasaANPR "C:\xampp\php\php.exe" "C:\xampp\htdocs\parking\workers\dahua_anpr_worker.php"
nssm set MombasaANPR AppDirectory "C:\xampp\htdocs\parking\workers"
nssm set MombasaANPR Start SERVICE_AUTO_START
nssm start MombasaANPR
```

### Method 2: Python EasyOCR Worker (Secondary Fallback)
If processing raw RTSP streams via computer vision OCR:
1. Install Python 3.10+ (ensure "Add python.exe to PATH" is checked).
2. Install Python packages:
   ```cmd
   cd C:\xampp\htdocs\parking\alpr
   pip install -r requirements.txt
   ```
3. Review `alpr/config.env` and test live feed recognition:
   ```cmd
   python alpr_service.py --show
   ```

---

## Step 9: Background Workers & Task Scheduler

Register automated background jobs to handle cloud synchronization, WhatsApp message queuing, and daily database backups.

Open **Command Prompt as Administrator** and execute:

```cmd
REM 1. Cloud Sync Worker (Executes every 1 minute)
schtasks /create /tn "MombasaParking_CloudSync" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\parking\workers\sync_worker.php" /sc minute /mo 1 /ru SYSTEM /f

REM 2. WhatsApp Outbox Worker (Executes every 1 minute)
schtasks /create /tn "MombasaParking_WhatsAppWorker" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\parking\workers\whatsapp_worker.php" /sc minute /mo 1 /ru SYSTEM /f

REM 3. Daily Database Backup (Runs every morning at 02:00 AM)
schtasks /create /tn "MombasaParking_NightlyBackup" /tr "C:\xampp\htdocs\parking\scripts\backup.bat" /sc daily /st 02:00 /ru SYSTEM /f

REM 4. Daily Quiet Reboot (Optional: Reboots Edge PC at 04:00 AM)
schtasks /create /tn "MombasaParking_DailyReboot" /tr "shutdown /r /t 60 /f" /sc daily /st 04:00 /ru SYSTEM /f
```

To run an immediate manual trigger test of all workers:
```cmd
C:\xampp\htdocs\parking\scripts\run_workers.bat
```

---

## Step 10: Remote Cloud Mirror Setup (cPanel)

For mall management offsite monitoring:
1. Log in to your cPanel hosting account (e.g., `cloud.mombasamall.co.ke`).
2. Upload the `cloud/` directory to `public_html/remote/`.
3. In cPanel MySQL Databases:
   - Create database: `mombasa_parking_cloud`
   - Import `cloud/schema_cloud.sql` via phpMyAdmin.
4. Edit `cloud/config.cloud.php` with your cPanel database credentials.
5. In the Edge PC `config/config.php`:
   - Set `cloud_sync.enabled` to `true`.
   - Update `cloud_sync.endpoint` to `https://cloud.mombasamall.co.ke/remote/api/ingest.php`.
   - Ensure `hmac_secret` matches on both sides.

---

## Default Credentials & Access URLs

### Web Access URLs
| Portal | URL (Local Edge PC) | URL (Guard Tablets / Wi-Fi) |
| :--- | :--- | :--- |
| **Portal Gateway Router** | `http://localhost/parking/` | `http://192.168.0.50/parking/` |
| **Guard Station Kiosk** | `http://localhost/parking/guard/` | `http://192.168.0.50/parking/guard/` |
| **Driver Self Check-In** | `http://localhost/parking/driver/` | `http://192.168.0.50/parking/driver/` |
| **Admin & Supervisor Portal** | `http://localhost/parking/admin/` | `http://192.168.0.50/parking/admin/` |
| **Remote Cloud Management** | `http://localhost/parking/cloud/` | `https://cloud.mombasamall.co.ke/remote/` |
| **Database (phpMyAdmin)** | `http://localhost/phpmyadmin/` | Restricted to Localhost |

### System Credentials
| Role | Portal | Username / PIN | Password |
| :--- | :--- | :--- | :--- |
| **Guard 1 (Gate 1)** | Guard Station | PIN: `1234` | — |
| **Guard 2 (Gate 2)** | Guard Station | PIN: `5678` | — |
| **Supervisor** | Guard / Admin | PIN: `9999` or user `supervisor` | `super123` |
| **Administrator** | Admin Portal | `admin` | `admin123` |
| **Cloud Management** | Cloud Mirror | `management` | `mall2026` |
| **Dahua Cameras / NVR** | Hardware / RTSP | `admin` | `Mall@2024` |

---

## Verification & Acceptance Testing

Follow these quick verification steps to confirm end-to-end operational health:

| # | Test Scenario | Action | Expected Outcome |
| :-: | :--- | :--- | :--- |
| **1** | **Driver Intake** | Open `http://localhost/parking/driver/`, submit plate `KDA 555A`, phone `0712345678`, store `Naivas`. | Pop-up chime occurs instantly on the Guard Station screen. |
| **2** | **Guard Approval & Print** | On Guard Station, tap **ACCEPT & PRINT** for `KDA 555A`. | Receipt prints to thermal printer; available slots decrement by 1. |
| **3** | **Manual Check-In** | Tap **ADD CAR MANUALLY**, enter `KDB 888B`, select store `Gym`. | Session created, ticket generated, occupancy updated. |
| **4** | **Exit Clearance** | Switch to **CARS LEAVING**, search `KDA 555A`, tap **CLEAR EXIT**. | Session marked `COMPLETED`, duration calculated, available slots increment by 1. |
| **5** | **Offline Operation** | Unplug internet WAN cable from NIC 1. Perform check-ins and exits. | System operates with zero downtime. Offline indicator shown in banner. |
| **6** | **Cloud Synchronization**| Reconnect NIC 1 internet cable. Wait 60 seconds. | Edge worker pushes queued transactions to the Cloud Mirror. |
| **7** | **Database Backup** | Run `scripts\backup.bat`. | Verified `.sql` dump created in `storage\backups\` and `D:\ParkingBackups\`. |

---

## Troubleshooting & Emergency Maintenance

| Issue | Root Cause | Solution |
| :--- | :--- | :--- |
| **Printer Not Printing** | Paper roll empty, Windows share offline, or spooler jammed. | 1. Ensure thermal paper is loaded correctly (feeding from underneath).<br>2. Verify printer is named **POS80** and shared in Windows Printer Properties.<br>3. Open Admin Settings > Click **Test Print**.<br>4. Restart spooler: Run `net stop spooler && net start spooler` in Admin CMD. |
| **Camera Red / Offline** | CCTV network cable detached or IP mismatch. | 1. Ping NVR: `ping 192.168.1.3` and cameras: `ping 192.168.1.230`.<br>2. Confirm NIC 2 gateway is empty (`ipconfig /all`).<br>3. Verify camera credentials in `config/config.php` (`admin` / `Mall@2024`). |
| **Guard Tablet Unreachable** | Tablet on wrong Wi-Fi or Windows Firewall blocking Port 80. | 1. Connect tablet to Mall LAN Wi-Fi (`192.168.0.x`).<br>2. Verify Edge PC IP is `192.168.0.50`.<br>3. Re-run Windows Firewall script from [Step 7.1](#step-7-windows-firewall--tablet-kiosk-setup). |
| **Port 80 In Use / Apache Fails** | Windows IIS or World Wide Web Publishing Service running. | 1. In Admin CMD, run: `net stop W3SVC`<br>2. Disable IIS: `sc config W3SVC start= disabled`<br>3. In XAMPP Control Panel, start Apache. |
| **WhatsApp Messages Queued** | Internet offline or Meta Token expired. | 1. Messages will safely remain in `whatsapp_queue` and send when internet resumes.<br>2. Verify permanent Meta token in `config/config.php`. |

### Service Management Commands
```cmd
REM Restart Apache Service
net stop Apache2.4 && net start Apache2.4

REM Restart MySQL Service
net stop mysql && net start mysql

REM Trigger On-Demand Backup
call "C:\xampp\htdocs\parking\scripts\backup.bat"
```

---

## Security & Compliance Notice

This system operates in strict compliance with the **Kenya Data Protection Act 2019**:
- Vehicle license plates, visitor timestamps, and telephone numbers are collected strictly for parking validation and premises security.
- Data retention schedules, automated local database backups, and secure credential hashing are enforced out-of-the-box.

