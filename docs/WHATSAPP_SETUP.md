# Meta WhatsApp Cloud API Setup Guide for Mombasa Mall Security

This document provides the exact configuration and template copies required by Meta (Facebook Business Manager) to approve automated WhatsApp ticketing for Mombasa Mall Basement Parking.

---

## 1. Official Pre-Approved WhatsApp Message Templates

Under Meta Cloud API policies, all **business-initiated messages** (such as automatic entry and exit tickets) require pre-approved message templates. Submit the following two templates inside **Meta WhatsApp Manager > Message Templates**.

### Template 1: Entry Parking Ticket
- **Template Name**: `mombasa_parking_ticket`
- **Category**: `UTILITY`
- **Language**: English (`en_US` or `en`)
- **Header**: None (or Text: `Mombasa Mall Parking Ticket`)
- **Body Text**:
```text
Hello {{1}}, welcome to Mombasa Mall! 

Your parking ticket for vehicle {{2}} has been issued.
• Ticket ID: {{3}}
• Entry Time: {{4}}
• Destination: {{5}}

Parking is FREE for mall visitors. Please keep this ticket and present it to the security guard when exiting the basement. Have a pleasant visit!
```
- **Sample Values to supply in Meta approval form**:
  - `{{1}}`: Samuel Mwangi
  - `{{2}}`: KDA 123A
  - `{{3}}`: MM-20261003-0001
  - `{{4}}`: 03/10/2026 10:45
  - `{{5}}`: Naivas Supermarket

---

### Template 2: Exit Clearance Confirmation (Optional)
- **Template Name**: `mombasa_parking_exit`
- **Category**: `UTILITY`
- **Language**: English (`en_US` or `en`)
- **Body Text**:
```text
Hello {{1}}, thank you for shopping at Mombasa Mall!

Your vehicle {{2}} (Ticket #{{3}}) has departed the basement parking.
• Exit Time: {{4}}
• Total Duration: {{5}}

We hope to see you again soon. Drive safely!
```
- **Sample Values**:
  - `{{1}}`: Samuel Mwangi
  - `{{2}}`: KDA 123A
  - `{{3}}`: MM-20261003-0001
  - `{{4}}`: 03/10/2026 12:15
  - `{{5}}`: 1h 30m

---

## 2. Meta Cloud API Credentials Configuration

Once Meta approves your templates (usually within 1 to 15 minutes for utility templates):

1. Go to **developers.facebook.com** > Your App > **WhatsApp > API Setup**.
2. Copy your **Phone number ID**.
3. Under **Business Settings > System Users**, generate a **Permanent Access Token** with permissions:
   - `whatsapp_business_messaging`
   - `whatsapp_business_management`
4. Open `C:\xampp\htdocs\parking\config\config.php` and paste:
```php
'whatsapp' => [
    'enabled'          => true,
    'phone_number_id'  => 'PASTE_YOUR_PHONE_NUMBER_ID',
    'token'            => 'PASTE_YOUR_PERMANENT_SYSTEM_USER_TOKEN',
    'template_entry'   => 'mombasa_parking_ticket',
    'template_exit'    => 'mombasa_parking_exit',
],
```

---

## 3. Automated Queue Worker on Windows

The Edge PC uses an offline-resilient outbox queue. If the mall internet drops, tickets are safely stored in the `whatsapp_queue` table and dispatched automatically when connectivity returns.

Register the worker in Windows Task Scheduler:
```powershell
schtasks /create /tn "MombasaParking_WhatsAppWorker" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\parking\workers\whatsapp_worker.php" /sc minute /mo 1 /ru SYSTEM /f
```
