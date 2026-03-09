<?php

/**
 * Custom Error Handler for Student Record System
 */
class ErrorHandler {
    
    public static function init() {
        // Set custom error handler
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        
        // Hide errors in production
        if (AppConfig::getAppEnv() === 'production' && !AppConfig::isAppDebug()) {
            ini_set('display_errors', 0);
            error_reporting(0);
        } else {
            ini_set('display_errors', 1);
            error_reporting(E_ALL);
        }
    }
    
    public static function handleError($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $message = "Error: [$errno] $errstr in $errfile on line $errline";
        error_log($message);
        
        if (AppConfig::getAppEnv() === 'production' && !AppConfig::isAppDebug()) {
            self::showUserFriendlyError();
        } else {
            echo "<div style='background: #fee; border: 1px solid #c00; padding: 10px; margin: 10px;'>";
            echo "<strong>Error:</strong> $message";
            echo "</div>";
        }
        
        return true;
    }
    
    public static function handleException($exception) {
        $message = "Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
        error_log($message);
        
        if (AppConfig::getAppEnv() === 'production' && !AppConfig::isAppDebug()) {
            self::showUserFriendlyError();
        } else {
            echo "<div style='background: #fee; border: 1px solid #c00; padding: 10px; margin: 10px;'>";
            echo "<strong>Exception:</strong> $message";
            echo "</div>";
        }
    }
    
    public static function showUserFriendlyError() {
        http_response_code(500);
        echo '<!DOCTYPE html>
<html>
<head>
    <title>System Error - Student Record System</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 50px; }
        .error-container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #d32f2f; }
        p { color: #666; }
        .btn { background: #1976d2; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>Oops! Something went wrong</h1>
        <p>We encountered an unexpected error. The issue has been logged and our team will look into it.</p>
        <p>Please try again in a few moments, or contact support if the problem persists.</p>
        <a href="index.php" class="btn">Go Back to Dashboard</a>
    </div>
</body>
</html>';
        exit;
    }
}

// Initialize error handler
ErrorHandler::init();
