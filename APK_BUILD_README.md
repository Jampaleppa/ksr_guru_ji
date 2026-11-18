# 📱 KSR Guru Ji — APK / AAB Build Guide

## Quick Start

### Option A — Free Online (No Android Studio needed)
👉 Go to **https://www.pwabuilder.com**
1. Enter: `https://ksrguruji.com`
2. Click **Package for stores → Android**
3. Download `.apk` or `.aab` — done!

---

### Option B — Local Build with Capacitor

**Prerequisites:**
- Node.js v18+: https://nodejs.org
- Android Studio: https://developer.android.com/studio
- Java JDK 17+: https://adoptium.net

```bat
# Step 1 — Install dependencies
npm install

# Step 2 — Add Android platform (first time only - www/ is already pre-configured)
npx cap add android

# Step 3 — Sync assets
npx cap sync android

# Step 4a — Debug APK (testing)
cd android && gradlew assembleDebug

# Step 4b — Release APK (share with users - auto-copied to downloads\ksr-guru-ji.apk)
cd android && gradlew assembleRelease

# Step 4c — AAB (Google Play Store - auto-copied to downloads\ksr-guru-ji.aab)
cd android && gradlew bundleRelease
```

**Or use the interactive builder:**
```bat
build-app.bat
```

---

### Output Files

| Command | Output | Use for |
|---------|--------|---------|
| `assembleDebug`   | `app-debug.apk`   | Testing on devices |
| `assembleRelease` | `app-release.apk` | Direct install/share |
| `bundleRelease`   | `app-release.aab` | Google Play Store |

---

### App Config

- **App ID:** `com.ksrguruji.app`
- **App Name:** KSR Guru Ji
- **Server URL:** `https://ksrguruji.com`
- **Config File:** `capacitor.config.json`

---

## PWA Install (No App Store — Instant!)

### Android (Chrome):
Customers open `ksrguruji.com` → banner appears → tap **Install**

### iPhone (Safari):
Open site → Share button (📤) → **Add to Home Screen**

### Download Page for customers:
`https://ksrguruji.com/download-app.php`
