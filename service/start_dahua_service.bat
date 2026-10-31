@echo off
title Dahua Camera & ANPR Live Daemon (192.168.0.3)
echo =====================================================================
echo  MOMBASA MALL BASEMENT PARKING - DAHUA NVR CAMERA DAEMON
echo  Target: Dahua IVSS7108/H (192.168.0.3) on 192.168.0.0/24
echo  Entrance Gate 1 (Ch 1) ^| Exit Gate 2 (Ch 2)
echo =====================================================================
echo.

cd /d "%~dp0.."
if exist "C:\xampp\php\php.exe" (
    set PHP_EXE=C:\xampp\php\php.exe
) else (
    set PHP_EXE=php
)

echo Starting Dahua Camera Service in continuous background mode...
"%PHP_EXE%" service\dahua_camera_daemon.php
pause
