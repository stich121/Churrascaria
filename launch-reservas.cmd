@echo off
set "APPDIR=%LOCALAPPDATA%\ChurrascariaPampulhaReservas"
if not exist "%APPDIR%" mkdir "%APPDIR%"

copy /Y "%~dp0index.html" "%APPDIR%\index.html" >nul
copy /Y "%~dp0styles.css" "%APPDIR%\styles.css" >nul
copy /Y "%~dp0app.js" "%APPDIR%\app.js" >nul
copy /Y "%~dp0supabase.schema.sql" "%APPDIR%\supabase.schema.sql" >nul
copy /Y "%~dp0README.md" "%APPDIR%\README.md" >nul

start "" "%APPDIR%\index.html"
