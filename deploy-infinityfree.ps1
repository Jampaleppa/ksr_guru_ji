# ============================================================
# deploy-infinityfree.ps1
# ------------------------------------------------------------
# Prepares the KSR GURU JI project for deployment to InfinityFree
# (ksrguruji.ifree.page, user if0_43170510, web root /htdocs/).
#
# It does NOT upload. It stages a clean, production-only copy of
# the project and splits it into size-limited batches so you can
# drag-and-drop each batch into FileZilla without hitting the
# HTTP 413 "Request Entity Too Large" limit that VS Code's
# ftp-simple extension trips over.
#
# Usage (PowerShell):
#   cd d:\ksr_guru_ji
#   .\deploy-infinityfree.ps1
#
# Output:
#   deploy\stage\            <- clean production tree
#   deploy\batches\batch-01\ <- numbered upload batches
#   deploy\batches\batch-02\
#   ...
# ============================================================

[CmdletBinding()]
param(
    [int]$MaxBatchMB = 20,                 # max size of each upload batch
    [string]$Source   = ''                 # project root (auto-detected if empty)
)

$ErrorActionPreference = 'Stop'

# Resolve the project root robustly:
#  1. explicit -Source
#  2. $PSScriptRoot (script folder)
#  3. current working directory
if ([string]::IsNullOrWhiteSpace($Source)) {
    if (-not [string]::IsNullOrWhiteSpace($PSScriptRoot)) {
        $Source = $PSScriptRoot
    } else {
        $Source = (Get-Location).Path
    }
}
$Source = (Resolve-Path $Source).Path

$StageRoot   = Join-Path $Source 'deploy\stage'
$BatchRoot   = Join-Path $Source 'deploy\batches'

# --- Directories that must NEVER be uploaded ---
$ExcludeDirs = @(
    '.git',
    '.vscode',
    '.idea',
    'node_modules',
    'deploy',                # staging output itself
    'logs',
    'backups',
    'backup',
    'tmp',
    'temp',
    '.netlify',
    '.vercel',
    '.andromity'
)

# --- File patterns that must NEVER be uploaded ---
$ExcludeFileGlobs = @(
    '*.log',
    '*.sql',
    '*.sqlite',
    '*.sqlite3',
    '.env',
    '.env.*',
    'access.log',
    'package-lock.json',
    'package.json',
    '*.ps1',
    '*.md',
    'netlify.toml',
    'model_capabilities.yaml',
    '*.bak',
    '*.tmp',
    '*.swp',
    'Project_profiles.pdf'   # 16.6 MB doc – exceeds InfinityFree 10 MB limit
)

# --- InfinityFree hard per-file limits (files above these are AUTO-DELETED) ---
#   HTML / PHP / JS : 1 MB
#   .htaccess       : 10 kB
#   everything else : 10 MB
$CodeExtensions = @('.php', '.js', '.html', '.htm', '.css')
$MaxCodeFileMB  = 1
$MaxOtherFileMB = 10
$MaxHtaccessKB  = 10

function Test-ExcludedPath {
    param([string]$RelativePath)
    $parts = $RelativePath -split '[\\/]'
    foreach ($part in $parts) {
        if ($ExcludeDirs -contains $part) { return $true }
    }
    return $false
}

function Test-ExcludedFile {
    param([System.IO.FileInfo]$File)
    foreach ($glob in $ExcludeFileGlobs) {
        if ($File.Name -like $glob) { return $true }
    }
    return $false
}

# ------------------------------------------------------------
# 1. Clean staging area
# ------------------------------------------------------------
Write-Host "Cleaning staging area..." -ForegroundColor Cyan
if (Test-Path $StageRoot) { Remove-Item $StageRoot -Recurse -Force }
if (Test-Path $BatchRoot) { Remove-Item $BatchRoot -Recurse -Force }
New-Item -ItemType Directory -Path $StageRoot -Force | Out-Null

# ------------------------------------------------------------
# 2. Copy production files into the staging tree
# ------------------------------------------------------------
Write-Host "Copying production files to staging..." -ForegroundColor Cyan
$copied = 0
$skipped = 0

