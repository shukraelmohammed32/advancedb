<?php
require_once '../config/database.php';
require_once '../auth/auth_helper.php';
require_once '../includes/distributed_coordinator.php';

requireRole('student');

$db = new Database();
$conn = $db->getConnection();
$coordinator = new DistributedCoordinator($db);
$distributed_ready = $coordinator->isDistributedReady();

$student_id = (int)($_SESSION['student_id'] ?? 0);
$user_id = (int)($_SESSION['user_id'] ?? 0);
$profile_table_exists = false;
$profile_photo_column_exists = false;

$table_check = $conn->query("SHOW TABLES LIKE 'student_profiles'");
if ($table_check && $table_check->num_rows > 0) {
    $profile_table_exists = true;

    $column_check = $conn->query("SHOW COLUMNS FROM student_profiles LIKE 'profile_photo'");
    if ($column_check && $column_check->num_rows > 0) {
        $profile_photo_column_exists = true;
    }
}

$success_message = '';
$error_message = '';

function sanitizeProfileText($value, $max_length) {
    $value = trim((string)$value);
    if (strlen($value) > $max_length) {
        $value = substr($value, 0, $max_length);
    }

    return $value;
}

function validDateInput($value) {
    if ($value === '') {
        return true;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        return false;
    }

    $today = new DateTime('today');
    return $date <= $today;
}

