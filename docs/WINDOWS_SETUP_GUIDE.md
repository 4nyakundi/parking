# Mombasa Mall Basement Parking Management System (60 Slots)
## Complete Windows Edge PC Installation & Production Deployment Guide

This guide provides step-by-step instructions for installing and running the system on a Windows 10/11 Edge PC using XAMPP (Apache + MySQL/MariaDB + PHP 8.2).

---

## 1. Network Topology & Dual Network Adapter Setup

The Edge PC uses **two physical Network Interface Cards (NICs)** to isolate CCTV video traffic from the mall LAN.

```
       [ MALL LAN / INTERNET ]                     [ CCTV NVR NETWORK ]
     192.168.0.1 (Default Gateway)                192.168.1.1 (NVR Streams)
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
                         XAMPP + ALPR Worker
                                    │
                    WiFi 192.168.0.x│ (Port 80)
                                    ▼
                        [ GUARD TABLET(S) ]
                 http://192.168.0.50/parking/guard/
```

### Step 1.1: Configure NIC 1 (Mall LAN / Internet)
1. Press `Win + R`, type `ncpa.cpl`, and press **Enter**.
2. Right-click the **Mall LAN** adapter > **Properties** > Double-click **Internet Protocol Version 4 (TCP/IPv4)**.
3. Select **Use the following IP address**:
   - **IP address**: `192.168.0.50`
   - **Subnet mask**: `255.255.255.0`
   - **Default gateway**: `192.168.0.1`
   - **Preferred DNS server**: `192.168.0.1` (or `8.8.8.8`)
4. Click **OK**.

### Step 1.2: Configure NIC 2 (Isolated CCTV NVR Network)
1. In `ncpa.cpl`, right-click the **CCTV** adapter > **Properties** > **TCP/IPv4**.
2. Select **Use the following IP address**:
   - **IP address**: `192.168.1.50`
   - **Subnet mask**: `255.255.255.0`
   - **Default gateway**: **LEAVE BLANK! (Crucial: do not set a gateway on NIC 2, or Windows will attempt to route internet through the offline NVR)**
3. Click **OK**.

### Step 1.3: Test NVR Connectivity
Open PowerShell and run:
```powershell
ping 192.168.1.1
Test-NetConnection -ComputerName 192.168.1.1 -Port 554
```
Ensure TCP port 554 (RTSP) returns `TcpTestSucceeded : True`.

---

## 2. XAMPP Installation & Service Configuration

1. Install **XAMPP for Windows** (PHP 8.2+) to `C:\xampp`.
2. Open `C:\xampp\xampp-control.exe` as **Administrator**.
3. Check the service checkboxes next to **Apache** and **MySQL** to register them as Windows Services:
   - Click the red **X** next to Apache > Click **Yes** to install Apache Service (`Apache2.4`).
   - Click the red **X** next to MySQL > Click **Yes** to install MySQL Service (`mysql`).
4. Click **Start** for both Apache and MySQL.
5. In your browser, open `http://localhost/phpmyadmin/` to verify database readiness.

---

## 3. Project Deployment & Database Import

1. Deploy the project files to:
   ```text
   C:\xampp\htdocs\parking\
   ```
2. Open Command Prompt and run the database schema import:
   ```cmd
   C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\parking\database\schema.sql
   ```
3. (Optional) Populate realistic demo data for initial training:
   ```cmd
   C:\xampp\mysql\bin\mysql.exe -u root mombasa_parking < C:\xampp\htdocs\parking\database\seed_demo.sql
   ```
4. Verify `C:\xampp\htdocs\parking\config\config.php` contains your local settings.

---

## 4. PHP Extensions & Composer Setup

1. Open `C:\xampp\php\php.ini` in Notepad.
2. Ensure the following extensions are enabled (remove leading semicolons `;`):
   ```ini
   extension=pdo_mysql
   extension=curl
   extension=gd
   extension=mbstring
   extension=openssl
   extension=zip
   extension=intl
   ```
3. Save `php.ini` and restart Apache via `C:\xampp\xampp-control.exe`.
4. Install thermal printer dependencies:
   ```cmd
   cd C:\xampp\htdocs\parking
   C:\xampp\php\php.exe composer.phar install
   ```

---

## 5. Thermal Receipt Printer Setup & Sharing

1. Connect the USB thermal receipt printer (58mm or 80mm ESC/POS).
2. Install the Windows driver provided by the manufacturer (e.g., POS-80 or Xprinter).
3. Open **Windows Settings > Bluetooth & Devices > Printers & Scanners** > Click the thermal printer.
4. Click **Printer Properties** > Go to the **Sharing** tab:
   - Check **Share this printer**.
   - Set **Share name**: `POS80`.
   - Click **Apply** and **OK**.
5. Test printer from Admin settings: Open `http://localhost/parking/admin/` > Navigate to **Hardware & Health** > Click **Test Print**.

---

## 6. Guard Tablet Configuration (Kiosk Mode)

1. Connect the guard tablet (iPad or Android tablet) to the mall Wi-Fi network.
2. In the tablet's browser (Google Chrome), navigate to:
   ```text
   http://192.168.0.50/parking/guard/
   ```
3. Set up Fullscreen / Kiosk Mode:
   - **Chrome on Android**: Tap the three dots menu > **Add to Home screen** > Opens as a standalone fullscreen app with no URL bar.
   - **Safari on iPad**: Tap Share icon > **Add to Home Screen**.
