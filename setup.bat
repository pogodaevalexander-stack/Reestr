@echo off
chcp 65001 >nul
setlocal EnableExtensions
title Reestr - bystraya ustanovka

echo ============================================
echo   REESTR - bystraya ustanovka (XAMPP)
echo ============================================
echo.

net session >nul 2>&1
if errorlevel 1 (
  echo [!] Zapuskite etot fayl ot imeni administratora.
  pause & exit /b 1
)

if not exist "C:\xampp\htdocs" (
  echo [!] C:\xampp\htdocs ne nayden. Ustanovite XAMPP v C:\xampp.
  pause & exit /b 1
)

if not exist "C:\xampp\htdocs\Reestr" (
  mklink /J "C:\xampp\htdocs\Reestr" "%~dp0" && echo [+] Sozdana svyaz: htdocs\Reestr ^-^> %~dp0
) else (
  echo [i] C:\xampp\htdocs\Reestr uzhe suschestvuet - propuskayu.
)

echo.
set /p IMPORT="Importirovat bazu reestr.sql? (Y/N): "
if /i "%IMPORT%"=="Y" (
  if exist "C:\xampp\mysql\bin\mysql.exe" (
    "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 < "%~dp0sql\reestr.sql" && echo [+] Baza importirovana || echo [!] Import ne udalsya
  ) else (
    echo [!] mysql.exe ne nayden - importiruyte sql\reestr.sql cherez PhpMyAdmin.
  )
)

echo.
echo Gotovo! Zapustite Apache i otkroyte: http://localhost/Reestr/
pause