function deleteProfilePhotoFile($relative_path) {
    if (!is_string($relative_path) || trim($relative_path) === '') {
        return;
    }

    $relative_path = str_replace('\\', '/', trim($relative_path));
    if (strpos($relative_path, 'uploads/student_profiles/') !== 0) {
        return;
    }

    $base_dir = realpath(__DIR__ . '/../uploads/student_profiles');
    if ($base_dir === false) {
        return;
    }

    $file_real_path = realpath(__DIR__ . '/../' . $relative_path);
    if ($file_real_path === false || !is_file($file_real_path)) {
        return;
    }

    if (strpos($file_real_path, $base_dir) !== 0) {
        return;
    }

    @unlink($file_real_path);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    requireValidCsrfToken();

    if (!$profile_table_exists) {
        $error_message = 'Student profile table is missing. Please import the updated database_fixed.sql.';
    } elseif ($student_id <= 0 || $user_id <= 0) {
        $error_message = 'Your account is not linked to a student record. Please contact admin.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = sanitizeProfileText($_POST['phone'] ?? '', 30);
        $address = sanitizeProfileText($_POST['address'] ?? '', 255);
        $date_of_birth = trim((string)($_POST['date_of_birth'] ?? ''));
        $guardian_name = sanitizeProfileText($_POST['guardian_name'] ?? '', 100);
        $guardian_phone = sanitizeProfileText($_POST['guardian_phone'] ?? '', 30);
        $bio = sanitizeProfileText($_POST['bio'] ?? '', 1000);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please enter a valid email address.';
        } elseif (!validDateInput($date_of_birth)) {
            $error_message = 'Date of birth must be a valid past date.';
        } else {
            $email_check_stmt = $conn->prepare('SELECT user_id FROM users WHERE user_id != ? AND (email = ? OR username = ?) LIMIT 1');
            if (!$email_check_stmt) {
                $error_message = 'Unable to validate email right now. Please try again.';
            } else {
                $email_check_stmt->bind_param('iss', $user_id, $email, $email);
                $email_check_stmt->execute();
                $email_exists = $email_check_stmt->get_result();
                if ($email_exists && $email_exists->num_rows > 0) {
                    $error_message = 'That email is already used by another account login.';
                }
                $email_check_stmt->close();
            }
        }

        $current_profile_photo = '';
        if ($error_message === '' && $profile_photo_column_exists) {
            $current_photo_stmt = $conn->prepare('SELECT profile_photo FROM student_profiles WHERE student_id = ? LIMIT 1');
            if ($current_photo_stmt) {
                $current_photo_stmt->bind_param('i', $student_id);
                $current_photo_stmt->execute();
                $current_photo_result = $current_photo_stmt->get_result();
                if ($current_photo_result && $current_photo_result->num_rows > 0) {
                    $current_photo_row = $current_photo_result->fetch_assoc();
                    $current_profile_photo = (string)($current_photo_row['profile_photo'] ?? '');
                }
                $current_photo_stmt->close();
            }
        }

        $new_profile_photo = $current_profile_photo;
        $uploaded_new_photo = false;
        $new_photo_full_path = '';
        $remove_photo = isset($_POST['remove_profile_photo']);

        $photo_file = $_FILES['profile_photo'] ?? null;
        $has_photo_upload = is_array($photo_file) && (($photo_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);

        if ($error_message === '' && ($has_photo_upload || $remove_photo) && !$profile_photo_column_exists) {
            $error_message = 'Profile picture support is missing in database. Please import the updated add_student_profile.sql.';
        }

        if ($error_message === '' && $profile_photo_column_exists && $remove_photo) {
            $new_profile_photo = '';
        }

        if ($error_message === '' && $profile_photo_column_exists && $has_photo_upload) {
            if (($photo_file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $error_message = 'Photo upload failed. Please try again.';
            } elseif (($photo_file['size'] ?? 0) <= 0 || ($photo_file['size'] ?? 0) > 2097152) {
                $error_message = 'Profile photo must be less than 2 MB.';
            } elseif (!is_uploaded_file((string)($photo_file['tmp_name'] ?? ''))) {
                $error_message = 'Invalid uploaded file.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = $finfo ? finfo_file($finfo, (string)$photo_file['tmp_name']) : '';
                if ($finfo) {
                    finfo_close($finfo);
                }

                $allowed_mime_types = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    'image/gif' => 'gif'
                ];

                if (!isset($allowed_mime_types[$mime])) {
                    $error_message = 'Only JPG, PNG, WEBP, and GIF images are allowed.';
                } else {
                    $upload_dir = __DIR__ . '/../uploads/student_profiles';
                    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                        $error_message = 'Unable to create upload folder.';
                    } else {
                        try {
                            $random_suffix = bin2hex(random_bytes(8));
                        } catch (Exception $exception) {
                            $random_suffix = str_replace('.', '', uniqid('', true));
                        }

                        $extension = $allowed_mime_types[$mime];
                        $filename = 'student_' . $student_id . '_' . $random_suffix . '.' . $extension;
                        $new_profile_photo = 'uploads/student_profiles/' . $filename;
                        $new_photo_full_path = $upload_dir . '/' . $filename;

                        if (!move_uploaded_file((string)$photo_file['tmp_name'], $new_photo_full_path)) {
                            $error_message = 'Unable to save uploaded photo.';
                        } else {
                            $uploaded_new_photo = true;
                        }
                    }
                }
            }
        }

        if ($error_message === '') {
            $dob_value = $date_of_birth !== '' ? $date_of_birth : null;
            $profile_photo_value = $new_profile_photo !== '' ? $new_profile_photo : null;

            $conn->begin_transaction();

            $user_stmt = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ? AND student_id = ? AND role = 'student' LIMIT 1");
            if (!$user_stmt) {
                $conn->rollback();
                $error_message = 'Unable to update profile email.';
            } else {
                $user_stmt->bind_param('sii', $email, $user_id, $student_id);
                if (!$user_stmt->execute()) {
                    $conn->rollback();
                    $error_message = 'Unable to update profile email.';
                }
                $user_stmt->close();
            }

            if ($error_message === '') {
                if ($profile_photo_column_exists) {
                    $profile_stmt = $conn->prepare(
                        'INSERT INTO student_profiles (student_id, phone, address, date_of_birth, guardian_name, guardian_phone, bio, profile_photo)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                            phone = VALUES(phone),
                            address = VALUES(address),
                            date_of_birth = VALUES(date_of_birth),
                            guardian_name = VALUES(guardian_name),
                            guardian_phone = VALUES(guardian_phone),
                            bio = VALUES(bio),
                            profile_photo = VALUES(profile_photo)'
                    );

                    if (!$profile_stmt) {
                        $conn->rollback();
                        $error_message = 'Unable to save student profile details.';
                    } else {
                        $profile_stmt->bind_param(
                            'isssssss',
                            $student_id,
                            $phone,
                            $address,
                            $dob_value,
                            $guardian_name,
                            $guardian_phone,
                            $bio,
                            $profile_photo_value
                        );

                        if (!$profile_stmt->execute()) {
                            $conn->rollback();
                            $error_message = 'Unable to save student profile details.';
                        }

                        $profile_stmt->close();
                    }
                } else {
                    $profile_stmt = $conn->prepare(
                        'INSERT INTO student_profiles (student_id, phone, address, date_of_birth, guardian_name, guardian_phone, bio)
                         VALUES (?, ?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                            phone = VALUES(phone),
                            address = VALUES(address),
                            date_of_birth = VALUES(date_of_birth),
                            guardian_name = VALUES(guardian_name),
                            guardian_phone = VALUES(guardian_phone),
                            bio = VALUES(bio)'
                    );

                    if (!$profile_stmt) {
                        $conn->rollback();
                        $error_message = 'Unable to save student profile details.';
                    } else {
                        $profile_stmt->bind_param(
                            'issssss',
                            $student_id,
                            $phone,
                            $address,
                            $dob_value,
                            $guardian_name,
                            $guardian_phone,
                            $bio
                        );

                        if (!$profile_stmt->execute()) {
                            $conn->rollback();
                            $error_message = 'Unable to save student profile details.';
                        }

                        $profile_stmt->close();
                    }
                }
            }

            if ($error_message === '') {
                $conn->commit();
                $_SESSION['email'] = $email;
                $sync_ok = $coordinator->syncStudentProfile($student_id);
                $success_message = $sync_ok
                    ? 'Profile saved and synced to your branch database.'
                    : 'Profile saved in the central coordinator, but branch sync failed.';

                if ($profile_photo_column_exists && $current_profile_photo !== $new_profile_photo && $current_profile_photo !== '') {
                    deleteProfilePhotoFile($current_profile_photo);
                }
            } else {
                if ($uploaded_new_photo && $new_photo_full_path !== '' && is_file($new_photo_full_path)) {
                    @unlink($new_photo_full_path);
                }
            }
        } else {
            if ($uploaded_new_photo && $new_photo_full_path !== '' && is_file($new_photo_full_path)) {
                @unlink($new_photo_full_path);
            }
        }
    }
}

