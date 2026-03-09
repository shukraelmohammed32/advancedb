@echo off
setlocal

set "SOURCE=C:\xampp\htdocs\advance\student-record-system"
set "TARGET=C:\xampp\htdocs\east_campus"

if not exist "%TARGET%" (
    mkdir "%TARGET%"
    echo Created east_campus directory.
) else (
    echo east_campus directory already exists.
)

echo Copying application files...
xcopy "%SOURCE%\*" "%TARGET%\" /E /I /Y /EXCLUDE:%SOURCE%\exclude.txt >nul
copy "%SOURCE%\.env.east" "%TARGET%\.env" /Y >nul

echo Preparing East branch database...
"C:\xampp\php\php.exe" "%SOURCE%\setup_east_campus.php"

echo.
echo East Campus setup complete.
echo Main Campus: http://localhost/advance/student-record-system
echo East Campus: http://localhost/east_campus
echo East Login: east_admin / eastadmin123
pause
