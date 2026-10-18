@echo off
title KSR Guru JI - Stop Server
color 0C

echo ===================================================
echo       KSR GURU JI - Stopping Web Server
echo ===================================================
echo.

echo Stopping Apache HTTP Server...
taskkill /F /IM httpd.exe >nul 2>&1
if %errorlevel% equ 0 (
    echo Apache server has been stopped.
) else (
    echo Apache server was not running.
)

echo.
echo ===================================================
echo   All Apache processes stopped.
echo ===================================================
echo.
timeout /t 3
