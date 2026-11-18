@echo off
chcp 65001 >nul
:: ============================================================
:: KSR Guru Ji - Mobile App Build Helper
:: Run this on your computer to build Android APK / AAB
:: ============================================================

title KSR Guru Ji App Builder

:: Auto-detect Android SDK platform-tools in user profile
if exist "%LOCALAPPDATA%\Android\Sdk\platform-tools\adb.exe" (
    set "PATH=%PATH%;%LOCALAPPDATA%\Android\Sdk\platform-tools"
)

echo.
echo  ==========================================
echo   KSR Guru Ji - Mobile App Builder v3.1
echo  ==========================================
echo.

:MENU
echo  ======================================================
echo   KSR GURU JI - MOBILE APP BUILD MENU (v3.1)
echo  ======================================================
echo.
echo   [1] Check Prerequisites (Node.js, Java, Android SDK)
echo   [2] Instant 1-Click Cloud APK / AAB (PWABuilder - Recommended)
echo   ------------------------------------------------------
echo   LOCAL CAPACITOR BUILD (requires Node.js + Android Studio):
echo   [3] Install Capacitor dependencies (npm install)
echo   [4] Add Android platform (npx cap add android)
echo   [5] Sync web assets (npx cap sync android)
echo   [6] Build DEBUG APK (testing on phone)
echo   [7] Build RELEASE APK (direct distribution)
echo   [8] Build AAB Bundle (for Google Play Store upload)
echo   [9] Open project in Android Studio
echo   ------------------------------------------------------
echo   DEVICE AND TESTING UTILITIES:
echo   [A] Launch App in Native Standalone Window (Chrome App Mode)
echo   [I] Install APK on Connected Phone (via ADB)
echo   [D] Open Downloads Folder (View APK, AAB, iOS files)
echo   [P] Test PWA and Downloads Hub in Browser
echo   [0] Exit
echo.
set "choice="
set /p choice="Enter your choice (1-9, A, I, D, P, 0): "
if not defined choice goto MENU

if /i "%choice%"=="1" goto CHECK
if /i "%choice%"=="2" goto CLOUD_BUILD
if /i "%choice%"=="3" goto INSTALL
if /i "%choice%"=="4" goto ADD_ANDROID
if /i "%choice%"=="5" goto SYNC
if /i "%choice%"=="6" goto BUILD_DEBUG
if /i "%choice%"=="7" goto BUILD_RELEASE
if /i "%choice%"=="8" goto BUILD_AAB
if /i "%choice%"=="9" goto OPEN_STUDIO
if /i "%choice%"=="A" goto LAUNCH_APP
if /i "%choice%"=="I" goto ADB_INSTALL
if /i "%choice%"=="D" goto OPEN_DOWNLOADS
if /i "%choice%"=="P" goto PWA_TEST
if /i "%choice%"=="0" goto EXIT
goto MENU

:CHECK
echo.
echo  Checking prerequisites on your machine...
echo.
node --version 2>nul && echo  [OK] Node.js: OK || echo  [MISSING] Node.js NOT found - download from https://nodejs.org
npm --version 2>nul && echo  [OK] npm: OK || echo  [MISSING] npm NOT found
where adb 2>nul && echo  [OK] ADB (Android Debug Bridge): OK || echo  [INFO] ADB not in global PATH
where java 2>nul && echo  [OK] Java: OK || echo  [MISSING] Java NOT found - download from https://adoptium.net
if exist "C:\Program Files\Google\Chrome\Application\chrome.exe" echo  [OK] Google Chrome: OK (supports Native App Mode)
echo.
echo  TIP: You do NOT need Node.js or Android Studio if you use
echo  Option [2] (PWABuilder Cloud Build) - builds APK and AAB online for free!
echo.
pause
goto MENU

