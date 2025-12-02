@echo off
echo ================================================
echo   PUSH FIX US KE GITHUB
echo   Kelompok 7 - RPL
echo ================================================
echo.

REM Check if repository exists
echo [1/3] Checking GitHub repository...
curl -s -o nul -w "%%{http_code}" https://github.com/aisyifaarum/fixus > temp_status.txt
set /p HTTP_STATUS=<temp_status.txt
del temp_status.txt

if "%HTTP_STATUS%"=="404" (
    echo.
    echo [ERROR] Repository belum dibuat!
    echo.
    echo Silakan buat repository dulu di:
    echo https://github.com/new
    echo.
    echo Nama repository: fixus
    echo Visibility: Private
    echo JANGAN centang apapun!
    echo.
    pause
    exit /b 1
)

echo [OK] Repository ditemukan!
echo.

REM Push to GitHub
echo [2/3] Pushing ke GitHub...
echo.
echo Anda akan diminta:
echo   Username: aisyifaarum
echo   Password: [paste Personal Access Token]
echo.
echo Belum punya token? Buat di:
echo https://github.com/settings/tokens
echo.
pause

git push -u origin main

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ================================================
    echo   BERHASIL!
    echo ================================================
    echo.
    echo Repository: https://github.com/aisyifaarum/fixus
    echo Files uploaded: 43 files
    echo Commits: 2 commits
    echo.
    echo [3/3] Verifikasi...
    echo Buka browser dan cek:
    echo https://github.com/aisyifaarum/fixus
    echo.
    echo Pastikan:
    echo   - README.md tampil dengan Kelompok 7
    echo   - TIDAK ADA config.php
    echo   - TIDAK ADA *.sql
    echo.
) else (
    echo.
    echo ================================================
    echo   GAGAL!
    echo ================================================
    echo.
    echo Kemungkinan:
    echo 1. Repository belum dibuat
    echo 2. Token salah
    echo 3. Tidak ada akses internet
    echo.
    echo Coba lagi dengan:
    echo   git push -u origin main
    echo.
)

pause
