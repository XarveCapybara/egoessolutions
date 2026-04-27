$ErrorActionPreference = "Stop"

Write-Host "== EgoesSolution pre-publish check =="

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

Write-Host ""
Write-Host "[1/3] PHP syntax lint"
$phpFiles = Get-ChildItem -Path $root -Recurse -Filter *.php
$lintFailed = $false
foreach ($file in $phpFiles) {
    $result = & php -l $file.FullName 2>&1
    if ($LASTEXITCODE -ne 0) {
        $lintFailed = $true
        Write-Host "FAIL: $($file.FullName)" -ForegroundColor Red
        Write-Host $result
    }
}
if ($lintFailed) {
    Write-Host ""
    Write-Host "Pre-publish check failed: PHP lint errors found." -ForegroundColor Red
    exit 1
}
Write-Host "PASS: PHP lint" -ForegroundColor Green

Write-Host ""
Write-Host "[2/3] Debug leftovers scan (var_dump/print_r/dd)"
$debugPattern = '\b(var_dump|print_r|dd)\s*\('
$debugHits = Get-ChildItem -Path $root -Recurse -Filter *.php |
    Select-String -Pattern $debugPattern

if ($debugHits) {
    Write-Host "WARN: potential debug leftovers found:" -ForegroundColor Yellow
    $debugHits | ForEach-Object {
        Write-Host ("  {0}:{1}: {2}" -f $_.Path, $_.LineNumber, $_.Line.Trim())
    }
} else {
    Write-Host "PASS: no debug leftovers found" -ForegroundColor Green
}

Write-Host ""
Write-Host "[3/3] Production config reminder"
$dbConfig = Join-Path $root "config\database.php"
if (Test-Path $dbConfig) {
    $content = Get-Content -Path $dbConfig -Raw
    if ($content -match "fieryblaze1") {
        Write-Host "WARN: local fallback DB password string still exists in config/database.php" -ForegroundColor Yellow
        Write-Host "      Ensure Hostinger environment variables are set before publish."
    } else {
        Write-Host "PASS: no local fallback password marker detected" -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "Pre-publish checks completed." -ForegroundColor Green
exit 0
