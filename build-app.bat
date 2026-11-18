@echo off
:: ============================================================
:: KSR Guru Ji — Mobile App Build Helper
:: Run this on your computer to build Android APK / AAB
:: ============================================================

title KSR Guru Ji App Builder

echo.
echo  ██╗  ██╗███████╗██████╗      █████╗ ██████╗ ██████╗
echo  ██║ ██╔╝██╔════╝██╔══██╗    ██╔══██╗██╔══██╗██╔══██╗
echo  █████╔╝ ███████╗██████╔╝    ███████║██████╔╝██████╔╝
echo  ██╔═██╗ ╚════██║██╔══██╗    ██╔══██║██╔═══╝ ██╔═══╝
echo  ██║  ██╗███████║██║  ██║    ██║  ██║██║     ██║
echo  ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝    ╚═╝  ╚═╝╚═╝     ╚═╝
echo.
echo  KSR Guru Ji — Mobile App Builder v3.0
echo  ==========================================
echo.

:MENU
echo  ======================================================
echo   KSR GURU JI — MOBILE APP BUILD MENU (v3.0)
echo  ======================================================
echo.
echo   [1] Check Prerequisites (Node.js, Java, Android SDK)
echo   [2] 🚀 Instant 1-Click Cloud APK / AAB (PWABuilder — Recommended)
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
echo   UTILITIES:
echo   [D] Open Downloads Folder (View APK, AAB, iOS files)
echo   [P] Test PWA in Browser (Localhost & Downloads Hub)
echo   [0] Exit
echo.
set /p choice="Enter your choice (1-9, D, P, 0): "

if /i "%choice%"=="1" goto CHECK
if /i "%choice%"=="2" goto CLOUD_BUILD
if /i "%choice%"=="3" goto INSTALL
if /i "%choice%"=="4" goto ADD_ANDROID
if /i "%choice%"=="5" goto SYNC
if /i "%choice%"=="6" goto BUILD_DEBUG
if /i "%choice%"=="7" goto BUILD_RELEASE
if /i "%choice%"=="8" goto BUILD_AAB
if /i "%choice%"=="9" goto OPEN_STUDIO
if /i "%choice%"=="D" goto OPEN_DOWNLOADS
if /i "%choice%"=="P" goto PWA_TEST
if /i "%choice%"=="0" goto EXIT
goto MENU

:CHECK
echo.
echo  Checking prerequisites for local build...
echo.
node --version 2>nul && echo  ✅ Node.js: OK || echo  ❌ Node.js NOT found — download from https://nodejs.org
npm --version 2>nul && echo  ✅ npm: OK || echo  ❌ npm NOT found
where adb 2>nul && echo  ✅ ADB (Android): OK || echo  ⚠  ADB not in PATH — install Android Studio first
where java 2>nul && echo  ✅ Java: OK || echo  ❌ Java NOT found — download from https://adoptium.net
echo.
echo  💡 TIP: You do NOT need Node.js or Android Studio if you use
echo  Option [2] (PWABuilder Cloud Build) — builds APK and AAB online for free!
echo.
pause
goto MENU

:CLOUD_BUILD
echo.
echo  Opening PWABuilder (Microsoft's free cloud APK/AAB builder)...
echo.
echo  1. In the browser, enter: https://ksrguruji.com
echo  2. Click "Package for Stores"
echo  3. Choose "Android" -> Download ready-to-run APK or AAB!
echo.
start https://www.pwabuilder.com/
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
echo  ✅ Dependencies installed!
pause
goto MENU

:ADD_ANDROID
echo.
echo  Adding Android platform...
if not exist www mkdir www
if not exist www\index.html copy index.html www\index.html 2>nul
npx cap add android
echo.
echo  ✅ Android platform added!
echo  📂 Find it at: android\app\src\main\
pause
goto MENU

:SYNC
echo.
echo  Syncing Capacitor (copies web assets to Android)...
npx cap sync android
echo.
echo  ✅ Sync complete!
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
    echo  🚀 Auto-copied to: downloads\ksr-guru-ji.apk
)
echo.
echo  ✅ DEBUG APK built!
echo  📦 Find it at: android\app\build\outputs\apk\debug\app-debug.apk
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
    echo  🚀 Auto-copied to: downloads\ksr-guru-ji.apk
)
echo.
echo  ✅ RELEASE APK built!
echo  📦 Find it at: android\app\build\outputs\apk\release\app-release.apk
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
    echo  🚀 Auto-copied to: downloads\ksr-guru-ji.aab
)
echo.
echo  ✅ AAB built!
echo  📦 Find it at: android\app\build\outputs\bundle\release\app-release.aab
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
echo  Opening KSR Guru Ji & Downloads Center in browser for testing...
start http://localhost:8090/downloads/ >nul 2>&1
start http://localhost/downloads/ >nul 2>&1
echo.
echo  If your local server is not running, double-click start_server.bat.
echo  You can also test live at: https://ksrguruji.com/downloads/
echo.
echo  💡 Tip: In Chrome -> press F12 -> Application tab -> Manifest to inspect PWA!
pause
goto MENU

:EXIT
echo.
echo  Goodbye! KSR Guru Ji App Builder closed.
echo.
exit /b 0
