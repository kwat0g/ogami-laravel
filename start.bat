@echo off
:: =============================================================================
:: Ogami ERP — Windows Quick Launcher
:: Double-click this file to start the dev server.
:: =============================================================================
echo.
echo  Ogami ERP - Starting dev server...
echo  (Close this window to stop all servers)
echo.

:: Run the PowerShell script, bypassing execution policy for this session only
powershell.exe -ExecutionPolicy Bypass -NoExit -File "%~dp0start.ps1"
