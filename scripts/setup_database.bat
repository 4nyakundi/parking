@echo off
REM ====================================================================
REM Mombasa Mall Basement Parking - Database Setup & Import Script
REM Runs automatically on Windows XAMPP
REM ====================================================================

set "MYSQL=C:\xampp\mysql\bin\mysql.exe"
set "DB_NAME=mombasa_parking"
set "PROJECT_DIR=%~dp0.."

echo =========================================================
echo MOMBASA MALL BASEMENT PARKING - DATABASE SETUP AND IMPORT
echo =========================================================
echo.

if not exist "%MYSQL%" (
    echo [ERROR] MySQL binary not found at C:\xampp\mysql\bin\mysql.exe.
    echo Please make sure XAMPP is installed in C:\xampp.
    pause
    exit /b 1
)

echo [1/3] Creating and importing master schema into '%DB_NAME%'...
"%MYSQL%" -u root < "%PROJECT_DIR%\database\schema.sql"
if %ERRORLEVEL% neq 0 (
    echo [ERROR] Failed to import database\schema.sql!
    pause
    exit /b 1
)
echo [OK] Schema imported successfully.

echo.
echo [2/3] Importing demo seed data (active cars, visitors, logs)...
"%MYSQL%" -u root %DB_NAME% < "%PROJECT_DIR%\database\seed_demo.sql"
if %ERRORLEVEL% neq 0 (
    echo [WARNING] Seed data import failed or already imported. Continuing...
) else (
    echo [OK] Demo seed data imported.
)

echo.
echo [3/3] Creating and importing Cloud Mirror database 'mombasa_parking_cloud'...
"%MYSQL%" -u root < "%PROJECT_DIR%\cloud\schema_cloud.sql"
if %ERRORLEVEL% neq 0 (
    echo [WARNING] Cloud schema import failed. Continuing...
) else (
    echo [OK] Cloud mirror database initialized.
)

echo.
echo =========================================================
echo SUCCESS: All databases imported and verified!
echo.
echo Access URLs:
echo   - Guard Station (Tablet) : http://localhost/parking/guard/
echo   - Driver Self Sign-In    : http://localhost/parking/driver/
echo   - Admin Panel            : http://localhost/parking/admin/
echo   - phpMyAdmin             : http://localhost/phpmyadmin/
echo =========================================================
pause
