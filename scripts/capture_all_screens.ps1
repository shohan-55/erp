$edgePath = "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"
$brainDir = "C:\Users\SOHAN HOSSAIN\.gemini\antigravity-ide\brain\2235363e-6061-4b07-bf4c-b897579a5744\screenshots"
$pubDir = "d:\DhakaWeb\erp\public\screenshots"

if (!(Test-Path $brainDir)) { New-Item -ItemType Directory -Path $brainDir -Force | Out-Null }
if (!(Test-Path $pubDir)) { New-Item -ItemType Directory -Path $pubDir -Force | Out-Null }

$captures = @(
    @{ Name = "desktop_firm_login_1440x900.png"; Width = 1440; Height = 900; Url = "http://127.0.0.1:8000/c/rac/login" },
    @{ Name = "desktop_platform_login_1440x900.png"; Width = 1440; Height = 900; Url = "http://127.0.0.1:8000/platform/login" },
    @{ Name = "desktop_forgot_password_1440x900.png"; Width = 1440; Height = 900; Url = "http://127.0.0.1:8000/c/rac/forgot-password" },
    @{ Name = "desktop_reset_password_1440x900.png"; Width = 1440; Height = 900; Url = "http://127.0.0.1:8000/c/rac/reset-password" },
    @{ Name = "desktop_account_activation_1440x900.png"; Width = 1440; Height = 900; Url = "http://127.0.0.1:8000/c/rac/activate" },
    @{ Name = "desktop_unknown_firm_1440x900.png"; Width = 1440; Height = 900; Url = "http://127.0.0.1:8000/c/unknown-firm/login" },
    @{ Name = "tablet_firm_login_768x1024.png"; Width = 768; Height = 1024; Url = "http://127.0.0.1:8000/c/rac/login" },
    @{ Name = "mobile_firm_login_390x844.png"; Width = 390; Height = 844; Url = "http://127.0.0.1:8000/c/rac/login" },
    @{ Name = "mobile_platform_login_390x844.png"; Width = 390; Height = 844; Url = "http://127.0.0.1:8000/platform/login" },
    @{ Name = "mobile_account_activation_390x844.png"; Width = 390; Height = 844; Url = "http://127.0.0.1:8000/c/rac/activate" },
    @{ Name = "small_mobile_firm_login_375x667.png"; Width = 375; Height = 667; Url = "http://127.0.0.1:8000/c/rac/login" }
)

foreach ($c in $captures) {
    $outFile = Join-Path $pubDir $c.Name
    $brainFile = Join-Path $brainDir $c.Name
    Write-Host "Capturing $($c.Name) ($($c.Width)x$($c.Height)) from $($c.Url)..."
    
    $argsList = @(
        "--headless",
        "--disable-gpu",
        "--screenshot=$outFile",
        "--window-size=$($c.Width),$($c.Height)",
        $c.Url
    )
    
    $p = Start-Process -FilePath $edgePath -ArgumentList $argsList -Wait -PassThru
    if (Test-Path $outFile) {
        Copy-Item -Path $outFile -Destination $brainFile -Force
        $len = (Get-Item $outFile).Length
        Write-Host "  Success: Saved $len bytes"
    } else {
        Write-Warning "  Failed to capture $($c.Name)"
    }
}

Write-Host "All Phase 02 captures complete!"
