[CmdletBinding()]
param(
    [string]$ServerAddress = '94.73.180.180',
    [string]$DeployUser = 'ulusdeploy',
    [string]$IdentityFile
)

$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
if ([string]::IsNullOrWhiteSpace($IdentityFile)) {
    $IdentityFile = Join-Path $repoRoot '.codex-build\portal-deploy-nov2026'
}
$remoteScriptSource = Join-Path $PSScriptRoot 'deploy-live-remote.sh'
$buildDirectory = Join-Path $repoRoot 'public\build'
$codexBuildDirectory = Join-Path $repoRoot '.codex-build'
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$archivePath = Join-Path $codexBuildDirectory "portal-build-$timestamp.tar.gz"
$localRemoteScript = Join-Path $codexBuildDirectory "deploy-live-$timestamp.sh"
$remoteArchive = "/home/$DeployUser/portal-build-$timestamp.tar.gz"
$remoteScript = "/home/$DeployUser/deploy-live-$timestamp.sh"
$sshTarget = "$DeployUser@$ServerAddress"

function Invoke-CheckedNative {
    param(
        [Parameter(Mandatory = $true)][string]$FilePath,
        [Parameter(Mandatory = $true)][string[]]$Arguments,
        [Parameter(Mandatory = $true)][string]$FailureMessage
    )

    & $FilePath @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "$FailureMessage (çıkış kodu: $LASTEXITCODE)"
    }
}

Push-Location $repoRoot
try {
    if (-not (Test-Path -LiteralPath $IdentityFile -PathType Leaf)) {
        throw "SSH anahtarı bulunamadı: $IdentityFile"
    }
    if (-not (Test-Path -LiteralPath $remoteScriptSource -PathType Leaf)) {
        throw "Sunucu dağıtım betiği bulunamadı: $remoteScriptSource"
    }
    if (-not (Test-Path -LiteralPath (Join-Path $repoRoot 'node_modules') -PathType Container)) {
        throw 'node_modules bulunamadı. Önce proje bağımlılıklarının kurulması gerekiyor.'
    }

    $branch = (& git branch --show-current).Trim()
    if ($LASTEXITCODE -ne 0 -or $branch -ne 'main') {
        throw "Dağıtım yalnızca main dalından yapılabilir. Mevcut dal: $branch"
    }

    $trackedChanges = @(& git status --porcelain --untracked-files=no)
    if ($LASTEXITCODE -ne 0) {
        throw 'Git çalışma durumu okunamadı.'
    }
    if ($trackedChanges.Count -gt 0) {
        throw 'Kaydedilmemiş kod değişikliği var. Önce değişiklikleri commit edip GitHub main dalına gönder.'
    }

    Write-Host 'GitHub sürümü kontrol ediliyor...'
    Invoke-CheckedNative -FilePath 'git' -Arguments @('fetch', 'origin', 'main') -FailureMessage 'GitHub bilgisi alınamadı.'
    $localCommit = (& git rev-parse HEAD).Trim()
    $remoteCommit = (& git rev-parse origin/main).Trim()
    if ($localCommit -ne $remoteCommit) {
        throw 'Yerel main, GitHub main ile aynı değil. Önce commit ve push işlemini tamamla.'
    }

    Write-Host 'Ön yüz derleniyor...'
    Invoke-CheckedNative -FilePath 'npm' -Arguments @('run', 'build') -FailureMessage 'Ön yüz derlemesi başarısız.'

    if (-not (Test-Path -LiteralPath (Join-Path $buildDirectory 'manifest.json') -PathType Leaf)) {
        throw 'Derleme manifest.json dosyasını oluşturmadı.'
    }
    if (-not (Test-Path -LiteralPath $codexBuildDirectory -PathType Container)) {
        New-Item -ItemType Directory -Path $codexBuildDirectory | Out-Null
    }

    Write-Host 'Derleme paketi hazırlanıyor...'
    Invoke-CheckedNative -FilePath 'tar.exe' -Arguments @('-czf', $archivePath, '-C', 'public', 'build') -FailureMessage 'Derleme paketi oluşturulamadı.'

    $sshOptions = @('-i', $IdentityFile, '-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=15')
    $remoteText = (Get-Content -LiteralPath $remoteScriptSource -Raw) -replace "`r`n", "`n"
    $utf8NoBom = [System.Text.UTF8Encoding]::new($false)
    [System.IO.File]::WriteAllText($localRemoteScript, $remoteText, $utf8NoBom)

    Write-Host 'Sunucuya paketler gönderiliyor...'
    Invoke-CheckedNative -FilePath 'scp' -Arguments (@($sshOptions) + @($archivePath, "${sshTarget}:$remoteArchive")) -FailureMessage 'Derleme paketi sunucuya gönderilemedi.'
    Invoke-CheckedNative -FilePath 'scp' -Arguments (@($sshOptions) + @($localRemoteScript, "${sshTarget}:$remoteScript")) -FailureMessage 'Dağıtım yönergesi sunucuya gönderilemedi.'

    Write-Host 'Yedek alınıyor, canlı sürüm güncelleniyor ve portal kontrol ediliyor...'
    $remoteCommand = "bash $remoteScript $timestamp"
    Invoke-CheckedNative -FilePath 'ssh' -Arguments (@($sshOptions) + @($sshTarget, $remoteCommand)) -FailureMessage 'Sunucudaki dağıtım tamamlanamadı. Yukarıdaki mesajı incele.'

    Write-Host ''
    Write-Host 'Dağıtım tamamlandı.' -ForegroundColor Green
}
finally {
    if (Test-Path -LiteralPath $localRemoteScript -PathType Leaf) {
        Remove-Item -LiteralPath $localRemoteScript -Force
    }
    Pop-Location
}
