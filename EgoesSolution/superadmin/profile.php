<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);

// Fetch row from users table and directly extract official first/last names
$dbUser = null;
if ($userId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                full_name,
                profile_image,
                CASE
                    WHEN LOCATE(',', full_name) > 0 THEN TRIM(SUBSTRING_INDEX(full_name, ',', -1))
                    ELSE TRIM(SUBSTRING_INDEX(full_name, ' ', 1))
                END AS official_first_name,
                CASE
                    WHEN LOCATE(',', full_name) > 0 THEN TRIM(SUBSTRING_INDEX(full_name, ',', 1))
                    WHEN LOCATE(' ', full_name) > 0 THEN TRIM(SUBSTRING(full_name, LOCATE(' ', full_name) + 1))
                    ELSE ''
                END AS official_last_name
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $dbUser = $stmt->fetch();
    } catch (PDOException $e) {}
}

$officialFullName = trim((string) ($dbUser['full_name'] ?? ''));
$firstNamePart = trim((string) ($dbUser['official_first_name'] ?? ''));
$lastNamePart = trim((string) ($dbUser['official_last_name'] ?? ''));
if ($firstNamePart === '') {
    $firstNamePart = 'Superadmin';
}

$defaults = [
    'nickname' => '',
    'first_name' => $firstNamePart,
    'last_name' => $lastNamePart,
    'avatar' => '',
    'date_of_birth' => '',
    'gender' => '',
    'address' => '',
    'phone' => '',
    'email' => '',
];
$p = $defaults;

$message = null;
$error = null;
$isModal = isset($_GET['modal']) && $_GET['modal'] === '1';
$editMode = isset($_GET['edit']) && $_GET['edit'] === '1';
$profileBaseUrl = $isModal ? 'profile.php?modal=1' : 'profile.php';
$profileEditUrl = $isModal ? 'profile.php?modal=1&edit=1#edit-profile' : 'profile.php?edit=1#edit-profile';
$passwordPanelOpen = false;

try {
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS user_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            nickname VARCHAR(100) NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NULL,
            avatar VARCHAR(255) NULL,
            date_of_birth DATE NULL,
            gender VARCHAR(30) NULL,
            address VARCHAR(255) NULL,
            phone VARCHAR(30) NULL,
            email VARCHAR(191) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_user_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
} catch (PDOException $e) {}

