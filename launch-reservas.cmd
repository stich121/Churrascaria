@echo off
set "APPDIR=%LOCALAPPDATA%\ChurrascariaPampulhaReservas"
if not exist "%APPDIR%" mkdir "%APPDIR%"

copy /Y "%~dp0index.html" "%APPDIR%\index.html" >nul
copy /Y "%~dp0styles.css" "%APPDIR%\styles.css" >nul
copy /Y "%~dp0app.js" "%APPDIR%\app.js" >nul
copy /Y "%~dp0supabase.schema.sql" "%APPDIR%\supabase.schema.sql" >nul
copy /Y "%~dp0README.md" "%APPDIR%\README.md" >nul

set "INDEX_FILE=%APPDIR%\index.html"
set "APP_URL=file:///%INDEX_FILE:\=/%"
set "EDGE_EXE=%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe"
if not exist "%EDGE_EXE%" set "EDGE_EXE=%ProgramFiles%\Microsoft\Edge\Application\msedge.exe"

if exist "%EDGE_EXE%" (
  start "Churrascaria Pampulha Reservas" "%EDGE_EXE%" --app="%APP_URL%" --user-data-dir="%APPDIR%\EdgeProfile"
) else (
  start "" "%INDEX_FILE%"
)
