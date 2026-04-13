<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'employee') {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$fullName = $_SESSION['display_name'] ?? 'Employee';
$parts = explode(' ', $fullName, 2);
$userId = (int) ($_SESSION['user_id'] ?? 0);

$defaults = [
    'nickname' => '',
    'first_name' => $parts[0] ?? 'Employee',
    'last_name' => $parts[1] ?? '',
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
$editMode = isset($_GET['edit']) && $_GET['edit'] === '1';

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
} catch (PDOException $e) {
    // Keep page usable even if table creation fails.
}

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
        }
    } catch (PDOException $e) {
        // Ignore read errors and continue with defaults.
    }
}

if ($p['email'] === '' && !empty($_SESSION['user_email'])) {
    $p['email'] = $_SESSION['user_email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                if ($profileImagePath !== '' && $userId > 0) {
                    $updateImageStmt = $pdo->prepare('UPDATE users SET profile_image = ? WHERE id = ?');
                    $updateImageStmt->execute([$profileImagePath, $userId]);
                    $avatarUrl = $profileImagePath;
                    $_SESSION['employee_profile']['avatar'] = $avatarUrl;
                }
            } catch (PDOException $e) {
                $error = 'Unable to save profile. Please try again.';
                $editMode = true;
            }
        }

        if ($error !== null) {
            // Keep user on edit form when save fails.
        } else {
            $_SESSION['display_name'] = $p['nickname'] !== '' ? $p['nickname'] : (trim($firstName . ' ' . $lastName) ?: $firstName);
            header('Location: profile.php?saved=1');
            exit;
        }
    }
}

if (isset($_GET['saved'])) {
    $message = 'Profile updated.';
}

$profile = $p;
$name = $_SESSION['display_name'] ?? 'Employee';
$avatarUrl = $profile['avatar'] ?? null;
$employeeCode = '';
$profileImage = '';
if ($userId > 0) {
    $employeeCodeStmt = $pdo->prepare('SELECT employee_code FROM employees WHERE user_id = ? LIMIT 1');
    $employeeCodeStmt->execute([$userId]);
    $employeeCode = (string) ($employeeCodeStmt->fetchColumn() ?: '');

    $profileImageStmt = $pdo->prepare('SELECT profile_image FROM users WHERE id = ? LIMIT 1');
    $profileImageStmt->execute([$userId]);
    $profileImage = trim((string) ($profileImageStmt->fetchColumn() ?: ''));
    if ($profileImage !== '') {
        $avatarUrl = $profileImage;
    }
}

$showName = $profile['nickname'] !== '' ? $profile['nickname'] : $profile['first_name'];
$fullDisplayName = trim($profile['first_name'] . ' ' . ($profile['last_name'] ?? ''));
if ($fullDisplayName === '') {
    $fullDisplayName = '—';
}

function eg_format_dob(?string $ymd): string
{
    if ($ymd === null || $ymd === '') {
        return '—';
    }
    $t = strtotime($ymd);
    return $t ? date('M j, Y', $t) : '—';
}

