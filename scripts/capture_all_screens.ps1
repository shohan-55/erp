param(
    [string]$BaseUrl = $(if ($env:APP_URL) { $env:APP_URL } else { "http://127.0.0.1:8000" }),
    [string]$OutputDir = $(if ($env:SCREENSHOT_OUTPUT_DIR) { $env:SCREENSHOT_OUTPUT_DIR } else { (Join-Path $PSScriptRoot "..\public\screenshots") }),
    [string]$ArtifactDir = $(if ($env:ANTIGRAVITY_ARTIFACT_DIR) { $env:ANTIGRAVITY_ARTIFACT_DIR } else { "" }),
    [string]$BrowserPath = $(if ($env:BROWSER_PATH) { $env:BROWSER_PATH } else { "" })
)

# Auto-detect Edge or Chrome browser path if not specified
if (-not $BrowserPath -or -not (Test-Path $BrowserPath)) {
    $candidates = @(
        "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
        "${env:ProgramFiles}\Microsoft\Edge\Application\msedge.exe",
        "${env:ProgramFiles}\Google\Chrome\Application\chrome.exe",
        "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe",
        "${env:LocalAppData}\Microsoft\Edge\Application\msedge.exe",
        "${env:LocalAppData}\Google\Chrome\Application\chrome.exe"
    )
    foreach ($cand in $candidates) {
        if ($cand -and (Test-Path $cand)) {
            $BrowserPath = $cand
            break
        }
    }
}

if (-not $BrowserPath -or -not (Test-Path $BrowserPath)) {
    Write-Error "No compatible Chromium browser found for headless captures. Please set -BrowserPath or BROWSER_PATH environment variable."
    exit 1
}

# Resolve full output directory
$OutputDir = [System.IO.Path]::GetFullPath($OutputDir)
if (-not (Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null
}

$hasArtifactDir = $false
if ($ArtifactDir -and (Test-Path $ArtifactDir)) {
    $ArtifactDir = [System.IO.Path]::GetFullPath($ArtifactDir)
    $hasArtifactDir = $true
}

$captures = @(
    @{ Name = "desktop_firm_login_1440x900.png"; Width = 1440; Height = 900; Path = "/c/rac/login" },
    @{ Name = "desktop_platform_login_1440x900.png"; Width = 1440; Height = 900; Path = "/platform/login" },
    @{ Name = "desktop_forgot_password_1440x900.png"; Width = 1440; Height = 900; Path = "/c/rac/forgot-password" },
    @{ Name = "desktop_reset_password_1440x900.png"; Width = 1440; Height = 900; Path = "/c/rac/reset-password" },
    @{ Name = "desktop_account_activation_1440x900.png"; Width = 1440; Height = 900; Path = "/c/rac/activate" },
    @{ Name = "desktop_unknown_firm_1440x900.png"; Width = 1440; Height = 900; Path = "/c/unknown-firm/login" },
    @{ Name = "tablet_firm_login_768x1024.png"; Width = 768; Height = 1024; Path = "/c/rac/login" },
    @{ Name = "mobile_firm_login_390x844.png"; Width = 390; Height = 844; Path = "/c/rac/login" },
    @{ Name = "mobile_platform_login_390x844.png"; Width = 390; Height = 844; Path = "/platform/login" },
    @{ Name = "mobile_account_activation_390x844.png"; Width = 390; Height = 844; Path = "/c/rac/activate" },
    @{ Name = "small_mobile_firm_login_375x667.png"; Width = 375; Height = 667; Path = "/c/rac/login" }
)

Write-Host "Starting responsive viewport captures using browser: $BrowserPath"
Write-Host "Target Base URL: $BaseUrl"
Write-Host "Target Output Directory: $OutputDir"

foreach ($c in $captures) {
    $fullUrl = "$($BaseUrl.TrimEnd('/'))$($c.Path)"
    $outFile = Join-Path $OutputDir $c.Name
    Write-Host "Capturing $($c.Name) ($($c.Width)x$($c.Height)) from $fullUrl..."
    
    $argsList = @(
        "--headless",
        "--disable-gpu",
        "--screenshot=$outFile",
        "--window-size=$($c.Width),$($c.Height)",
        $fullUrl
    )
    
    $p = Start-Process -FilePath $BrowserPath -ArgumentList $argsList -Wait -PassThru
    if (Test-Path $outFile) {
        $len = (Get-Item $outFile).Length
        Write-Host "  Success: Saved $len bytes"
        if ($hasArtifactDir) {
            $destFile = Join-Path $ArtifactDir $c.Name
            Copy-Item -Path $outFile -Destination $destFile -Force
        }
    } else {
        Write-Warning "  Failed to capture $($c.Name)"
    }
}

Write-Host "All Phase 02 captures complete!"