4. The guard logs in using their 4-digit PIN:
   - Guard 1 PIN: `1234`
   - Guard 2 PIN: `5678`
   - Supervisor PIN: `9999`

---

## 7. Windows Firewall Configuration

Allow incoming Port 80 traffic strictly on the local mall subnet (`192.168.0.0/24`):

Open PowerShell as Administrator and run:
```powershell
New-NetFirewallRule -DisplayName "Mombasa Mall Parking HTTP" `
    -Direction Inbound `
    -Protocol TCP `
    -LocalPort 80 `
    -RemoteAddress 192.168.0.0/24 `
    -Action Allow
```

---

## 8. Python ALPR Worker Service Installation

1. Install **Python 3.10+ (64-bit)** on Windows. Ensure you check **"Add python.exe to PATH"**.
2. Open Command Prompt and install dependencies:
   ```cmd
   cd C:\xampp\htdocs\parking\alpr
   pip install -r requirements.txt
   ```
3. Test camera feed recognition:
   ```cmd
   python alpr_service.py --show
   ```
4. Set up Auto-Start on Windows boot:
   Download **NSSM (Non-Sucking Service Manager)** and run:
   ```cmd
   nssm install MombasaALPR "C:\Users\<YourUser>\AppData\Local\Programs\Python\Python313\python.exe" "C:\xampp\htdocs\parking\alpr\alpr_service.py"
   nssm set MombasaALPR AppDirectory "C:\xampp\htdocs\parking\alpr"
   nssm set MombasaALPR Start SERVICE_AUTO_START
   nssm start MombasaALPR
   ```

---

## 9. Windows Task Scheduler Automated Tasks

Run these three commands in Admin Command Prompt to register the automated background jobs:

### Job 1: Cloud Sync Worker (Every 1 minute)
```cmd
schtasks /create /tn "MombasaParking_CloudSync" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\parking\workers\sync_worker.php" /sc minute /mo 1 /ru SYSTEM /f
```

### Job 2: WhatsApp Outbox Worker (Every 1 minute)
```cmd
schtasks /create /tn "MombasaParking_WhatsAppWorker" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\parking\workers\whatsapp_worker.php" /sc minute /mo 1 /ru SYSTEM /f
```

### Job 3: Permanent Nightly Database Backup (Daily at 02:00 AM)
```cmd
schtasks /create /tn "MombasaParking_NightlyBackup" /tr "C:\xampp\htdocs\parking\scripts\backup.bat" /sc daily /st 02:00 /ru SYSTEM /f
```

---

## 10. Remote Cloud Management Mirror Deployment

1. On your cPanel shared hosting (e.g. `cloud.mombasamall.co.ke`):
   - Upload the `/cloud/` folder to `public_html/remote/`.
   - In cPanel MySQL Databases, create database `mombasa_parking_cloud`.
   - Import `cloud/schema_cloud.sql` via phpMyAdmin.
   - Configure `cloud/config.cloud.php` with the cloud database credentials.
2. Management login:
   - URL: `https://cloud.mombasamall.co.ke/remote/`
   - Username: `management`
   - Password: `mall2026`

---

## 11. Unattended Power Recovery & Reliability

1. **BIOS Power Loss Recovery**: Restart Edge PC, press `F2` or `Del` to enter BIOS. Under **Power Management**, set **Restore on AC Power Loss** to **Power On**.
2. **Windows Auto-Login**: Run `netplwiz` and uncheck "Users must enter a username and password to use this computer".
3. **Dedicated UPS**: Connect the Edge PC, Ethernet switch, and thermal receipt printer to a reliable 1000VA+ Uninterruptible Power Supply (UPS).
4. **Daily Quiet Restart Schedule**: Schedule a safe Windows reboot at 04:00 AM daily via Task Scheduler:
   ```cmd
   schtasks /create /tn "MombasaParking_DailyReboot" /tr "shutdown /r /t 60 /f" /sc daily /st 04:00 /ru SYSTEM /f
   ```

---

## 12. End-to-End Verification Checklist

| Test Case | Verification Steps | Expected Result |
| :--- | :--- | :--- |
| **1. Driver Entry** | Open `http://localhost/parking/driver/`, submit plate `KDA 555A`, phone `0712345678`, store `Naivas`. | Request appears instantly on guard screen with audio chime. |
| **2. Guard Approval** | On guard screen, tap **ACCEPT & PRINT**. | Thermal ticket spools to POS80; record moves to active parking sessions; available slots decrements. |
| **3. Manual Check-in** | Tap **ADD CAR MANUALLY**, enter `KDB 888B`, store `Gym`. | Wizard completes, ticket prints, and occupancy updates. |
| **4. Exit Clearance** | Switch to **CARS LEAVING**, search `KDA 555A`, tap **CLEAR EXIT**. | Session marked COMPLETED; dwell time calculated; available slots increments. |
| **5. Offline Tolerance** | Disconnect internet cable from NIC 1. Perform check-in and exit. | System functions completely locally; offline banner displayed; zero data loss. |
| **6. Cloud Sync Catch-Up**| Reconnect NIC 1 internet cable. Wait 60 seconds. | Edge worker pushes all offline sessions to Cloud Mirror; status returns to green. |
| **7. Backup Integrity** | Run `scripts\backup.bat`. | SQL dump file verified in `storage\backups\` and `D:\ParkingBackups\`. |
