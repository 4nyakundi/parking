@echo off
title Dahua ANPR Worker - Mombasa Mall (192.168.1.230 & 192.168.1.210)
set "PHP=C:\xampp\php\php.exe"
set "PROJECT=C:\xampp\htdocs\parking"

:loop
echo [%date% %time%] Starting Dahua Dual-Camera ANPR Worker...
"%PHP%" "%PROJECT%\workers\dahua_anpr_worker.php"
echo [%date% %time%] ANPR Worker exited or crashed. Restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto loop
