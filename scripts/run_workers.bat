@echo off
REM Helper batch to manually trigger workers on the Edge PC
set "PHP=C:\xampp\php\php.exe"
set "PROJECT=C:\xampp\htdocs\parking"

echo ===================================================
echo MOMBASA MALL BASEMENT PARKING - WORKER TRIGGER
echo ===================================================
echo 1. Running Cloud Sync Worker...
"%PHP%" "%PROJECT%\workers\sync_worker.php"
echo.
echo 2. Running WhatsApp Queue Worker...
"%PHP%" "%PROJECT%\workers\whatsapp_worker.php"
echo.
echo 3. Running Database Backup...
call "%PROJECT%\scripts\backup.bat"
echo.
echo All tasks executed. Press any key to exit.
pause >nul
