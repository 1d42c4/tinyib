@echo off
setlocal EnableExtensions DisableDelayedExpansion
title TinyIB local PHP server

rem Find the board beside this launcher, or in a TinyIB subfolder.
set "TINYIB_ROOT=%~dp0"
if exist "%TINYIB_ROOT%imgboard.php" goto board_found
set "TINYIB_ROOT=%~dp0TinyIB\"
if exist "%TINYIB_ROOT%imgboard.php" goto board_found

echo TinyIB was not found next to this launcher or in its TinyIB subfolder.
echo Put this BAT beside imgboard.php, or beside the TinyIB folder.
pause
exit /b 1

:board_found
pushd "%TINYIB_ROOT%"
if errorlevel 1 (
    echo Could not open the TinyIB directory.
    pause
    exit /b 1
)

set "PHP_EXE=C:\php\php.exe"
if not exist "%PHP_EXE%" (
    echo PHP was not found at C:\php\php.exe.
    popd
    pause
    exit /b 1
)
if not exist "local-router.php" (
    echo local-router.php is missing from the TinyIB directory.
    popd
    pause
    exit /b 1
)

"%PHP_EXE%" imgboard.php
if errorlevel 1 (
    echo TinyIB could not rebuild its pages. See the error above.
    popd
    pause
    exit /b 1
)

echo.
echo TinyIB: http://127.0.0.1:8080/
echo Keep this window open while using the board.
echo Close this window or press Ctrl+C to stop the server.
echo.

rem Set TINYIB_NO_BROWSER=1 to run without automatically opening a browser.
if "%TINYIB_NO_BROWSER%"=="1" goto run_server
start "" powershell.exe -NoProfile -WindowStyle Hidden -Command "$url='http://127.0.0.1:8080/'; for ($attempt=0; $attempt -lt 30; $attempt++) { try { $response=Invoke-WebRequest -UseBasicParsing -Uri $url -TimeoutSec 1; if ($response.Headers['X-TinyIB-Local'] -eq '1') { Start-Process $url; break } } catch {} Start-Sleep -Milliseconds 300 }"

:run_server
"%PHP_EXE%" -d upload_max_filesize=2M -d post_max_size=3M -S 127.0.0.1:8080 -t . local-router.php
set "TINYIB_EXIT=%ERRORLEVEL%"
popd
if not "%TINYIB_EXIT%"=="0" (
    echo.
    echo The server stopped or could not start. Check the error above.
    echo If port 8080 is already in use, close the other server and try again.
    pause
)
exit /b %TINYIB_EXIT%
