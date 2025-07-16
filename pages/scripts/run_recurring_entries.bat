@echo off
"C:\php\php.exe" "C:\path\to\your\app\scripts\process_recurring_entries.php" >> "C:\path\to\your\app\logs\recurring_entries_cron.log" 2>&1
exit /b %errorlevel%