:CLOUD_BUILD
echo.
echo  Opening PWABuilder (Microsoft's free cloud APK/AAB builder)...
echo.
echo  1. In the browser, enter: https://ksrguruji.com
echo  2. Click "Package for Stores"
echo  3. Choose "Android" - Download ready-to-run APK or AAB!
echo.
start https://www.pwabuilder.com/
pause
goto MENU

:LAUNCH_APP
echo.
echo  Launching KSR Guru Ji in Standalone Native App Mode...
if exist "C:\Program Files\Google\Chrome\Application\chrome.exe" (
    start "" "C:\Program Files\Google\Chrome\Application\chrome.exe" --app=http://localhost:8090/
    echo  Native App window launched!
) else (
    start http://localhost:8090/
)
goto MENU

:ADB_INSTALL
echo.
echo  Checking for connected Android devices via ADB...
where adb >nul 2>&1
if %errorlevel% neq 0 (
    echo  [ERROR] ADB not found in PATH.
    pause
    goto MENU
)
adb devices
echo.
if exist "%~dp0downloads\ksr-guru-ji.apk" (
    echo  Installing %~dp0downloads\ksr-guru-ji.apk to device...
    adb install -r "%~dp0downloads\ksr-guru-ji.apk"
    if %errorlevel% equ 0 (
        echo  [SUCCESS] App successfully installed on your phone!
    ) else (
        echo  [WARNING] Installation failed. Make sure USB Debugging is ON in phone settings.
    )
) else (
    echo  [ERROR] File downloads\ksr-guru-ji.apk not found.
)
pause
goto MENU

:OPEN_DOWNLOADS
echo.
echo  Opening downloads folder in Windows Explorer...
explorer "%~dp0downloads"
goto MENU

:INSTALL
echo.
echo  Installing Capacitor CLI and dependencies...
npm install
echo.
echo  [OK] Dependencies installed!
pause
goto MENU

:ADD_ANDROID
echo.
echo  Adding Android platform...
if not exist www mkdir www
if not exist www\index.html copy index.html www\index.html 2>nul
npx cap add android
echo.
echo  [OK] Android platform added!
echo  Find it at: android\app\src\main\
pause
goto MENU

:SYNC
echo.
echo  Syncing Capacitor (copies web assets to Android)...
npx cap sync android
echo.
echo  [OK] Sync complete!
pause
goto MENU

:BUILD_DEBUG
echo.
echo  Building DEBUG APK...
npx cap sync android
cd android
call gradlew.bat assembleDebug
cd ..
if exist android\app\build\outputs\apk\debug\app-debug.apk (
    copy /y android\app\build\outputs\apk\debug\app-debug.apk downloads\ksr-guru-ji.apk >nul 2>&1
    echo  Auto-copied to: downloads\ksr-guru-ji.apk
)
echo.
echo  [OK] DEBUG APK built!
echo  Find it at: android\app\build\outputs\apk\debug\app-debug.apk
echo.
echo  To install on your phone:
echo  adb install android\app\build\outputs\apk\debug\app-debug.apk
pause
goto MENU

:BUILD_RELEASE
echo.
echo  Building RELEASE APK...
npx cap sync android
cd android
call gradlew.bat assembleRelease
cd ..
if exist android\app\build\outputs\apk\release\app-release.apk (
    copy /y android\app\build\outputs\apk\release\app-release.apk downloads\ksr-guru-ji.apk >nul 2>&1
    echo  Auto-copied to: downloads\ksr-guru-ji.apk
)
echo.
echo  [OK] RELEASE APK built!
echo  Find it at: android\app\build\outputs\apk\release\app-release.apk
pause
goto MENU

:BUILD_AAB
echo.
echo  Building AAB (Google Play Store bundle)...
npx cap sync android
cd android
call gradlew.bat bundleRelease
cd ..
if exist android\app\build\outputs\bundle\release\app-release.aab (
    copy /y android\app\build\outputs\bundle\release\app-release.aab downloads\ksr-guru-ji.aab >nul 2>&1
    echo  Auto-copied to: downloads\ksr-guru-ji.aab
)
echo.
echo  [OK] AAB built!
echo  Find it at: android\app\build\outputs\bundle\release\app-release.aab
echo.
echo  Upload this .aab file to Google Play Console
pause
goto MENU

:OPEN_STUDIO
echo.
echo  Opening Android Studio...
npx cap open android
pause
goto MENU

:PWA_TEST
echo.
echo  Opening KSR Guru Ji in browser for testing...
start http://localhost:8090/downloads/ >nul 2>&1
echo.
echo  If your local server is not running, double-click start_server.bat.
echo  You can also test live at: https://ksrguruji.com/downloads/
echo.
echo  Tip: In Chrome - press F12 - Application tab - Manifest to inspect PWA!
pause
goto MENU

:EXIT
echo.
echo  Goodbye! KSR Guru Ji App Builder closed.
echo.
exit /b 0
