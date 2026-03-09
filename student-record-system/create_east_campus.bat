@echo off
echo Creating East Campus Branch...

REM Create east campus directory
if not exist "c:\xampp\htdocs\east_campus" (
    mkdir "c:\xampp\htdocs\east_campus"
    echo ✅ Created east_campus directory
) else (
    echo ⚠️ east_campus directory already exists
)

REM Copy all files except node_modules and some temp files
echo 📁 Copying files to east campus...
xcopy "c:\xampp\htdocs\advance\student-record-system\*" "c:\xampp\htdocs\east_campus\" /E /I /Y /EXCLUDE:exclude.txt

REM Copy east campus environment file
copy "c:\xampp\htdocs\advance\student-record-system\.env.east" "c:\xampp\htdocs\east_campus\.env" /Y

echo ✅ Files copied successfully

REM Run the east campus setup
echo 🚀 Setting up east campus database...
php "c:\xampp\htdocs\east_campus\setup_east_campus.php"

echo.
echo 🎉 East Campus Setup Complete!
echo.
echo 📍 Access Points:
echo   Main Campus: http://localhost/advance/student-record-system
echo   East Campus: http://localhost/east_campus
echo.
echo 👤 Login Credentials:
echo   Main Campus: admin / admin123
echo   East Campus: east_admin / eastadmin123
echo.
pause
