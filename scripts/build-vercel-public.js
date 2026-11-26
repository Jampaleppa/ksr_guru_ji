/**
 * scripts/build-vercel-public.js
 * Populates public/ with allowlisted static assets only for Vercel CDN.
 * PHP scripts, configs, databases, tests, and private uploads stay excluded
 * so api/index.php executes cleanly via vercel-php without raw PHP exposure.
 */
'use strict';

const fs = require('fs');
const path = require('path');

const rootDir = path.resolve(__dirname, '..');
const outDir = path.join(rootDir, 'public');

const EXCLUDED_TOP_DIRS = new Set([
  '.git',
  '.github',
  '.netlify',
  '.vercel',
  '.vscode',
  '.andromity',
  'android',
  'api',
  'database',
  'downloads',
  'includes',
  'invoices',
  'ios',
  'logs',
  'node_modules',
  'payment-core',
  'public',
  'scratch',
  'scripts',
  'storage',
  'tests',
  'www',
]);

const EXCLUDED_REL_PREFIXES = [
  'admin/database',
  'uploads/temp',
  'uploads/receipts',
  'WhatsApp/vendor',
  'WhatsApp/storage',
  'WhatsApp/telehealth/medicine/uploads/prescriptions',
];

const EXCLUDED_FILENAMES = new Set([
  'sw.js',
  '_worker.js',
]);

const ALLOWED_EXTENSIONS = new Set([
  '.css',
  '.js',
  '.png',
  '.jpg',
  '.jpeg',
  '.gif',
  '.svg',
  '.ico',
  '.webp',
  '.woff',
  '.woff2',
  '.ttf',
  '.eot',
  '.mp3',
  '.wav',
]);

function isExcludedRelPath(relUnix) {
  for (const prefix of EXCLUDED_REL_PREFIXES) {
    if (relUnix === prefix || relUnix.startsWith(prefix + '/')) {
      return true;
    }
  }
  return false;
}

function copyStaticAssets(srcDir, relBase) {
  const entries = fs.readdirSync(srcDir, { withFileTypes: true });
  for (const entry of entries) {
    const relUnix = relBase ? `${relBase}/${entry.name}` : entry.name;

    if (!relBase && entry.isDirectory() && EXCLUDED_TOP_DIRS.has(entry.name)) {
      continue;
    }
    if (isExcludedRelPath(relUnix)) {
      continue;
    }

    const srcPath = path.join(srcDir, entry.name);
    const destPath = path.join(outDir, relUnix);

    if (entry.isDirectory()) {
      copyStaticAssets(srcPath, relUnix);
      continue;
    }
    if (!entry.isFile()) {
      continue;
    }
    if (!relBase && EXCLUDED_FILENAMES.has(entry.name)) {
      continue;
    }
    const ext = path.extname(entry.name).toLowerCase();
    if (!ALLOWED_EXTENSIONS.has(ext)) {
      continue;
    }

    fs.mkdirSync(path.dirname(destPath), { recursive: true });
    fs.copyFileSync(srcPath, destPath);
    copiedCount++;
  }
}

let copiedCount = 0;
fs.rmSync(outDir, { recursive: true, force: true });
fs.mkdirSync(outDir, { recursive: true });
copyStaticAssets(rootDir, '');
console.log(`Built Vercel public/ directory with ${copiedCount} static assets.`);
