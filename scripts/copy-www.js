/**
 * scripts/copy-www.js
 * Cross-platform sync of web assets into www/ for Capacitor mobile builds.
 */
const fs = require('fs');
const path = require('path');

const rootDir = path.resolve(__dirname, '..');
const destDir = path.join(rootDir, 'www');

const EXCLUDES = new Set([
  'node_modules',
  'www',
  'android',
  'ios',
  '.git',
  '.github',
  '.vercel',
  'scratch'
]);

if (!fs.existsSync(destDir)) {
  fs.mkdirSync(destDir, { recursive: true });
}

function copyRecursive(src, dest) {
  const entries = fs.readdirSync(src, { withFileTypes: true });
  for (const entry of entries) {
    if (EXCLUDES.has(entry.name)) continue;
    const srcPath = path.join(src, entry.name);
    const destPath = path.join(dest, entry.name);

    if (entry.isDirectory()) {
      if (!fs.existsSync(destPath)) {
        fs.mkdirSync(destPath, { recursive: true });
      }
      copyRecursive(srcPath, destPath);
    } else {
      fs.copyFileSync(srcPath, destPath);
    }
  }
}

try {
  copyRecursive(rootDir, destDir);
  console.log('Successfully copied assets to www/');
} catch (err) {
  console.error('Error copying assets to www/:', err.message);
}
