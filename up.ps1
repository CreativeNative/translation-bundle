# up.ps1
# Purpose: Start Docker containers and recreate Mutagen sync sessions cleanly
# Encoding: UTF-8 (no BOM)

[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
Write-Host "Starting Docker and Mutagen setup..." -ForegroundColor Cyan

# Step 0: Check for Mutagen installation and environment
Write-Host "Checking Mutagen installation..." -ForegroundColor Yellow
$mutagen = Get-Command mutagen -ErrorAction SilentlyContinue

if (-not $mutagen) {
    Write-Host "Error: Mutagen is not installed or not found in PATH." -ForegroundColor Red
    Write-Host "Please install Mutagen or add it to your PATH before continuing." -ForegroundColor Red
    exit 1
}

Write-Host "Mutagen detected: $($mutagen.Source)" -ForegroundColor Green

# Step 1: Start Docker containers
Write-Host "Starting Docker containers (detached mode, rebuild enabled)." -ForegroundColor Cyan
docker compose up -d --build

if ($LASTEXITCODE -ne 0) {
    Write-Host "Docker containers failed to start. Please check the logs above." -ForegroundColor Red
    exit 1
} else {
    Write-Host "Docker containers started successfully." -ForegroundColor Green
}

# Step 2: Wait until all containers are fully running
Write-Host "`nChecking container readiness..." -ForegroundColor Cyan

# Define the container names you expect to be running
$containers = @("php")

foreach ($container in $containers) {
    $isReady = $false
    Write-Host "Waiting for $container to be ready..." -ForegroundColor Yellow

    for ($i = 1; $i -le 20; $i++) {
        $status = docker inspect -f "{{.State.Running}}" $container 2>$null

        if ($status -eq "true") {
            $isReady = $true
            Write-Host "$container is running." -ForegroundColor Green
            break
        }

        Start-Sleep -Seconds 2
    }

    if (-not $isReady) {
        Write-Host "Container $container did not become ready in time." -ForegroundColor Red
        exit 1
    }
}

# Step 3: Clean up all old Mutagen sessions

## define Prefix
$prefix = "translation-"

Write-Host "`nTerminating and removing all Mutagen sessions starting with '$prefix'..." -ForegroundColor Cyan

# List sessions and filter out only identifier lines
$matchingSessions = mutagen sync list |
        Select-String "^Name:" |
        ForEach-Object { ($_ -split ":")[1].Trim() } |
        Where-Object { $_ -like "$prefix*" }

if ($matchingSessions) {
    foreach ($session in $matchingSessions) {
        Write-Host "Terminating session: $session" -ForegroundColor Yellow
        mutagen sync terminate $session 2>&1 | Write-Host
    }
} else {
    Write-Host "No sessions found with prefix '$prefix'." -ForegroundColor DarkGray
}

# Restart Mutagen daemon
Write-Host "Restarting Mutagen daemon..." -ForegroundColor Cyan
mutagen daemon stop 2>&1 | Write-Host
mutagen daemon start 2>&1 | Write-Host

# Step 4: Define new sessions
$mutagenSessions = @(
    @{
        Name = "translation-vendor"
        Alpha = "docker://php/var/www/tmi/translation-bundle/vendor"
        Beta = "D:\www\tmi\translation-bundle\vendor"
        SyncMode = "one-way-safe"
    },
    @{
        Name = "translation-var"
        Alpha = "docker://php/var/www/tmi/translation-bundle/var"
        Beta = "D:\www\tmi\translation-bundle\var"
        SyncMode = "two-way-safe"
    }
)

# Step 5: Create new Mutagen sessions
foreach ($session in $mutagenSessions) {
    Write-Host "Creating Mutagen session: $($session.Name)" -ForegroundColor Cyan
    mutagen sync create $($session.Alpha) $($session.Beta) `
        --name $($session.Name) `
        --sync-mode=$($session.SyncMode) `
        2>&1 | Write-Host

    if ($LASTEXITCODE -eq 0) {
        Write-Host "Session $($session.Name) with sync mode $($session.SyncMode) created successfully." -ForegroundColor Green
    } else {
        Write-Host "Failed to create session $($session.Name)." -ForegroundColor Red
    }
}

# Step 6: Display current Mutagen status
Write-Host "`nFinal Mutagen status overview:" -ForegroundColor Cyan
mutagen sync list

Write-Host "`nAll setup tasks completed successfully." -ForegroundColor Green

# Step 8: Pause to read output
# Pause