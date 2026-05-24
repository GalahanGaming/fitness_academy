<?php include "auth_check.php"; ?>
<?php include "config.php"; ?>

<?php
if ($_SESSION['role'] !== 'member') {
    header("Location: index.php");
    exit();
}

$member_id = $_SESSION['member_id'];
$user_id   = $_SESSION['user_id'];
$msg = "";
$msg_type = "";

// ─── UPLOAD PROFILE PHOTO ─────────────────────────────────────────────────────
if (isset($_POST['upload_photo']) && isset($_FILES['profile_photo'])) {
    $file     = $_FILES['profile_photo'];
    $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size = 2 * 1024 * 1024; // 2MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = "Upload failed. Please try again."; $msg_type = "error";
    } elseif (!in_array($file['type'], $allowed)) {
        $msg = "Only JPG, PNG, and WEBP images are allowed."; $msg_type = "error";
    } elseif ($file['size'] > $max_size) {
        $msg = "Image must be under 2MB."; $msg_type = "error";
    } else {
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'member_' . $member_id . '.' . strtolower($ext);
        $dest     = 'uploads/profile_photos/' . $filename;

        if (!is_dir('uploads/profile_photos')) mkdir('uploads/profile_photos', 0755, true);

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $stmt = $conn->prepare("UPDATE members SET profile_photo = ? WHERE member_id = ?");
            $stmt->bind_param("si", $filename, $member_id);
            $stmt->execute();
            $msg = "Profile photo updated!"; $msg_type = "success";
        } else {
            $msg = "Failed to save image. Check folder permissions."; $msg_type = "error";
        }
    }
}

// ─── UPDATE CONTACT NUMBER ────────────────────────────────────────────────────
if (isset($_POST['update_contact'])) {
    $new_contact = trim($_POST['contact_number']);
    if (empty($new_contact)) {
        $msg = "Contact number cannot be empty."; $msg_type = "error";
    } else {
        $stmt = $conn->prepare("UPDATE members SET contact_number = ? WHERE member_id = ?");
        $stmt->bind_param("si", $new_contact, $member_id);
        $stmt->execute();
        $msg = "Contact number updated successfully!"; $msg_type = "success";
    }
}

// ─── CHANGE PASSWORD ──────────────────────────────────────────────────────────
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password     = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password FROM user_account WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!password_verify($current_password, $user['password'])) {
        $msg = "Current password is incorrect."; $msg_type = "error";
    } elseif (strlen($new_password) < 8) {
        $msg = "New password must be at least 8 characters."; $msg_type = "error";
    } elseif ($new_password !== $confirm_password) {
        $msg = "New passwords do not match."; $msg_type = "error";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt2  = $conn->prepare("UPDATE user_account SET password = ? WHERE user_id = ?");
        $stmt2->bind_param("si", $hashed, $user_id);
        $stmt2->execute();
        $msg = "Password changed successfully!"; $msg_type = "success";
    }
}

