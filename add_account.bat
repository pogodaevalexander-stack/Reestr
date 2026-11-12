@echo off
chcp 65001 >nul
setlocal

set "PHP_EXE=C:\xampp\php\php.exe"
if not exist "%PHP_EXE%" (
  where php >nul 2>nul
  if errorlevel 1 (
    echo [!] PHP не найден. Установите XAMPP в C:\xampp или добавьте PHP в PATH.
    pause
    exit /b 1
  )
  set "PHP_EXE=php"
)

"%PHP_EXE%" "%~dp0tools\add_account.php"
if errorlevel 1 (
  echo.
  echo [!] Аккаунт не создан.
) else (
  echo.
  echo Готово.
)
pause