if ($userId > 0) {
    try {
        $profileStmt = $pdo->prepare('
            SELECT nickname, first_name, last_name, avatar, date_of_birth, gender, address, phone, email
            FROM user_profiles
            WHERE user_id = ?
            LIMIT 1
        ');
        $profileStmt->execute([$userId]);
        $dbProfile = $profileStmt->fetch();
        if ($dbProfile) {
            foreach ($defaults as $k => $v) {
                if (array_key_exists($k, $dbProfile) && $dbProfile[$k] !== null) {
                    $p[$k] = (string) $dbProfile[$k];
                }
            }
            if (
                trim((string) ($p['first_name'] ?? '')) === '' ||
                (trim((string) ($p['last_name'] ?? '')) === '' && trim((string) ($p['first_name'] ?? '')) === $officialFullName)
            ) {
                $p['first_name'] = $firstNamePart;
                $p['last_name'] = $lastNamePart;
            }
        }
    } catch (PDOException $e) {}
}

if ($p['email'] === '' && !empty($_SESSION['user_email'])) {
    $p['email'] = $_SESSION['user_email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');
        $dob = trim($_POST['date_of_birth'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $profileImagePath = '';

        if (isset($_FILES['profile_image']) && is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
            $uploadError = $_FILES['profile_image']['error'];
            if ($uploadError === UPLOAD_ERR_OK) {
                $tmpFile = $_FILES['profile_image']['tmp_name'];
                $imageInfo = @getimagesize($tmpFile);
                $allowedTypes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
                if ($imageInfo === false || !isset($allowedTypes[$imageInfo[2]])) {
                    $error = 'Invalid profile image file type. Use JPG, PNG, WEBP, or GIF.';
                    $editMode = true;
                } else {
                    $extension = $allowedTypes[$imageInfo[2]];
                    $uploadDir = __DIR__ . '/../assets/images/profile';
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                        $error = 'Unable to create profile image directory.';
                        $editMode = true;
                    } else {
                        $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
                        $destination = $uploadDir . '/' . $filename;
                        if (!move_uploaded_file($tmpFile, $destination)) {
                            $error = 'Unable to save uploaded profile image.';
                            $editMode = true;
                        } else {
                            $profileImagePath = '../assets/images/profile/' . $filename;
                        }
                    }
                }
            } elseif ($uploadError !== UPLOAD_ERR_NO_FILE) {
                $error = 'Profile image upload failed. Please try again.';
                $editMode = true;
            }
        }

        if ($firstName === '') {
            $error = 'First name is required.';
            $editMode = true;
        } else {
            $p['nickname'] = $nickname;
            $p['first_name'] = $firstName;
            $p['last_name'] = $lastName;
            $p['date_of_birth'] = $dob;
            $p['gender'] = $gender;
            $p['address'] = $address;
            $p['phone'] = $phone;
            $p['email'] = $email;

            if ($error === null && $userId > 0) {
                try {
                    $upsertStmt = $pdo->prepare('
                        INSERT INTO user_profiles (user_id, nickname, first_name, last_name, avatar, date_of_birth, gender, address, phone, email)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            nickname = VALUES(nickname),
                            first_name = VALUES(first_name),
                            last_name = VALUES(last_name),
                            avatar = VALUES(avatar),
                            date_of_birth = VALUES(date_of_birth),
                            gender = VALUES(gender),
                            address = VALUES(address),
                            phone = VALUES(phone),
                            email = VALUES(email)
                    ');
                    $upsertStmt->execute([
                        $userId,
                        $p['nickname'] !== '' ? $p['nickname'] : null,
                        $p['first_name'],
                        $p['last_name'] !== '' ? $p['last_name'] : null,
                        $p['avatar'] !== '' ? $p['avatar'] : null,
                        $p['date_of_birth'] !== '' ? $p['date_of_birth'] : null,
                        $p['gender'] !== '' ? $p['gender'] : null,
                        $p['address'] !== '' ? $p['address'] : null,
                        $p['phone'] !== '' ? $p['phone'] : null,
                        $p['email'] !== '' ? $p['email'] : null,
                    ]);

                    $newFormattedFullName = $p['last_name'] !== '' ? $p['last_name'] . ', ' . $p['first_name'] : $p['first_name'];
                    $updateUserFullNameStmt = $pdo->prepare('UPDATE users SET full_name = ? WHERE id = ?');
                    $updateUserFullNameStmt->execute([$newFormattedFullName, $userId]);

                    if ($profileImagePath !== '' && $userId > 0) {
                        $updateImageStmt = $pdo->prepare('UPDATE users SET profile_image = ? WHERE id = ?');
                        $updateImageStmt->execute([$profileImagePath, $userId]);
                        $avatarUrl = $profileImagePath;
                        $_SESSION['superadmin_profile']['avatar'] = $avatarUrl;
                    }
                } catch (PDOException $e) {
                    $error = 'Unable to save profile. Please try again.';
                    $editMode = true;
                }
            }

            if ($error === null) {
                $finalDisplayName = $p['last_name'] !== '' ? $p['last_name'] . ', ' . $p['first_name'] : $p['first_name'];
                $_SESSION['display_name'] = $p['nickname'] !== '' ? $p['nickname'] : $finalDisplayName;
                header('Location: ' . ($isModal ? 'profile.php?modal=1&saved=1' : 'profile.php?saved=1'));
                exit;
            }
        }
    } elseif ($action === 'update_password') {
        $passwordPanelOpen = true;
        $currentPassword = trim($_POST['current_password'] ?? '');
        $newPassword = trim($_POST['new_password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $error = 'Please complete all password fields.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New password and confirmation do not match.';
        } elseif ($userId <= 0) {
            $error = 'Invalid user session. Please log in again.';
        } else {
            try {
                $userStmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE id = ? AND role = "superadmin" LIMIT 1');
                $userStmt->execute([$userId]);
                $row = $userStmt->fetch();
                if (!$row || !password_verify($currentPassword, (string) $row['password_hash'])) {
                    $error = 'Current password is incorrect.';
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                    $updateStmt->execute([$newHash, $userId]);
                    $message = 'Password updated successfully.';
                }
            } catch (PDOException $e) {
                $error = 'Unable to update password. Please try again.';
            }
        }
    }
}

if (isset($_GET['saved'])) {
    $message = 'Profile updated.';
}

$profile = $p;
$name = $_SESSION['display_name'] ?? 'Superadmin';
$avatarUrl = $profile['avatar'] ?? null;
$employeeCode = '';
$profileImage = '';
if ($userId > 0) {
    if ($dbUser && isset($dbUser['profile_image'])) {
        $profileImage = trim((string) $dbUser['profile_image']);
        if ($profileImage !== '') {
            $avatarUrl = $profileImage;
        }
    }
}

$showName = $profile['nickname'] !== '' ? $profile['nickname'] : trim($profile['first_name'] . ' ' . $profile['last_name']);
if ($showName === '') {
    $showName = 'Superadmin';
}
$fullDisplayName = $profile['last_name'] !== '' ? $profile['last_name'] . ', ' . $profile['first_name'] : $profile['first_name'];
if (trim($fullDisplayName) === '') {
    $fullDisplayName = '—';
}

function eg_sa_format_dob(?string $ymd): string {
    if ($ymd === null || $ymd === '') {
        return '—';
    }
    $t = strtotime($ymd);
    return $t ? date('M j, Y', $t) : '—';
}

function eg_sa_disp(?string $s): string {
    $s = trim((string) $s);
    return $s === '' ? '—' : $s;
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>EGoes Solutions</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="../assets/css/style.css" />
  </head>
  <body class="bg-light eg-profile-page eg-profile-page--ref<?= $isModal ? ' eg-profile-page--modal' : '' ?>">
    <?php if (!$isModal): ?>
      <?php include __DIR__ . '/../includes/header.php'; ?>
      <div class="container-fluid">
        <div class="row">
          <?php include __DIR__ . '/../includes/sidebar_superadmin.php'; ?>
          <main class="col-12 col-md-9 col-lg-10 py-3">
    <?php else: ?>
      <main class="py-3">
    <?php endif; ?>

          <div class="container-fluid pb-4">
            <?php if ($message): ?>
              <div class="alert alert-success eg-profile-alert mb-3"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
              <div class="alert alert-danger eg-profile-alert mb-3"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="eg-profile-wrapper">
              <div class="eg-profile-container eg-profile-container--ref">
                <div class="eg-profile-ref-card" id="edit-profile">
                <div class="eg-profile-ref-cardhead">
                  <div class="eg-profile-ref-hero">
                    <div class="eg-profile-ref-avatar position-relative" style="overflow: hidden;">
                      <?php if ($avatarUrl): ?>
                        <img id="avatarPreview" src="<?= htmlspecialchars($avatarUrl) ?>" alt="" style="object-fit: cover; width: 100%; height: 100%;" />
                      <?php else: ?>
                        <span class="bi bi-person-fill" id="avatarIconFallback"></span>
                        <img id="avatarPreview" src="" alt="" style="display: none; object-fit: cover; width: 100%; height: 100%;" />
                      <?php endif; ?>
                      
                      <?php if ($editMode): ?>
                        <label for="profile_image" class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center m-0" style="background: rgba(0,0,0,0.5); cursor: pointer; opacity: 0; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0" title="Change Profile Picture">
                          <i class="bi bi-camera text-white" style="font-size: 1.5rem;"></i>
                        </label>
                      <?php endif; ?>
                    </div>
                    <div class="eg-profile-ref-intro">
                      <h1 class="eg-profile-ref-name"><?= htmlspecialchars($showName) ?></h1>
                      <?php if (!$editMode): ?>
                        <a href="<?= $profileEditUrl ?>" class="eg-profile-ref-nicklink">Edit Nickname</a>
                      <?php else: ?>
                        <span class="eg-profile-ref-nicklink eg-profile-ref-nicklink--muted">Editing profile</span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <?php if (!$editMode): ?>
                    <a href="<?= $profileEditUrl ?>" class="eg-profile-ref-edit" aria-label="Edit profile">
                      <i class="bi bi-pencil"></i>
                    </a>
                  <?php endif; ?>
                </div>

                <hr class="eg-profile-ref-divider" />

                <h2 class="eg-profile-ref-section-title">Personal details</h2>

                <?php if ($editMode): ?>
                  <form method="post" enctype="multipart/form-data" class="eg-profile-ref-form">
                    <input type="hidden" name="action" value="update_profile" />
                    <input type="file" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/webp,image/gif" class="d-none" />
                    <div class="eg-profile-ref-grid">
                      <div class="eg-profile-ref-field">
                        <label class="eg-profile-ref-label" for="nickname">Nickname</label>
                        <input type="text" class="eg-profile-ref-input" id="nickname" name="nickname" value="<?= htmlspecialchars($profile['nickname']) ?>" placeholder="Display name" />
                      </div>
                      <div class="eg-profile-ref-field">
                        <label class="eg-profile-ref-label" for="first_name">First name</label>
                        <input type="text" class="eg-profile-ref-input" id="first_name" name="first_name" value="<?= htmlspecialchars($profile['first_name']) ?>" required />
                      </div>
                      <div class="eg-profile-ref-field">
                        <label class="eg-profile-ref-label" for="last_name">Last name</label>
                        <input type="text" class="eg-profile-ref-input" id="last_name" name="last_name" value="<?= htmlspecialchars($profile['last_name'] ?? '') ?>" />
                      </div>
                      <div class="eg-profile-ref-field">
                        <label class="eg-profile-ref-label" for="date_of_birth">Date of birth</label>
                        <input type="date" class="eg-profile-ref-input" id="date_of_birth" name="date_of_birth" value="<?= htmlspecialchars($profile['date_of_birth']) ?>" />
                      </div>
                      <div class="eg-profile-ref-field">
                        <label class="eg-profile-ref-label" for="gender">Gender</label>
                        <input type="text" class="eg-profile-ref-input" id="gender" name="gender" value="<?= htmlspecialchars($profile['gender']) ?>" placeholder="e.g. Male" />
                      </div>
                      <div class="eg-profile-ref-field eg-profile-ref-field--wide">
                        <label class="eg-profile-ref-label" for="address">Address</label>
                        <input type="text" class="eg-profile-ref-input" id="address" name="address" value="<?= htmlspecialchars($profile['address']) ?>" />
                      </div>
                      <div class="eg-profile-ref-field">
                        <label class="eg-profile-ref-label" for="phone">Phone number</label>
                        <input type="text" class="eg-profile-ref-input" id="phone" name="phone" value="<?= htmlspecialchars($profile['phone']) ?>" />
                      </div>
                      <div class="eg-profile-ref-field">
                        <label class="eg-profile-ref-label" for="email">Email</label>
                        <input type="email" class="eg-profile-ref-input" id="email" name="email" value="<?= htmlspecialchars($profile['email']) ?>" />
                      </div>
                    </div>
                    <div class="eg-profile-ref-form-actions">
                      <a href="<?= $profileBaseUrl ?>" class="eg-profile-ref-btn eg-profile-ref-btn--ghost">Cancel</a>
                      <button type="submit" class="eg-profile-ref-btn eg-profile-ref-btn--primary">Save changes</button>
                    </div>
                  </form>
                <?php else: ?>
                  <dl class="eg-profile-ref-dl">
                    <div class="eg-profile-ref-row">
                      <dt>First name</dt>
                      <dd><?= htmlspecialchars($profile['first_name'] !== '' ? $profile['first_name'] : '—') ?></dd>
                    </div>
                    <div class="eg-profile-ref-row">
                      <dt>Last name</dt>
                      <dd><?= htmlspecialchars($profile['last_name'] !== '' ? $profile['last_name'] : '—') ?></dd>
                    </div>
                    <div class="eg-profile-ref-row">
                      <dt>Date of Birth</dt>
                      <dd><?= htmlspecialchars(eg_sa_format_dob($profile['date_of_birth'] ?? '')) ?></dd>
                    </div>
                    <div class="eg-profile-ref-row">
                      <dt>Gender</dt>
                      <dd><?= htmlspecialchars(eg_sa_disp($profile['gender'] ?? '')) ?></dd>
                    </div>
                    <div class="eg-profile-ref-row">
                      <dt>Address</dt>
                      <dd><?= htmlspecialchars(eg_sa_disp($profile['address'] ?? '')) ?></dd>
                    </div>
                    <div class="eg-profile-ref-row">
                      <dt>Phone Number</dt>
                      <dd><?= htmlspecialchars(eg_sa_disp($profile['phone'] ?? '')) ?></dd>
                    </div>
                    <div class="eg-profile-ref-row">
                      <dt>Email</dt>
                      <dd><?= htmlspecialchars(eg_sa_disp($profile['email'] ?? '')) ?></dd>
                    </div>
                  </dl>
                  <div class="eg-panel p-4 mt-3 mb-3 eg-change-password-panel">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                      <h5 class="mb-0">Change Password</h5>
                      <button
                        class="btn btn-outline-primary btn-sm"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#changePasswordCollapse"
                        aria-expanded="<?= $passwordPanelOpen ? 'true' : 'false' ?>"
                        aria-controls="changePasswordCollapse">
                        Change Password
                      </button>
                    </div>
                    <div class="collapse<?= $passwordPanelOpen ? ' show' : '' ?> mt-3" id="changePasswordCollapse">
                      <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="update_password" />
                        <div class="col-md-4">
                          <label for="current_password" class="form-label">Current Password</label>
                          <input type="password" class="form-control" id="current_password" name="current_password" required />
                        </div>
                        <div class="col-md-4">
                          <label for="new_password" class="form-label">New Password</label>
                          <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required />
                        </div>
                        <div class="col-md-4">
                          <label for="confirm_password" class="form-label">Confirm New Password</label>
                          <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required />
                        </div>
                        <div class="col-12">
                          <button type="submit" class="btn btn-primary">Update Password</button>
                        </div>
                      </form>
                    </div>
                  </div>
                <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

        </main>
        <?php if (!$isModal): ?>
        </div>
      </div>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        <?php if ($isModal && isset($_GET['saved'])): ?>
        if (window.parent && window.parent !== window) {
          window.parent.postMessage({
            type: 'eg_profile_updated',
            profile: {
              displayName: <?= json_encode($showName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
              avatarUrl: <?= json_encode((string) ($avatarUrl ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
            }
          }, window.location.origin);
        }
        <?php endif; ?>

        const fileInput = document.getElementById('profile_image');
        const preview = document.getElementById('avatarPreview');
        const fallback = document.getElementById('avatarIconFallback');
        if (fileInput && preview) {
          fileInput.addEventListener('change', function () {
            if (this.files && this.files[0]) {
              const reader = new FileReader();
              reader.onload = function (e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                if (fallback) fallback.style.display = 'none';
              };
              reader.readAsDataURL(this.files[0]);
            }
          });
        }
      });
    </script>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </body>
</html>