// ─── FETCH MEMBER INFO ────────────────────────────────────────────────────────
$info_stmt = $conn->prepare("
    SELECT m.member_id, m.first_name, m.last_name, m.contact_number, m.gender,
           m.created_at, m.profile_photo, u.email
    FROM members m
    JOIN user_account u ON u.member_id = m.member_id
    WHERE m.member_id = ?
");
$info_stmt->bind_param("i", $member_id);
$info_stmt->execute();
$info = $info_stmt->get_result()->fetch_assoc();

$sub_stmt = $conn->prepare("
    SELECT s.status, s.start_date, s.end_date, p.plan_name
    FROM subscription s
    JOIN membership_plan p ON p.plan_id = s.plan_id
    WHERE s.member_id = ?
    ORDER BY s.created_at DESC LIMIT 1
");
$sub_stmt->bind_param("i", $member_id);
$sub_stmt->execute();
$sub = $sub_stmt->get_result()->fetch_assoc();

$photo_url = $info['profile_photo']
    ? 'uploads/profile_photos/' . htmlspecialchars($info['profile_photo'])
    : null;
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Profile - Fitness Academy</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .back-link { display:inline-flex; align-items:center; gap:6px; color:#888; font-size:13px; text-decoration:none; margin-bottom:1.2rem; transition:color 0.2s; }
        .back-link:hover { color:#e8ff47; }

        /* ── Photo upload area ── */
        .photo-section { text-align:center; margin-bottom:1.5rem; }
        .photo-circle {
            width: 120px; height: 120px;
            border-radius: 50%;
            border: 3px solid #e8ff47;
            margin: 0 auto 1rem;
            overflow: hidden;
            background: #1a1a1a;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            position: relative;
            transition: opacity 0.2s;
        }
        .photo-circle:hover { opacity: 0.85; }
        .photo-circle img { width:100%; height:100%; object-fit:cover; }
        .photo-placeholder { font-size: 3rem; color: #333; }
        .photo-overlay {
            position: absolute; inset: 0;
            background: rgba(0,0,0,0.5);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.2s;
            border-radius: 50%;
            font-size: 12px; color: #fff; text-align: center;
        }
        .photo-circle:hover .photo-overlay { opacity: 1; }
        .photo-input { display: none; }
        .photo-name { font-size: 12px; color: #555; margin-bottom: 0.75rem; }

        /* ── Member ID badge ── */
        .member-id-badge {
            background: linear-gradient(135deg, #1a1a1a, #222);
            border: 1px solid #e8ff47;
            border-radius: 12px;
            padding: 1.2rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .member-id-label { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:2px; margin-bottom:6px; }
        .member-id-number { font-family:'Bebas Neue',sans-serif; font-size:3rem; color:#e8ff47; line-height:1; letter-spacing:4px; }
        .member-id-sub { font-size:12px; color:#555; margin-top:6px; }

        /* ── Info rows ── */
        .info-row { display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid #1f1f1f; font-size:14px; }
        .info-row:last-child { border-bottom:none; }
        .info-label { color:#555; font-size:12px; text-transform:uppercase; letter-spacing:1px; }
        .info-value { color:#fff; font-weight:500; }

        .section-divider { border:none; border-top:1px solid #2a2a2a; margin:1.5rem 0; }
        .strength-bar  { height:3px; border-radius:2px; background:#222; margin-top:6px; overflow:hidden; }
        .strength-fill { height:100%; width:0%; border-radius:2px; transition:width 0.3s, background 0.3s; }
        .strength-label { font-size:11px; color:#555; margin-top:3px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="navbar-logo">Fitness Academy</div>
    <div class="navbar-user">
        Welcome, <span><?php echo htmlspecialchars($info['first_name']); ?></span>
        <a href="member_dashboard.php" style="color:#e8ff47; font-size:13px; text-decoration:none;">← Dashboard</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<div class="container">

    <a href="member_dashboard.php" class="back-link">← Back to Dashboard</a>
    <div class="page-title">My <span>Profile</span></div>

    <?php if ($msg !== ""): ?>
        <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
    <?php endif; ?>

    <div class="grid-2">

        <!-- LEFT COLUMN -->
        <div>
            <!-- PHOTO + MEMBER ID -->
            <div class="card" style="margin-bottom:1.5rem;">

                <!-- Photo upload -->
                <form method="POST" enctype="multipart/form-data" id="photo-form">
                    <div class="photo-section">
                        <div class="photo-circle" onclick="document.getElementById('photo-input').click()">
                            <?php if ($photo_url): ?>
                                <img src="<?php echo $photo_url; ?>" alt="Profile Photo" id="photo-preview">
                            <?php else: ?>
                                <div class="photo-placeholder" id="photo-placeholder">👤</div>
                                <img src="" alt="" id="photo-preview" style="display:none; width:100%; height:100%; object-fit:cover;">
                            <?php endif; ?>
                            <div class="photo-overlay">📷 Change Photo</div>
                        </div>
                        <input type="file" name="profile_photo" id="photo-input" class="photo-input"
                               accept="image/jpeg,image/png,image/webp"
                               onchange="previewPhoto(this)">
                        <div class="photo-name" id="photo-filename">
                            <?php echo $info['profile_photo'] ? 'Click photo to change' : 'Click to upload a photo'; ?>
                        </div>
                        <button type="submit" name="upload_photo" class="submit-btn" id="upload-btn" style="display:none;">
                            Save Photo
                        </button>
                    </div>
                </form>

                <!-- Member ID -->
                <div class="member-id-badge">
                    <div class="member-id-label">Your Member ID</div>
                    <div class="member-id-number">#<?php echo str_pad($info['member_id'], 4, '0', STR_PAD_LEFT); ?></div>
                    <div class="member-id-sub">Show this to staff when checking in</div>
                </div>
            </div>

            <!-- ACCOUNT INFO -->
            <div class="card">
                <div class="section-title">Account Information</div>
                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($info['first_name'] . ' ' . $info['last_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo htmlspecialchars($info['email']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Contact</span>
                    <span class="info-value"><?php echo htmlspecialchars($info['contact_number'] ?? '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Gender</span>
                    <span class="info-value"><?php echo htmlspecialchars($info['gender'] ?? '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Member Since</span>
                    <span class="info-value"><?php echo date('F d, Y', strtotime($info['created_at'])); ?></span>
                </div>

                <hr class="section-divider">
                <div class="section-title" style="font-size:1rem; margin-bottom:0.75rem;">Membership</div>
                <?php if ($sub): ?>
                    <div class="info-row">
                        <span class="info-label">Plan</span>
                        <span class="info-value"><?php echo htmlspecialchars($sub['plan_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="info-value"><span class="badge badge-<?php echo strtolower($sub['status']); ?>"><?php echo ucfirst($sub['status']); ?></span></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Expires</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($sub['end_date'])); ?></span>
                    </div>
                <?php else: ?>
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="info-value"><span class="badge badge-expired">No Plan</span></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div>
            <!-- UPDATE CONTACT -->
            <div class="card" style="margin-bottom:1.5rem;">
                <div class="section-title">Update Contact Number</div>
                <form method="POST">
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact_number"
                               placeholder="09XXXXXXXXX"
                               value="<?php echo htmlspecialchars($info['contact_number'] ?? ''); ?>"
                               required>
                    </div>
                    <button type="submit" name="update_contact" class="submit-btn">Save Contact</button>
                </form>
            </div>

            <!-- CHANGE PASSWORD -->
            <div class="card">
                <div class="section-title">Change Password</div>
                <form method="POST">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" placeholder="Enter current password" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" id="new_password"
                               placeholder="Min. 8 characters" required
                               oninput="checkStrength(this.value)">
                        <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
                        <div class="strength-label" id="strength-label"></div>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" placeholder="Repeat new password" required>
                    </div>
                    <button type="submit" name="change_password" class="submit-btn">Change Password</button>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview     = document.getElementById('photo-preview');
            const placeholder = document.getElementById('photo-placeholder');
            preview.src       = e.target.result;
            preview.style.display = 'block';
            if (placeholder) placeholder.style.display = 'none';
            document.getElementById('photo-filename').textContent = input.files[0].name;
            document.getElementById('upload-btn').style.display  = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function checkStrength(value) {
    const fill  = document.getElementById('strength-fill');
    const label = document.getElementById('strength-label');
    let strength = 0;
    if (value.length >= 8)          strength++;
    if (/[A-Z]/.test(value))        strength++;
    if (/[0-9]/.test(value))        strength++;
    if (/[^A-Za-z0-9]/.test(value)) strength++;
    const levels = [
        { width:'0%',   color:'#333',    text:'' },
        { width:'25%',  color:'#f44336', text:'Weak' },
        { width:'50%',  color:'#ff9800', text:'Fair' },
        { width:'75%',  color:'#e8ff47', text:'Good' },
        { width:'100%', color:'#4caf50', text:'Strong' },
    ];
    fill.style.width      = levels[strength].width;
    fill.style.background = levels[strength].color;
    label.style.color     = levels[strength].color;
    label.textContent     = levels[strength].text;
}
</script>
<script src="app.js"></script>
</body>
</html>