function eg_disp(?string $s): string
{
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
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous"
    />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="../assets/css/style.css" />
  </head>
  <body class="bg-light eg-profile-page eg-profile-page--ref">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="container-fluid pt-3">
      <div class="eg-profile-back-row">
        <a href="dashboard.php" class="eg-back-link eg-back-link--profile">
          <i class="bi bi-arrow-left"></i>
          <span>Back to Dashboard</span>
        </a>
      </div>
    </div>

    <div class="container-fluid pb-4">
      <?php if ($message): ?>
        <div class="alert alert-success eg-profile-alert mb-3"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger eg-profile-alert mb-3"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="eg-profile-container eg-profile-container--ref">
        <div class="eg-profile-ref-card" id="edit-profile">
          <div class="eg-profile-ref-cardhead">
            <div class="eg-profile-ref-hero">
              <div class="eg-profile-ref-avatar">
                <?php if ($avatarUrl): ?>
                  <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="" />
                <?php else: ?>
                  <span class="bi bi-person-fill"></span>
                <?php endif; ?>
              </div>
              <div class="eg-profile-ref-intro">
                <h1 class="eg-profile-ref-name"><?= htmlspecialchars($showName) ?></h1>
                <?php if (!$editMode): ?>
                  <a href="profile.php?edit=1#edit-profile" class="eg-profile-ref-nicklink">Edit Nickname</a>
                <?php else: ?>
                  <span class="eg-profile-ref-nicklink eg-profile-ref-nicklink--muted">Editing profile</span>
                <?php endif; ?>
              </div>
            </div>
            <?php if (!$editMode): ?>
              <a href="profile.php?edit=1#edit-profile" class="eg-profile-ref-edit" aria-label="Edit profile">
                <i class="bi bi-pencil"></i>
              </a>
            <?php endif; ?>
          </div>

          <hr class="eg-profile-ref-divider" />

          <h2 class="eg-profile-ref-section-title">Personal details</h2>

          <?php if ($editMode): ?>
            <form method="post" enctype="multipart/form-data" class="eg-profile-ref-form">
              <div class="eg-profile-ref-grid">
                <div class="eg-profile-ref-field">
                  <label class="eg-profile-ref-label" for="profile_image">Profile image</label>
                  <input type="file" class="eg-profile-ref-input" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/webp,image/gif" />
                </div>
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
                <a href="profile.php" class="eg-profile-ref-btn eg-profile-ref-btn--ghost">Cancel</a>
                <button type="submit" class="eg-profile-ref-btn eg-profile-ref-btn--primary">Save changes</button>
              </div>
            </form>
          <?php else: ?>
            <dl class="eg-profile-ref-dl">
              <div class="eg-profile-ref-row">
                <dt>Full name</dt>
                <dd><?= htmlspecialchars($fullDisplayName) ?></dd>
              </div>
              <div class="eg-profile-ref-row">
                <dt>Date of Birth</dt>
                <dd><?= htmlspecialchars(eg_format_dob($profile['date_of_birth'] ?? '')) ?></dd>
              </div>
              <div class="eg-profile-ref-row">
                <dt>Gender</dt>
                <dd><?= htmlspecialchars(eg_disp($profile['gender'] ?? '')) ?></dd>
              </div>
              <div class="eg-profile-ref-row">
                <dt>Address</dt>
                <dd><?= htmlspecialchars(eg_disp($profile['address'] ?? '')) ?></dd>
              </div>
              <div class="eg-profile-ref-row">
                <dt>Phone Number</dt>
                <dd><?= htmlspecialchars(eg_disp($profile['phone'] ?? '')) ?></dd>
              </div>
              <div class="eg-profile-ref-row">
                <dt>Email</dt>
                <dd><?= htmlspecialchars(eg_disp($profile['email'] ?? '')) ?></dd>
              </div>
            </dl>
            <hr class="eg-profile-ref-divider" />
            <h2 class="eg-profile-ref-section-title">Employee Barcode</h2>
            <?php if ($employeeCode !== ''): ?>
              <div class="mb-2 text-muted small">Employee Code: <strong><?= htmlspecialchars($employeeCode) ?></strong></div>
              <div class="bg-white p-3 border rounded mb-2 d-inline-block">
                <svg id="employeeBarcode"></svg>
              </div>
              <div>
                <button type="button" id="downloadBarcodeBtn" class="eg-profile-ref-btn eg-profile-ref-btn--primary">Download Barcode</button>
              </div>
            <?php else: ?>
              <p class="text-muted mb-2">No employee code found yet.</p>
            <?php endif; ?>
            <p class="eg-profile-ref-hint mb-0">
              <a href="profile.php?edit=1#edit-profile" class="eg-profile-ref-link">Edit personal details</a>
            </p>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
    <?php if ($employeeCode !== ''): ?>
      <script>
        (function () {
          const code = <?= json_encode($employeeCode) ?>;
          const employeeName = <?= json_encode($fullDisplayName !== '—' ? $fullDisplayName : $showName) ?>;
          const barcodeSvg = document.getElementById('employeeBarcode');
          const downloadBtn = document.getElementById('downloadBarcodeBtn');
          if (!barcodeSvg || !downloadBtn) return;

          const SVG_NS = 'http://www.w3.org/2000/svg';
          function renderBarcodeWithName(codeValue, nameValue) {
            const tempSvg = document.createElementNS(SVG_NS, 'svg');
            JsBarcode(tempSvg, codeValue, {
              format: 'CODE128',
              width: 2,
              height: 80,
              displayValue: true,
              margin: 8
            });

            const rawW = parseFloat(tempSvg.getAttribute('width') || '420');
            const rawH = parseFloat(tempSvg.getAttribute('height') || '140');
            const nameBand = 26;
            const totalH = rawH + nameBand;

            barcodeSvg.innerHTML = '';
            barcodeSvg.setAttribute('width', String(rawW));
            barcodeSvg.setAttribute('height', String(totalH));
            barcodeSvg.setAttribute('viewBox', `0 0 ${rawW} ${totalH}`);

            const bg = document.createElementNS(SVG_NS, 'rect');
            bg.setAttribute('x', '0');
            bg.setAttribute('y', '0');
            bg.setAttribute('width', String(rawW));
            bg.setAttribute('height', String(totalH));
            bg.setAttribute('fill', '#ffffff');
            barcodeSvg.appendChild(bg);

            if (nameValue) {
              const nameText = document.createElementNS(SVG_NS, 'text');
              nameText.setAttribute('x', String(rawW / 2));
              nameText.setAttribute('y', '16');
              nameText.setAttribute('text-anchor', 'middle');
              nameText.setAttribute('font-size', '14');
              nameText.setAttribute('font-family', 'Arial, sans-serif');
              nameText.setAttribute('fill', '#111111');
              nameText.textContent = nameValue;
              barcodeSvg.appendChild(nameText);
            }

            const g = document.createElementNS(SVG_NS, 'g');
            g.setAttribute('transform', `translate(0, ${nameBand})`);
            Array.from(tempSvg.childNodes).forEach(function (node) {
              g.appendChild(node.cloneNode(true));
            });
            barcodeSvg.appendChild(g);
          }

          renderBarcodeWithName(code, employeeName);

          downloadBtn.addEventListener('click', function () {
            const serializer = new XMLSerializer();
            const svgData = serializer.serializeToString(barcodeSvg);
            const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
            const svgUrl = URL.createObjectURL(svgBlob);
            const img = new Image();

            img.onload = function () {
              const canvas = document.createElement('canvas');
              const svgRect = barcodeSvg.getBoundingClientRect();
              const width = Math.max(1, Math.round(svgRect.width || 400));
              const height = Math.max(1, Math.round(svgRect.height || 140));
              canvas.width = width;
              canvas.height = height;

              const ctx = canvas.getContext('2d');
              if (!ctx) {
                URL.revokeObjectURL(svgUrl);
                return;
              }

              ctx.fillStyle = '#ffffff';
              ctx.fillRect(0, 0, width, height);
              ctx.drawImage(img, 0, 0, width, height);

              const pngUrl = canvas.toDataURL('image/png');
              const link = document.createElement('a');
              link.href = pngUrl;
              link.download = code + '-barcode.png';
              document.body.appendChild(link);
              link.click();
              document.body.removeChild(link);
              URL.revokeObjectURL(svgUrl);
            };

            img.onerror = function () {
              URL.revokeObjectURL(svgUrl);
            };

            img.src = svgUrl;
          });
        })();
      </script>
    <?php endif; ?>
  </body>
</html>
