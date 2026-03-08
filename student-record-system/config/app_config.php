<?php

require_once __DIR__ . '/env.php';

/**
 * Application Configuration Loader
 * Loads all settings from environment variables securely
 */
class AppConfig {
    
    // Database settings
    public static function getDatabaseHost() {
        return env('DB_HOST', 'localhost');
    }
    
    public static function getDatabaseUsername() {
        return env('DB_USERNAME', 'root');
    }
    
    public static function getDatabasePassword() {
        return env('DB_PASSWORD', '');
    }
    
    public static function getDatabaseName() {
        return env('DB_DATABASE', 'student_record_system');
    }
    
    // Admin settings
    public static function getAdminUsername() {
        return env('ADMIN_USERNAME', 'admin');
    }
    
    public static function getAdminPassword() {
        return env('ADMIN_PASSWORD', 'admin123');
    }
    
    public static function getAdminEmail() {
        return env('ADMIN_EMAIL', 'admin@school.edu');
    }
    
    // Security settings
    public static function getSessionLifetime() {
        return env('SESSION_LIFETIME', 120);
    }
    
    public static function isCsrfEnabled() {
        return env('CSRF_TOKEN', true);
    }
    
    public static function isForceHttps() {
        return env('FORCE_HTTPS', false);
    }
    
    public static function getAllowedOrigins() {
        $origins = env('ALLOWED_ORIGINS', 'http://localhost,http://127.0.0.1');
        return explode(',', $origins);
    }
    
    // Academic settings
    public static function getPassingScore() {
        return env('PASSING_SCORE', 50);
    }
    
    public static function getMaxStudentsPerClass() {
        return env('MAX_STUDENTS_PER_CLASS', 40);
    }
    
    public static function getDefaultAcademicYear() {
        return env('DEFAULT_ACADEMIC_YEAR', '2024-2025');
    }
    
    // Distributed settings
    public static function isDistributedEnabled() {
        return env('ENABLE_DISTRIBUTED', false);
    }
    
    public static function getDefaultSiteId() {
        return env('DEFAULT_SITE_ID', 1);
    }
    
    public static function isSyncEnabled() {
        return env('SYNC_ENABLED', false);
    }
    
    // Maintenance
    public static function isMaintenanceMode() {
        return env('MAINTENANCE_MODE', false);
    }
    
    // File upload
    public static function getMaxFileSize() {
        return env('MAX_FILE_SIZE', 5242880);
    }
    
    public static function getUploadPath() {
        return env('UPLOAD_PATH', 'uploads/');
    }
}
