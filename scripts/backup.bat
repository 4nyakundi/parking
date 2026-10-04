@echo off
REM ====================================================================
REM Mombasa Mall Basement Parking - Automated Database Backup Script
REM Preserves permanent daily database snapshots with zero auto-deletion.
REM ====================================================================

REM 1. Configuration Paths
set "MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe"
set "DB_USER=root"
set "DB_PASS="
set "DB_NAME=mombasa_parking"

REM Backup destination directories
set "BACKUP_DIR_LOCAL=C:\xampp\htdocs\parking\storage\backups"
set "BACKUP_DIR_PRIMARY=D:\ParkingBackups"

REM 2. Generate date timestamp: YYYY-MM-DD_HHMMSS
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set "datetime=%%I"
set "YYYY=%datetime:~0,4%"
set "MM=%datetime:~4,2%"
set "DD=%datetime:~6,2%"
set "HH=%datetime:~8,2%"
set "MIN=%datetime:~10,2%"
set "SS=%datetime:~12,2%"

set "FILENAME=parking_%YYYY%-%MM%-%DD%_%HH%%MIN%%SS%.sql"

REM Ensure backup folders exist
if not exist "%BACKUP_DIR_LOCAL%" mkdir "%BACKUP_DIR_LOCAL%"
if not exist "%BACKUP_DIR_PRIMARY%" mkdir "%BACKUP_DIR_PRIMARY%"

echo [%date% %time%] Starting mysqldump for %DB_NAME%...

REM 3. Execute mysqldump
if "%DB_PASS%"=="" (
    "%MYSQLDUMP%" -u %DB_USER% --routines --triggers --single-transaction %DB_NAME% > "%BACKUP_DIR_LOCAL%\%FILENAME%"
) else (
    "%MYSQLDUMP%" -u %DB_USER% -p%DB_PASS% --routines --triggers --single-transaction %DB_NAME% > "%BACKUP_DIR_LOCAL%\%FILENAME%"
)

REM 4. Verify file creation and mirror to Drive D:\ (Permanent Retention Rule)
if exist "%BACKUP_DIR_LOCAL%\%FILENAME%" (
    echo Backup successful: %BACKUP_DIR_LOCAL%\%FILENAME%
    if exist "%BACKUP_DIR_PRIMARY%" (
        copy "%BACKUP_DIR_LOCAL%\%FILENAME%" "%BACKUP_DIR_PRIMARY%\%FILENAME%" >nul
        echo Mirrored permanent copy to %BACKUP_DIR_PRIMARY%\%FILENAME%
    )
) else (
    echo ERROR: Backup file failed to generate! Check disk space or MySQL credentials.
    exit /b 1
)

echo [%date% %time%] Backup completed successfully.
exit /b 0
