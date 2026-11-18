@echo off
chcp 65001 >nul
setlocal

if not defined XAMPP_HOME set "XAMPP_HOME=C:\xampp"
set "PHP_EXE=%XAMPP_HOME%\php\php.exe"
if not exist "%PHP_EXE%" (
  where php >nul 2>nul
  if errorlevel 1 (
    echo [!] PHP не найден. Задайте XAMPP_HOME или добавьте PHP в PATH.
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