Get-ChildItem -Path $Source -Recurse -Force -File | ForEach-Object {
    $rel = $_.FullName.Substring($Source.Length).TrimStart('\', '/')

    if ((Test-ExcludedPath -RelativePath $rel) -or (Test-ExcludedFile -File $_)) {
        $skipped++
        return
    }

    $dest = Join-Path $StageRoot $rel
    $destDir = Split-Path $dest -Parent
    if (-not (Test-Path -LiteralPath $destDir)) { New-Item -ItemType Directory -Path $destDir -Force | Out-Null }
    Copy-Item -LiteralPath $_.FullName -Destination $dest -Force
    $copied++
}

Write-Host ("  Copied : {0} files" -f $copied) -ForegroundColor Green
Write-Host ("  Skipped: {0} files (dev/artifacts)" -f $skipped) -ForegroundColor Yellow

# ------------------------------------------------------------
# 3. Ensure index.php is at the staging root
# ------------------------------------------------------------
if (-not (Test-Path -LiteralPath (Join-Path $StageRoot 'index.php'))) {
    throw "index.php missing from staging root! Check exclusions."
}
Write-Host "  index.php present at staging root" -ForegroundColor Green

# ------------------------------------------------------------
# 3b. Enforce InfinityFree per-file limits
# ------------------------------------------------------------
Write-Host "Checking InfinityFree per-file size limits..." -ForegroundColor Cyan
$violations = @()
Get-ChildItem -Path $StageRoot -Recurse -Force -File | ForEach-Object {
    $ext = $_.Extension.ToLower()
    if ($_.Name -eq '.htaccess') {
        if ($_.Length -gt ($MaxHtaccessKB * 1KB)) {
            $violations += [PSCustomObject]@{ File = $_.FullName; Rule = ".htaccess > ${MaxHtaccessKB} kB" }
        }
    } elseif ($CodeExtensions -contains $ext) {
        if ($_.Length -gt ($MaxCodeFileMB * 1MB)) {
            $violations += [PSCustomObject]@{ File = $_.FullName; Rule = "code file > ${MaxCodeFileMB} MB" }
        }
    } else {
        if ($_.Length -gt ($MaxOtherFileMB * 1MB)) {
            $violations += [PSCustomObject]@{ File = $_.FullName; Rule = "other file > ${MaxOtherFileMB} MB" }
        }
    }
}

if ($violations.Count -gt 0) {
    Write-Host "  WARNING: the following files EXCEED InfinityFree limits and will be auto-deleted on upload:" -ForegroundColor Red
    $violations | ForEach-Object { Write-Host ("    - {0}  [{1}]" -f $_.File, $_.Rule) -ForegroundColor Red }
    Write-Host "  Remove or compress these before uploading." -ForegroundColor Red
} else {
    Write-Host "  All staged files are within InfinityFree limits." -ForegroundColor Green
}

# ------------------------------------------------------------
# 4. Split staging into size-limited batches
# ------------------------------------------------------------
Write-Host "Splitting into batches (max ${MaxBatchMB} MB each)..." -ForegroundColor Cyan
$MaxBatchBytes = $MaxBatchMB * 1MB
New-Item -ItemType Directory -Path $BatchRoot -Force | Out-Null

$batchIndex = 1
$batchBytes = 0
$batchDir   = Join-Path $BatchRoot ('batch-{0:D2}' -f $batchIndex)
New-Item -ItemType Directory -Path $batchDir -Force | Out-Null

$batches = @()
$batches += [PSCustomObject]@{ Dir = $batchDir; Bytes = 0; Files = @() }

Get-ChildItem -Path $StageRoot -Recurse -Force -File | ForEach-Object {
    $rel = $_.FullName.Substring($StageRoot.Length).TrimStart('\', '/')

    if (($batchBytes + $_.Length) -gt $MaxBatchBytes -and $batchBytes -gt 0) {
        $batchIndex++
        $batchDir = Join-Path $BatchRoot ('batch-{0:D2}' -f $batchIndex)
        New-Item -ItemType Directory -Path $batchDir -Force | Out-Null
        $batchBytes = 0
        $batches += [PSCustomObject]@{ Dir = $batchDir; Bytes = 0; Files = @() }
    }

    $dest = Join-Path $batchDir $rel
    $destDir = Split-Path $dest -Parent
    if (-not (Test-Path -LiteralPath $destDir)) { New-Item -ItemType Directory -Path $destDir -Force | Out-Null }
    Copy-Item -LiteralPath $_.FullName -Destination $dest -Force

    $batchBytes += $_.Length
    $batches[-1].Bytes = $batchBytes
    $batches[-1].Files += $rel
}

# ------------------------------------------------------------
# 5. Report
# ------------------------------------------------------------
Write-Host ""
Write-Host "================ BATCH MANIFEST ================" -ForegroundColor Magenta
foreach ($b in $batches) {
    $mb = [math]::Round($b.Bytes / 1MB, 2)
    Write-Host ("{0}  ({1} MB, {2} files)" -f (Split-Path $b.Dir -Leaf), $mb, $b.Files.Count)
}
Write-Host "================================================" -ForegroundColor Magenta
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "  1. Open FileZilla and connect to your InfinityFree FTP account."
Write-Host "  2. Navigate to /htdocs/ on the remote side."
Write-Host "  3. Drag each 'deploy\batches\batch-XX' CONTENTS into /htdocs/,"
Write-Host "     merging folders and overwriting when prompted."
Write-Host "  4. Upload SQL files separately via phpMyAdmin (see DEPLOY_INFINITYFREE.md)."
Write-Host ""
