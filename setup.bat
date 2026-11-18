@echo off
chcp 65001 >nul
setlocal EnableExtensions
title Reestr - bystraya ustanovka
if not defined XAMPP_HOME set "XAMPP_HOME=C:\xampp"
set "HTDOCS=%XAMPP_HOME%\htdocs"
set "MYSQL_EXE=%XAMPP_HOME%\mysql\bin\mysql.exe"
set "MYSQLADMIN_EXE=%XAMPP_HOME%\mysql\bin\mysqladmin.exe"
for %%I in ("%~dp0.") do set "PROJECT_NAME=%%~nxI"
set "MYSQL_HOST=127.0.0.1"
set "MYSQL_PORT=3306"

echo ============================================
echo   REESTR - bystraya ustanovka (XAMPP)
echo ============================================
echo.

if not exist "%HTDOCS%" (
  echo [!] Papka "%HTDOCS%" ne naydena. Ustanovite XAMPP ili zadayte XAMPP_HOME.
  pause & exit /b 1
)

if not exist "%HTDOCS%\%PROJECT_NAME%" (
  mklink /J "%HTDOCS%\%PROJECT_NAME%" "%~dp0"
  if errorlevel 1 echo [!] Ne udalos sozdat svyaz. Skopiruyte papku proekta v "%HTDOCS%".
) else (
  echo [i] "%HTDOCS%\%PROJECT_NAME%" uzhe suschestvuet - propuskayu.
)

echo.
set /p IMPORT="Importirovat bazu reestr.sql? (Y/N): "
if /i "%IMPORT%"=="Y" (
  if exist "%MYSQL_EXE%" (
    if exist "%MYSQLADMIN_EXE%" (
      "%MYSQLADMIN_EXE%" --protocol=TCP --host=%MYSQL_HOST% --port=%MYSQL_PORT% --user=root ping >nul 2>&1
      if errorlevel 1 (
        echo [!] MySQL ne otvechaet po TCP na %MYSQL_HOST%:%MYSQL_PORT%.
        echo     Zapustite MySQL v XAMPP Control Panel i povtorite import.
        echo     Esli port drugoy, izmenite MYSQL_PORT v nachale setup.bat.
        echo     Proverte "%XAMPP_HOME%\mysql\bin\my.ini" i dostupnost porta v firewall.
        goto :import_done
      )
    )
    "%MYSQL_EXE%" --protocol=TCP --host=%MYSQL_HOST% --port=%MYSQL_PORT% --user=root --default-character-set=utf8mb4 < "%~dp0sql\reestr.sql"
    if errorlevel 1 (
      echo [!] Import ne udalsya. Proverte parol root, port MySQL i vyvod oshibki vyshe.
    ) else (
      echo [+] Baza importirovana
    )
  ) else (
    echo [!] mysql.exe ne nayden v "%XAMPP_HOME%" - importiruyte sql\reestr.sql cherez PhpMyAdmin.
  )
)
:import_done

echo.
if exist "%HTDOCS%\%PROJECT_NAME%\index.php" (
  echo Gotovo! Zapustite Apache i otkroyte: http://localhost/%PROJECT_NAME%/
) else (
  echo [!] Proekt ne razmeschen v web-kataloge. Skopiruyte ego v "%HTDOCS%".
)
pause
