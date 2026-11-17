@echo off
title KSR Guru JI - Web Server Launcher
color 0A

echo ===================================================
echo       KSR GURU JI - Starting Web Server
echo ===================================================
echo.

:: 1. Ensure MySQL is running
echo [1/3] Checking MySQL Service...
net start MYSQL80 >nul 2>&1
if %errorlevel% equ 0 (
    echo       MySQL (MYSQL80) service is RUNNING.
) else (
    net start wampmysqld64 >nul 2>&1
    if %errorlevel% equ 0 (
        echo       MySQL (wampmysqld64) service is RUNNING.
    ) else (
        echo       MySQL service is active or running.
    )
)

:: 2. Check if Apache is already running on port 8090
echo.
echo [2/3] Checking Apache HTTP Server status...
netstat -ano | findstr ":8090 " | findstr "LISTENING" >nul 2>&1
if %errorlevel% equ 0 (
    echo       Apache is ALREADY RUNNING on port 8090.
) else (
    echo       Starting Apache HTTP Server on Port 8090...
    net start wampapache64 >nul 2>&1
    timeout /t 1 /nobreak >nul
    netstat -ano | findstr ":8090 " | findstr "LISTENING" >nul 2>&1
    if %errorlevel% neq 0 (
        set INSTALL_DIR=c:/wamp64
        start /min "" "c:\wamp64\bin\apache\apache2.4.62.1\bin\httpd.exe"
        timeout /t 2 /nobreak >nul
    )
)

:: 3. Open browser
echo.
echo [3/3] Opening KSR Guru JI in your browser...
start http://localhost:8090/

echo.
echo ===================================================
echo   KSR GURU JI is live at: http://localhost:8090/
echo   WAMP Dashboard is at:   http://localhost:8090/wamp/
echo   To stop the server, double-click stop_server.bat
echo ===================================================
echo.
pause