$profile = null;
if ($profile_table_exists && $student_id > 0 && $user_id > 0) {
    $select_profile_photo = $profile_photo_column_exists ? 'sp.profile_photo' : 'NULL AS profile_photo';
    $profile_stmt = $conn->prepare(
        "SELECT
            u.username,
            u.email,
            s.student_id,
            s.name,
            s.gender,
            s.grade,
            s.academic_year,
            s.semester,
            sp.phone,
            sp.address,
            sp.date_of_birth,
            sp.guardian_name,
            sp.guardian_phone,
            sp.bio,
            $select_profile_photo
         FROM users u
         JOIN students s ON s.student_id = u.student_id
         LEFT JOIN student_profiles sp ON sp.student_id = s.student_id
         WHERE u.user_id = ? AND u.student_id = ? AND u.role = 'student'
         LIMIT 1"
    );

    if ($profile_stmt) {
        $profile_stmt->bind_param('ii', $user_id, $student_id);
        $profile_stmt->execute();
        $profile_result = $profile_stmt->get_result();
        $profile = $profile_result ? $profile_result->fetch_assoc() : null;
        $profile_stmt->close();
    }
}

if (!$profile_table_exists && $error_message === '') {
    $error_message = 'Student profile table is missing. Please import the updated database_fixed.sql.';
} elseif (!$profile && $error_message === '') {
    $error_message = 'Your account is not linked to a student record. Please contact admin.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">Student Record System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="report.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars((string)($_SESSION['display_name'] ?? 'Student'), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">My Profile</h1>
            </div>
        </div>

        <?php if ($success_message !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($profile): ?>
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header">Student Information</div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <?php if (!empty($profile['profile_photo'])): ?>
                                    <img src="../<?php echo htmlspecialchars((string)$profile['profile_photo'], ENT_QUOTES, 'UTF-8'); ?>"
                                         alt="Profile Photo"
                                         style="width:140px; height:140px; object-fit:cover; border-radius:50%; border:4px solid #d7e1ec;">
                                <?php else: ?>
                                    <div style="width:140px; height:140px; margin:0 auto; border-radius:50%; border:4px solid #d7e1ec; display:flex; align-items:center; justify-content:center; color:#6b7e91; font-weight:600;">No Photo</div>
                                <?php endif; ?>
                            </div>
                            <p class="mb-2"><strong>Name:</strong><br><?php echo htmlspecialchars($profile['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mb-2"><strong>Gender:</strong><br><?php echo htmlspecialchars($profile['gender'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mb-2"><strong>Grade:</strong><br><?php echo htmlspecialchars($profile['grade'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mb-2"><strong>Academic Year:</strong><br><?php echo htmlspecialchars($profile['academic_year'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mb-0"><strong>Semester:</strong><br><?php echo htmlspecialchars($profile['semester'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">Edit Profile</div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <?php csrfInput(); ?>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars((string)$profile['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="profile_photo" class="form-label">Profile Picture</label>
                                    <input type="file" class="form-control" id="profile_photo" name="profile_photo" accept=".jpg,.jpeg,.png,.webp,.gif,image/*">
                                    <small class="text-muted">JPG, PNG, WEBP, GIF. Max size: 2 MB.</small>
                                    <?php if (!empty($profile['profile_photo'])): ?>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" id="remove_profile_photo" name="remove_profile_photo" value="1">
                                            <label class="form-check-label" for="remove_profile_photo">Remove current photo</label>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="text" class="form-control" id="phone" name="phone" maxlength="30" value="<?php echo htmlspecialchars((string)($profile['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. +1 555 123 4567">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars((string)($profile['date_of_birth'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <input type="text" class="form-control" id="address" name="address" maxlength="255" value="<?php echo htmlspecialchars((string)($profile['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Street, city, state">
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="guardian_name" class="form-label">Guardian Name</label>
                                        <input type="text" class="form-control" id="guardian_name" name="guardian_name" maxlength="100" value="<?php echo htmlspecialchars((string)($profile['guardian_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="guardian_phone" class="form-label">Guardian Phone</label>
                                        <input type="text" class="form-control" id="guardian_phone" name="guardian_phone" maxlength="30" value="<?php echo htmlspecialchars((string)($profile['guardian_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="bio" class="form-label">Bio / Notes</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="4" maxlength="1000" placeholder="Short personal note or health/contact details."><?php echo htmlspecialchars((string)($profile['bio'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary" name="save_profile">Save Profile</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php
    $footer_base_path = '../';
    include __DIR__ . '/../includes/footer.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



