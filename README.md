# Mombasa Mall Basement Parking Management System (60 Slots)
## Complete Installation, Hardware Wiring & Software Setup Manual

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![Database](https://img.shields.io/badge/Database-MariaDB%20%2F%20MySQL-orange.svg)](https://mariadb.org)
[![OS](https://img.shields.io/badge/OS-Windows%2010%20%2F%2011%20Pro-0078D6.svg)](https://microsoft.com)
[![Architecture](https://img.shields.io/badge/Architecture-Offline--First%20Edge-success.svg)](#)

A complete offline-first parking operations platform engineered for **Mombasa Mall Basement Parking (60 slots)**. The system runs on an on-premise Windows Edge PC, orchestrating Dahua dual-camera ANPR, guard tablet kiosks, QR-based visitor check-in, ESC/POS thermal ticket printing, Meta WhatsApp Cloud API notifications, and asynchronous cloud synchronization.

---

## ⚡ Quick Reference: URLs & Default Credentials

| Portal | URL (Edge PC / Wi-Fi) | Default Credentials |
| :--- | :--- | :--- |
| **Portal Router** | `http://localhost/parking/` (or `http://192.168.0.50/parking/`) | Automatic device routing (mobile vs. desktop) |
| **Guard Station Kiosk** | `http://localhost/parking/guard/` | Guard 1 PIN: `1234`<br>Guard 2 PIN: `5678`<br>Supervisor PIN: `9999` |
| **Driver Self Check-In** | `http://localhost/parking/driver/` | Public mobile check-in via QR code |
| **Admin & Supervisor Portal** | `http://localhost/parking/admin/` | Administrator: `admin` / `admin123`<br>Supervisor: `supervisor` / `super123` |
| **Remote Cloud Mirror** | `http://localhost/parking/cloud/` | Username: `management` / Password: `mall2026` |
| **Dahua Cameras & NVR** | Hardware RTSP & Web GUI | Username: `admin` / Password: `Mall@2024` |
| **phpMyAdmin** | `http://localhost/phpmyadmin/` | Username: `root` / Password: *(blank)* |

---

## 1. Network Cabling: Hikvision Switch vs. Internet Switch

Because your security cameras and mall Wi-Fi operate on **two different subnets**, the Edge PC needs access to both.

### Option A: Dual-NIC Setup (Recommended Best Practice)
The Edge PC connects to **BOTH switches simultaneously** using two physical Ethernet ports (Built-in LAN + a USB-to-Gigabit Ethernet adapter):

```text
                           ┌───────────────────────────┐
                           │      WINDOWS EDGE PC      │
                           └─────────────┬─────────────┘
                                         │
                 ┌───────────────────────┴───────────────────────┐
                 │                                               │
                 ▼                                               ▼
      [ NIC 1: Built-in LAN ]                         [ NIC 2: USB LAN Adapter ]
                 │                                               │
                 ▼                                               ▼
     [ Internet / Mall Switch ]                      [ Hikvision / CCTV Switch ]
        Subnet: 192.168.0.x                             Subnet: 192.168.1.x
     • PC IP: 192.168.0.50                           • PC IP: 192.168.1.50
     • Gateway: 192.168.0.1                          • Gateway: [LEAVE BLANK!]
                 │                                               │
     ┌───────────┴───────────┐                       ┌───────────┴───────────┐
     ▼                       ▼                       ▼                       ▼
Guard Tablets           Driver Phones           Entrance Camera         Exit Camera
& Cloud Sync            (QR Check-In)           (192.168.1.230)         (192.168.1.210)
```

**Why this is best:**
- **Zero Video Lag:** Heavy CCTV multicast/RTSP video is physically isolated from the mall Wi-Fi.
- **Tablet Connectivity:** Guard tablets and driver QR check-ins stay on `192.168.0.x`.
- **Active Internet:** WhatsApp API and cloud database backups reach the internet freely.

---

### Option B: Single-NIC Setup (Temporary Single Cable Workaround)
If the Edge PC currently has only **one physical Ethernet port** and no USB adapter:

1. Connect the Edge PC cable directly to the **Internet / Mall Switch** (`192.168.0.x`).
2. Connect the **NVR's LAN port** to that same **Internet Switch** and give the NVR static IP `192.168.0.3`.
3. In `config/config.php`, configure the system to pull snapshots and streams **through the NVR's LAN IP**:
   - Channel 1 (Entrance): `http://192.168.0.3/cgi-bin/snapshot.cgi?channel=1`
   - Channel 2 (Exit): `http://192.168.0.3/cgi-bin/snapshot.cgi?channel=2`

> [!CAUTION]
> **Never connect the Edge PC solely to the Hikvision CCTV switch.** If you do, guard tablets will not connect, driver mobile check-ins cannot load, WhatsApp tickets will fail, and cloud backups will be blocked.

---

## 2. Windows IP Configuration

### 2.1 Configure NIC 1 (Internet / Mall Switch)
1. Press `Win + R`, type `ncpa.cpl`, and press **Enter**.
2. Right-click the **Mall LAN** adapter > **Properties** > Double-click **Internet Protocol Version 4 (TCP/IPv4)**.
3. Configure the following:
   - **IP address**: `192.168.0.50`
   - **Subnet mask**: `255.255.255.0`
   - **Default gateway**: `192.168.0.1`
   - **Preferred DNS**: `192.168.0.1` (or `8.8.8.8`)
4. Click **OK**.

### 2.2 Configure NIC 2 (Hikvision / CCTV Switch)
1. In `ncpa.cpl`, right-click the **CCTV Network** adapter > **Properties** > **TCP/IPv4**.
2. Configure the following:
   - **IP address**: `192.168.1.50`
   - **Subnet mask**: `255.255.255.0`
   - **Default gateway**: **LEAVE COMPLETELY BLANK!**
   - **DNS servers**: **LEAVE BLANK!**
3. Click **OK**.

> [!IMPORTANT]
> Leaving the Default Gateway empty on NIC 2 is mandatory. If you enter a gateway on both cards, Windows attempts to route internet traffic through the offline camera switch, killing WhatsApp and cloud sync.

### 2.3 Verify Network Connectivity
Open PowerShell and test both paths:
```powershell
# 1. Test Internet / Mall Gateway
ping 192.168.0.1
ping 8.8.8.8

# 2. Test CCTV Cameras & NVR
ping 192.168.1.230
ping 192.168.1.210
Test-NetConnection -ComputerName 192.168.1.3 -Port 554
```

---

## 3. XAMPP & PHP 8.2 Installation

1. Download and install **XAMPP for Windows** (PHP 8.2+) to `C:\xampp`.
2. Open `C:\xampp\xampp-control.exe` as **Administrator**.
3. Register Apache and MySQL as persistent Windows Services:
   - Click the red **X** icon next to **Apache** > Click **Yes** (creates `Apache2.4` service).
   - Click the red **X** icon next to **MySQL** > Click **Yes** (creates `mysql` service).
4. Configure PHP extensions in `C:\xampp\php\php.ini`:
   Open `php.ini` in Notepad and ensure these lines are enabled (remove any leading `;`):
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
5. Set system timezone and upload limits in `php.ini`:
   ```ini
   date.timezone = "Africa/Nairobi"
   memory_limit = 256M
   upload_max_filesize = 32M
   post_max_size = 32M
   ```
6. Start Apache and MySQL in XAMPP Control Panel.
7. Confirm phpMyAdmin opens at `http://localhost/phpmyadmin/`.

---

## 4. Project Deployment & Dependencies

1. Place the project files in the web root:
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
   *This installs the `mike42/escpos-php` driver for thermal receipt printers.*

4. Ensure local storage directories exist:
   ```cmd
   if not exist "storage\logs" mkdir storage\logs
   if not exist "storage\snapshots" mkdir storage\snapshots
   if not exist "storage\backups" mkdir storage\backups
   ```

---

## 5. Database Setup & Initialization

### Option 1: Automated Script (Recommended)
Run the bundled database installation script:
```cmd
C:\xampp\htdocs\parking\scripts\setup_database.bat
```
This script automatically:
1. Creates the `mombasa_parking` database and executes `database/schema.sql`.
2. Seeds default mall tenants, system users, and security settings.
3. Imports sample vehicles and active sessions from `database/seed_demo.sql`.
4. Creates the local mirror database `mombasa_parking_cloud` from `cloud/schema_cloud.sql`.

### Option 2: Manual CLI Import
```cmd
REM 1. Master schema import
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\parking\database\schema.sql

REM 2. Demo seed data
C:\xampp\mysql\bin\mysql.exe -u root mombasa_parking < C:\xampp\htdocs\parking\database\seed_demo.sql

REM 3. Cloud mirror schema
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\parking\cloud\schema_cloud.sql
```

---

## 6. System Configuration (`config.php`)

All operational parameters are defined in `config/config.php`.

### 6.1 Local Database Override (`config/config.local.php`)
Verify that `config/config.local.php` matches your local XAMPP MariaDB credentials:
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

### 6.2 Key Master Settings (`config/config.php`)
- **Capacity**: `'capacity' => 60`
- **Overstay Threshold**: `'overstay_hours' => 8`
- **Timezone**: `'timezone' => 'Africa/Nairobi'`
- **Camera Source Mode**: `'source_mode' => 'dahua_anpr'`
- **Camera Credentials**: User: `admin` / Password: `Mall@2024`

---

## 7. Thermal Receipt Printer Setup (ESC/POS)

1. Connect the USB thermal receipt printer (80mm or 58mm) to the Edge PC.
2. Install the manufacturer's Windows printer driver (e.g., POS-80 series driver).
3. Open **Windows Settings > Bluetooth & Devices > Printers & Scanners** > Select the thermal printer.
4. Click **Printer Properties** > Go to the **Sharing** tab:
   - Check **Share this printer**.
   - Set **Share name** to: `POS80`.
   - Click **Apply** and **OK**.
5. In `config/config.php`, configure printer settings:
   ```php
   'printer' => [
       'mode'         => 'windows',
       'printer_name' => 'POS80',
       'paper_width'  => 80,
       'header_line1' => 'MOMBASA MALL',
       'header_line2' => 'BASEMENT PARKING - 60 SLOTS',
       'footer_text'  => 'Keep ticket safe. Present to security at exit.',
       'auto_cut'     => true,
   ],
   ```
6. **Test Print**: Open `http://localhost/parking/admin/` > Navigate to **Hardware & Health** > Click **Test Print**.

---

## 8. Windows Firewall & Guard Tablet Kiosks

### 8.1 Open Windows Firewall for Port 80
Allow the guard tablets on the mall Wi-Fi to reach the web server. Run in **PowerShell as Administrator**:
```powershell
New-NetFirewallRule -DisplayName "Mombasa Mall Parking HTTP" `
    -Direction Inbound `
    -Protocol TCP `
    -LocalPort 80 `
    -RemoteAddress 192.168.0.0/24 `
    -Action Allow
```

### 8.2 Guard Tablet Setup (Kiosk Mode)
1. Connect the guard tablet to the mall Wi-Fi network (Subnet `192.168.0.x`).
2. Open Chrome (Android) or Safari (iPad) and navigate to:
   ```text
   http://192.168.0.50/parking/guard/
   ```
3. Enable Standalone Kiosk Mode:
   - **Android Chrome**: Tap the 3 dots menu > **Add to Home screen** (creates a fullscreen app icon with no browser bar).
   - **iPad Safari**: Tap the Share button > **Add to Home Screen**.
4. Log in using the guard PIN:
   - Gate 1 Guard: `1234`
   - Gate 2 Guard: `5678`
   - Supervisor: `9999`

### 8.3 Driver Entrance QR Code Stand
Print a QR sign at the basement vehicle entrance:
- URL: `http://192.168.0.50/parking/driver/`
- Arriving drivers scan the code on their mobile phones to submit their plate, phone number, and destination store.

---

## 9. Dahua ANPR Worker Service Setup

The native Dahua ANPR worker (`workers/dahua_anpr_worker.php`) connects directly to the HTTP multipart event streams on both cameras:
- Entrance Camera: `http://192.168.1.230/cgi-bin/eventManager.cgi`
- Exit Camera: `http://192.168.1.210/cgi-bin/eventManager.cgi`

### 9.1 Test ANPR Worker Manually
Open Command Prompt and run:
```cmd
C:\xampp\php\php.exe C:\xampp\htdocs\parking\workers\dahua_anpr_worker.php
```

### 9.2 Install as Windows Service (via NSSM)
To ensure the worker runs 24/7 and restarts automatically on Windows boot:
```cmd
nssm install MombasaANPR "C:\xampp\php\php.exe" "C:\xampp\htdocs\parking\workers\dahua_anpr_worker.php"
nssm set MombasaANPR AppDirectory "C:\xampp\htdocs\parking\workers"
nssm set MombasaANPR Start SERVICE_AUTO_START
nssm start MombasaANPR
```

---

## 10. Background Tasks & Task Scheduler

Register the background tasks in Windows to handle cloud sync, WhatsApp ticket dispatches, and daily database backups.

Open **Command Prompt as Administrator** and execute:

```cmd
REM 1. Cloud Sync Worker (Runs every 1 minute)
schtasks /create /tn "MombasaParking_CloudSync" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\parking\workers\sync_worker.php" /sc minute /mo 1 /ru SYSTEM /f

REM 2. WhatsApp Outbox Worker (Runs every 1 minute)
schtasks /create /tn "MombasaParking_WhatsAppWorker" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\parking\workers\whatsapp_worker.php" /sc minute /mo 1 /ru SYSTEM /f

REM 3. Daily Database Backup (Runs every day at 02:00 AM)
schtasks /create /tn "MombasaParking_NightlyBackup" /tr "C:\xampp\htdocs\parking\scripts\backup.bat" /sc daily /st 02:00 /ru SYSTEM /f

REM 4. Automated Daily Reboot (Optional: Restarts Edge PC at 04:00 AM)
schtasks /create /tn "MombasaParking_DailyReboot" /tr "shutdown /r /t 60 /f" /sc daily /st 04:00 /ru SYSTEM /f
```

To run a manual test of all background tasks:
```cmd
C:\xampp\htdocs\parking\scripts\run_workers.bat
```

---

## 11. Verification & Acceptance Testing

Execute this 7-step checklist to verify end-to-end operational readiness:

| # | Test Scenario | Action | Expected Result |
| :-: | :--- | :--- | :--- |
| **1** | **Driver Intake** | Open `http://localhost/parking/driver/`, submit plate `KDA 555A`, phone `0712345678`, store `Naivas`. | Immediate chime and visual notification on the Guard Tablet screen. |
| **2** | **Guard Approval** | Tap **ACCEPT & PRINT** on the guard tablet. | Ticket prints on `POS80` printer; active vehicle count increments; available bays decrement. |
| **3** | **Manual Check-In** | Tap **ADD CAR MANUALLY**, enter `KDB 888B`, store `Gym`. | Session created immediately, ticket prints, and occupancy updates. |
| **4** | **Exit Clearance** | Switch to **CARS LEAVING**, search `KDA 555A`, tap **CLEAR EXIT**. | Session marked `COMPLETED`, dwell time calculated, and slot returned to pool. |
| **5** | **Offline Tolerance**| Disconnect internet WAN cable from NIC 1. Perform check-ins and exits. | System operates with zero downtime. Offline status banner displayed. |
| **6** | **Cloud Synchronization**| Reconnect NIC 1 internet cable. Wait 60 seconds. | Queued offline transactions push to Cloud Mirror; status returns to green. |
| **7** | **Database Backup** | Execute `scripts\backup.bat`. | Verified `.sql` dump generated in `storage\backups\` and `D:\ParkingBackups\`. |

---

## 12. Troubleshooting & Diagnostics

| Issue | Root Cause | Solution |
| :--- | :--- | :--- |
| **Printer Not Printing** | Out of paper, share name incorrect, or print spooler stalled. | 1. Ensure thermal paper feeds from underneath the roll.<br>2. In Windows Settings > Printers, verify printer is named **POS80** and shared.<br>3. Open Admin Settings > Click **Test Print**.<br>4. Restart spooler in Admin CMD: `net stop spooler && net start spooler`. |
| **Camera Red / Offline** | CCTV network cable detached or IP mismatch. | 1. Ping NVR: `ping 192.168.1.3` and cameras: `ping 192.168.1.230`.<br>2. Confirm NIC 2 gateway is completely blank (`ipconfig /all`).<br>3. Verify credentials in `config/config.php` (`admin` / `Mall@2024`). |
| **Guard Tablet Unreachable** | Tablet on wrong Wi-Fi or Windows Firewall blocking Port 80. | 1. Ensure tablet is on Mall Wi-Fi (`192.168.0.x`).<br>2. Confirm Edge PC IP is `192.168.0.50`.<br>3. Re-run Windows Firewall script from [Section 8.1](#81-open-windows-firewall-for-port-80). |
| **Apache Fails / Port 80 Occupied** | Windows IIS or World Wide Web Publishing Service running. | 1. Open Admin CMD and run: `net stop W3SVC`<br>2. Disable IIS: `sc config W3SVC start= disabled`<br>3. In XAMPP Control Panel, click **Start** for Apache. |
| **WhatsApp Messages Queued** | Internet offline or Meta Token expired. | 1. Messages will safely remain queued in `whatsapp_queue` and send when internet returns.<br>2. Verify permanent Meta token in `config/config.php`. |

### Service Management Quick Commands
```cmd
REM Restart Apache Service
net stop Apache2.4 && net start Apache2.4

REM Restart MySQL Service
net stop mysql && net start mysql

REM Restart Dahua ANPR Worker
nssm restart MombasaANPR

REM Run Manual On-Demand Backup
call "C:\xampp\htdocs\parking\scripts\backup.bat"
```
