<?php

if (!function_exists('accountSecurityMinPasswordLength')) {
    function accountSecurityMinPasswordLength() {
        return 6;
    }
}

if (!function_exists('accountSecurityNormalizePhone')) {
    function accountSecurityNormalizePhone($value) {
        return preg_replace('/\D+/', '', (string)$value);
    }
}

if (!function_exists('accountSecurityValidPastDate')) {
    function accountSecurityValidPastDate($value, $allow_empty = true) {
        $value = trim((string)$value);

        if ($value === '') {
            return $allow_empty;
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return false;
        }

        $today = new DateTime('today');
        return $date <= $today;
    }
}

if (!function_exists('accountSecurityVerifyCurrentPassword')) {
    function accountSecurityVerifyCurrentPassword($conn, $user_id, $plain_password) {
        $user_id = (int)$user_id;
        if (!($conn instanceof mysqli) || $user_id <= 0 || trim((string)$plain_password) === '') {
            return false;
        }

        $stmt = $conn->prepare('SELECT password FROM users WHERE user_id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = dbStatementFetchOneAssoc($stmt);
        $stmt->close();

        if (!$row || !isset($row['password'])) {
            return false;
        }

        return password_verify($plain_password, (string)$row['password']);
    }
}

if (!function_exists('accountSecurityUpdateUserPassword')) {
    function accountSecurityUpdateUserPassword($conn, $user_id, $plain_password) {
        $user_id = (int)$user_id;
        if (!($conn instanceof mysqli) || $user_id <= 0 || trim((string)$plain_password) === '') {
            return false;
        }

        $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password = ?, is_active = 1 WHERE user_id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('si', $password_hash, $user_id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('accountSecurityFetchStudentRecoveryAccount')) {
    function accountSecurityFetchStudentRecoveryAccount($conn, $student_id, $login_identity) {
        $student_id = (int)$student_id;
        $login_identity = trim((string)$login_identity);

        if (!($conn instanceof mysqli) || $student_id <= 0 || $login_identity === '') {
            return null;
        }

        $stmt = $conn->prepare(
            "SELECT
                u.user_id,
                u.username,
                u.email,
                u.is_active,
                s.student_id,
                s.name,
                sp.date_of_birth,
                sp.guardian_name,
                sp.guardian_phone
             FROM users u
             JOIN students s ON s.student_id = u.student_id
             LEFT JOIN student_profiles sp ON sp.student_id = s.student_id
             WHERE u.role = 'student'
               AND u.is_active = 1
               AND u.student_id = ?
               AND (u.email = ? OR u.username = ?)
             LIMIT 1"
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('iss', $student_id, $login_identity, $login_identity);
        $stmt->execute();
        $row = dbStatementFetchOneAssoc($stmt);
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('accountSecurityStudentRecoveryReady')) {
    function accountSecurityStudentRecoveryReady($account) {
        if (!is_array($account)) {
            return false;
        }

        $date_of_birth = trim((string)($account['date_of_birth'] ?? ''));
        $guardian_phone = accountSecurityNormalizePhone($account['guardian_phone'] ?? '');

        return $date_of_birth !== '' && $guardian_phone !== '';
    }
}

if (!function_exists('accountSecurityStudentRecoveryMatches')) {
    function accountSecurityStudentRecoveryMatches($account, $date_of_birth, $guardian_phone) {
        if (!accountSecurityStudentRecoveryReady($account)) {
            return false;
        }

        return trim((string)($account['date_of_birth'] ?? '')) === trim((string)$date_of_birth)
            && accountSecurityNormalizePhone($account['guardian_phone'] ?? '') === accountSecurityNormalizePhone($guardian_phone);
    }
}
?>
