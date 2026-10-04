# Mombasa Mall Basement Parking - Troubleshooting & Diagnostics Guide

Plain-language troubleshooting procedures for hardware, networking, printers, cameras, and XAMPP issues.

---

## 1. Quick Troubleshooting Matrix

| Issue | Common Cause | Plain-Language Fix |
| :--- | :--- | :--- |
| **Printer Not Printing** | Thermal paper roll finished, Windows share offline, or spooler jammed | 1. Open printer cover, ensure thermal paper feeds from underneath, and close firmly.<br>2. Check Windows Settings > Printers > Ensure printer is named **POS80** and shared.<br>3. Open Admin Settings > Click **Test Print**.<br>4. Restart Windows Print Spooler: Run `net stop spooler && net start spooler` in Admin Command Prompt. |
| **Camera Not Detected (Status Red)** | Isolated CCTV NIC disconnected, NVR IP changed, or RTSP password incorrect | 1. Open PowerShell and run: `ping 192.168.1.1` to confirm NVR is reachable.<br>2. Test RTSP port: `Test-NetConnection -ComputerName 192.168.1.1 -Port 554`.<br>3. Verify credentials in `config/config.php` and `alpr/config.env`.<br>4. Open VLC Media Player > Media > Open Network Stream > Test `rtsp://admin:password@192.168.1.1:554/Streaming/Channels/101`. |
| **Guard Tablet Cannot Reach Server** | Tablet on wrong Wi-Fi or Windows Firewall blocking Port 80 | 1. Ensure tablet is connected to Mall Internal Wi-Fi (Subnet `192.168.0.x`).<br>2. On Edge PC, verify static IP is `192.168.0.50` (`ipconfig /all`).<br>3. Open Windows Firewall with Advanced Security > Inbound Rules > Add New Rule > Port > TCP 80 > Allow the Connection. |
| **XAMPP Apache Port 80 Conflict** | Windows IIS, Skype, or World Wide Web Publishing Service occupying Port 80 | 1. Open Admin CMD and run: `net stop W3SVC`<br>2. Disable IIS autostart: `sc config W3SVC start= disabled`<br>3. In XAMPP Control Panel, click **Config > Service and Port Settings** > Verify Apache is set to 80/443 > Click **Start**. |
| **WhatsApp Messages Not Sending** | Internet is down, or Meta Access Token expired / invalid | 1. If internet is down: Normal behavior. Tickets stay queued in `whatsapp_queue` and send automatically once internet restores.<br>2. If internet is online: Check token validity in developers.facebook.com > WhatsApp > API Setup.<br>3. Verify templates `mombasa_parking_ticket` and `mombasa_parking_exit` are approved in Meta WhatsApp Manager. |
| **Cloud Sync Shows "Edge Offline"** | Mall WAN disconnected, or HMAC Secret mismatch | 1. Test internet on Edge PC: `ping 8.8.8.8`.<br>2. Verify `cloud_sync.hmac_secret` and `cloud_sync.api_key` in `config/config.php` exactly match `cloud/config.cloud.php`.<br>3. Run `C:\xampp\php\php.exe C:\xampp\htdocs\parking\workers\sync_worker.php` manually in CMD to see full cURL debug output. |
| **ALPR Worker Shows High CPU Load** | Processing too many raw frames per second | 1. Open `alpr/config.env` and set `FRAME_SKIP=4` or `FRAME_SKIP=5`.<br>2. Check `ENTRANCE_ROI` coordinates to ensure only the vehicle road lane is cropped, cutting out unnecessary pixels. |

---

## 2. Emergency Recovery Commands

### Restart Apache & MySQL Windows Services
```cmd
net stop Apache2.4
net start Apache2.4

net stop mysql
net start mysql
```

### Restart ALPR Worker Service
If installed via NSSM:
```cmd
nssm restart MombasaALPR
```

If running via Task Scheduler or manual CMD:
```cmd
taskkill /f /im python.exe
start "" "C:\xampp\htdocs\parking\alpr\run_alpr.bat"
```

### Trigger On-Demand Database Backup
```cmd
call "C:\xampp\htdocs\parking\scripts\backup.bat"
```
Verifies file is generated in both `C:\xampp\htdocs\parking\storage\backups` and `D:\ParkingBackups`.
