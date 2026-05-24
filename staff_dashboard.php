<?php include "auth_check.php"; ?>
<?php include "config.php"; ?>

<?php
// Make sure only staff can access this
if ($_SESSION['role'] !== 'staff') {
    header("Location: index.php");
    exit();
}

$staff_user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";

// ─── CONFIRM / REJECT PAYMENT ────────────────────────────────────────────────
if (isset($_POST['action']) && isset($_POST['subscription_id'])) {
    $action = $_POST['action'];
    $sub_id = $_POST['subscription_id'];

    if ($action === 'confirm') {
        $stmt = $conn->prepare("UPDATE payment SET status = 'paid' WHERE subscription_id = ?");
        $stmt->bind_param("i", $sub_id);
        $stmt->execute();

        $stmt2 = $conn->prepare("UPDATE subscription SET status = 'active' WHERE subscription_id = ?");
        $stmt2->bind_param("i", $sub_id);
        $stmt2->execute();

        $msg = "Payment confirmed and membership activated!";
        $msg_type = "success";

    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE payment SET status = 'rejected' WHERE subscription_id = ?");
        $stmt->bind_param("i", $sub_id);
        $stmt->execute();

        $stmt2 = $conn->prepare("UPDATE subscription SET status = 'rejected' WHERE subscription_id = ?");
        $stmt2->bind_param("i", $sub_id);
        $stmt2->execute();

        $msg = "Payment rejected.";
        $msg_type = "error";
    }
}

// ─── DELETE PROMOTION ─────────────────────────────────────────────────────────
if (isset($_POST['delete_promo'])) {
    $del_promo_id = $_POST['del_promo_id'];
    $stmt = $conn->prepare("DELETE FROM promotions WHERE promo_id = ?");
    $stmt->bind_param("i", $del_promo_id);
    $stmt->execute();
    $msg = "Promotion deleted successfully.";
    $msg_type = "success";
}

// ─── RECORD ATTENDANCE ───────────────────────────────────────────────────────
if (isset($_POST['record_attendance'])) {
    $attend_member_id = intval($_POST['attend_member_id']);
    $attend_type      = $_POST['attend_type'];
    $today            = date('Y-m-d');
    $now              = date('H:i:s');

    if ($attend_type === 'time_in') {
        $check = $conn->prepare("SELECT * FROM attendance WHERE member_id = ? AND date = ?");
        $check->bind_param("is", $attend_member_id, $today);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $msg = "Member already timed in today!";
            $msg_type = "error";
        } else {
            $stmt = $conn->prepare("INSERT INTO attendance (member_id, date, time_in) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $attend_member_id, $today, $now);
            $stmt->execute();
            $msg = "Time in recorded successfully!";
            $msg_type = "success";
        }
    } elseif ($attend_type === 'time_out') {
        $stmt = $conn->prepare("UPDATE attendance SET time_out = ? WHERE member_id = ? AND date = ? AND time_out IS NULL");
        $stmt->bind_param("sis", $now, $attend_member_id, $today);
        $stmt->execute();

        if ($conn->affected_rows > 0) {
            $msg = "Time out recorded successfully!";
            $msg_type = "success";
        } else {
            $msg = "No time in found for this member today, or already timed out!";
            $msg_type = "error";
        }
    }
}

// ─── POST ANNOUNCEMENT ───────────────────────────────────────────────────────
if (isset($_POST['post_announcement'])) {
    $title   = $_POST['ann_title'];
    $message = $_POST['ann_message'];

    $stmt = $conn->prepare("INSERT INTO announcements (title, message, posted_by, created_at, is_active) VALUES (?, ?, ?, NOW(), 1)");
    $stmt->bind_param("ssi", $title, $message, $staff_user_id);
    $stmt->execute();

    $msg = "Announcement posted successfully!";
    $msg_type = "success";
}

// ─── TOGGLE ANNOUNCEMENT ─────────────────────────────────────────────────────
if (isset($_POST['toggle_announcement'])) {
    $ann_id     = $_POST['announcement_id'];
    $current    = $_POST['current_status'];
    $new_status = $current == 1 ? 0 : 1;

    $stmt = $conn->prepare("UPDATE announcements SET is_active = ? WHERE announcement_id = ?");
    $stmt->bind_param("ii", $new_status, $ann_id);
    $stmt->execute();

    $msg = "Announcement updated!";
    $msg_type = "success";
}

// ─── CREATE PROMOTION ─────────────────────────────────────────────────────────
if (isset($_POST['create_promo'])) {
    $promo_name  = $_POST['promo_name'];
    $description = $_POST['promo_description'];
    $discount    = $_POST['discount_percent'];
    $start_date  = $_POST['promo_start'];
    $end_date    = $_POST['promo_end'];
    $plan_id     = $_POST['promo_plan_id'] ?: null;

    $stmt = $conn->prepare("INSERT INTO promotions (promo_name, description, discount_percent, start_date, end_date, plan_id, created_by, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->bind_param("ssdssii", $promo_name, $description, $discount, $start_date, $end_date, $plan_id, $staff_user_id);
    $stmt->execute();

    $msg = "Promotion created successfully!";
    $msg_type = "success";
}

// ─── DEACTIVATE MEMBER ───────────────────────────────────────────────────────
if (isset($_POST['deactivate_member'])) {
    $target_member_id  = intval($_POST['target_member_id']);
    $deactivation_note = trim($_POST['deactivation_note']);

    if (empty($deactivation_note)) {
        $msg = "Please provide a reason for deactivation.";
        $msg_type = "error";
    } else {
        $stmt = $conn->prepare("
            UPDATE user_account
            SET is_active = 0,
                deactivation_note = ?,
                deactivated_by = ?,
                deactivated_at = NOW()
            WHERE member_id = ?
        ");
        $stmt->bind_param("sii", $deactivation_note, $staff_user_id, $target_member_id);
        $stmt->execute();

        $msg      = $conn->affected_rows > 0 ? "Member account has been deactivated." : "Could not deactivate. Please try again.";
        $msg_type = $conn->affected_rows > 0 ? "success" : "error";
    }
}

// ─── FETCH DATA ───────────────────────────────────────────────────────────────

$pending_payments = $conn->query("
    SELECT p.payment_id, p.amount, p.payment_date, p.payment_method,
           p.transaction_reference, p.subscription_id,
           m.first_name, m.last_name,
           pl.plan_name, s.start_date, s.end_date
    FROM payment p
    JOIN subscription s ON s.subscription_id = p.subscription_id
    JOIN members m ON m.member_id = s.member_id
    JOIN membership_plan pl ON pl.plan_id = s.plan_id
    WHERE p.status = 'pending'
    ORDER BY p.payment_date DESC
");

$members_list = $conn->query("
    SELECT m.member_id, m.first_name, m.last_name, m.contact_number, m.gender,
           m.profile_photo, u.email, u.user_id, u.is_active,
           s.status AS sub_status, s.end_date,
           pl.plan_name
    FROM members m
    JOIN user_account u ON u.member_id = m.member_id
    LEFT JOIN subscription s ON s.subscription_id = (
        SELECT subscription_id FROM subscription
        WHERE member_id = m.member_id
        ORDER BY created_at DESC LIMIT 1
    )
    LEFT JOIN membership_plan pl ON pl.plan_id = s.plan_id
    WHERE u.role = 'member' AND u.is_active = 1
    ORDER BY m.first_name
");

$current_month = date('Y-m');
$leaderboard = $conn->query("
    SELECT m.member_id, m.first_name, m.last_name,
           COUNT(a.attendance_id) AS visit_count
    FROM attendance a
    JOIN members m ON m.member_id = a.member_id
    JOIN user_account u ON u.member_id = m.member_id
    WHERE DATE_FORMAT(a.date, '%Y-%m') = '$current_month'
      AND u.role = 'member' AND u.is_active = 1
    GROUP BY a.member_id
    ORDER BY visit_count DESC
    LIMIT 5
");

$announcements = $conn->query("SELECT a.*, u.email FROM announcements a JOIN user_account u ON u.user_id = a.posted_by ORDER BY a.created_at DESC");
$plans  = $conn->query("SELECT * FROM membership_plan");
$promos = $conn->query("SELECT p.*, pl.plan_name FROM promotions p LEFT JOIN membership_plan pl ON pl.plan_id = p.plan_id ORDER BY p.start_date DESC");

$total_members  = $conn->query("SELECT COUNT(*) as c FROM members m JOIN user_account u ON u.member_id = m.member_id WHERE u.role = 'member' AND u.is_active = 1")->fetch_assoc()['c'];
$active_members = $conn->query("SELECT COUNT(*) as c FROM subscription WHERE status = 'active'")->fetch_assoc()['c'];
$pending_count  = $conn->query("SELECT COUNT(*) as c FROM payment WHERE status = 'pending'")->fetch_assoc()['c'];

$today_log = $conn->query("
    SELECT a.date, a.time_in, a.time_out, m.first_name, m.last_name, m.member_id
    FROM attendance a
    JOIN members m ON m.member_id = a.member_id
    WHERE a.date = CURDATE()
    ORDER BY a.time_in DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Staff Dashboard - Fitness Academy</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .search-filter-bar { display:flex; gap:12px; margin-bottom:1.2rem; flex-wrap:wrap; }
        .search-filter-bar input[type="text"] { flex:1; min-width:200px; background:#1a1a1a; color:#fff; border:1px solid #2a2a2a; padding:10px 14px; border-radius:8px; font-size:14px; }
        .search-filter-bar input[type="text"]:focus { outline:none; border-color:#e8ff47; }
        .search-filter-bar select { background:#1a1a1a; color:#fff; border:1px solid #2a2a2a; padding:10px 14px; border-radius:8px; font-size:14px; cursor:pointer; }
        .search-filter-bar select:focus { outline:none; border-color:#e8ff47; }

        .leaderboard { background:#141414; border:1px solid #2a2a2a; border-radius:12px; padding:1.2rem 1.5rem; margin-bottom:1.5rem; }
        .leaderboard-title { font-size:13px; font-weight:600; color:#e8ff47; text-transform:uppercase; letter-spacing:1px; margin-bottom:1rem; }
        .leaderboard-row { display:flex; align-items:center; gap:12px; padding:8px 0; border-bottom:1px solid #1e1e1e; }
        .leaderboard-row:last-child { border-bottom:none; }
        .leaderboard-rank { font-size:18px; width:30px; text-align:center; flex-shrink:0; }
        .leaderboard-name { flex:1; font-size:14px; color:#fff; }
        .leaderboard-count { font-size:13px; color:#888; }
        .leaderboard-count span { color:#e8ff47; font-weight:700; font-size:16px; }

        .btn-view-att { background:#1e1e1e; color:#e8ff47; border:1px solid #e8ff47; padding:5px 10px; border-radius:6px; font-size:12px; cursor:pointer; white-space:nowrap; }
        .btn-view-att:hover { background:#e8ff47; color:#000; }
        .btn-deactivate { background:#1e1e1e; color:#ff4d4d; border:1px solid #ff4d4d; padding:5px 10px; border-radius:6px; font-size:12px; cursor:pointer; white-space:nowrap; }
        .btn-deactivate:hover { background:#ff4d4d; color:#fff; }

        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.82); z-index:1000; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:#141414; border:1px solid #2a2a2a; border-radius:14px; padding:2rem; width:90%; max-width:500px; position:relative; }
        .modal-box h3 { font-size:18px; color:#fff; margin-bottom:0.4rem; }
        .modal-box p { font-size:13px; color:#888; margin-bottom:1.2rem; }
        .modal-close { position:absolute; top:14px; right:18px; background:none; border:none; color:#888; font-size:20px; cursor:pointer; }
        .modal-close:hover { color:#fff; }
        .modal-box textarea { width:100%; background:#1a1a1a; color:#fff; border:1px solid #2a2a2a; padding:10px 14px; border-radius:8px; font-size:14px; resize:vertical; min-height:100px; box-sizing:border-box; margin-bottom:1rem; }
        .modal-box textarea:focus { outline:none; border-color:#ff4d4d; }
        .modal-actions { display:flex; gap:10px; justify-content:flex-end; }
        .btn-cancel { background:#1e1e1e; color:#888; border:1px solid #2a2a2a; padding:8px 18px; border-radius:8px; cursor:pointer; font-size:14px; }
        .btn-cancel:hover { color:#fff; border-color:#fff; }
        .btn-confirm-deactivate { background:#ff4d4d; color:#fff; border:none; padding:8px 18px; border-radius:8px; cursor:pointer; font-size:14px; font-weight:600; }
        .btn-confirm-deactivate:hover { background:#e03c3c; }

        .att-modal-box { background:#141414; border:1px solid #2a2a2a; border-radius:14px; padding:2rem; width:90%; max-width:620px; position:relative; max-height:85vh; overflow-y:auto; }
        .att-month-count { background:#1a1a1a; border:1px solid #e8ff47; border-radius:10px; padding:14px 20px; margin-bottom:1.2rem; display:flex; align-items:center; gap:14px; }
        .att-month-count .big-num { font-size:2.5rem; font-weight:800; color:#e8ff47; line-height:1; }
        .att-month-count .label { font-size:13px; color:#888; }
        .att-month-count .label strong { display:block; color:#fff; font-size:15px; margin-bottom:2px; }

        /* Attendance lookup */
        .lookup-title { font-size:13px; font-weight:600; color:#e8ff47; text-transform:uppercase; letter-spacing:1px; margin-bottom:1rem; }
        .lookup-input-row { display:flex; gap:10px; align-items:center; margin-bottom:1rem; }
        .lookup-input-row input { flex:1; background:#1a1a1a; color:#fff; border:1px solid #2a2a2a; padding:12px 16px; border-radius:8px; font-size:18px; font-weight:600; letter-spacing:2px; outline:none; transition:border-color 0.2s; }
        .lookup-input-row input:focus { border-color:#e8ff47; }
        .lookup-input-row input::placeholder { font-size:14px; font-weight:400; letter-spacing:0; color:#444; }
        .btn-lookup { background:#e8ff47; color:#000; border:none; padding:12px 22px; border-radius:8px; font-size:14px; font-weight:700; cursor:pointer; white-space:nowrap; }
        .btn-lookup:hover { opacity:0.88; }

        .member-result-card { display:none; background:#1a1a1a; border:1px solid #2a2a2a; border-radius:10px; padding:1.2rem 1.5rem; margin-top:0.5rem; }
        .member-result-card.visible { display:block; }
        .member-result-card.status-active { border-color:#4caf50; }
        .member-result-card.status-expired { border-color:#ff4d4d; }
        .member-result-card.status-pending { border-color:#e8ff47; }

        .mrc-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:1rem; }
        .mrc-name { font-size:1.2rem; font-weight:700; color:#fff; margin-bottom:3px; }
        .mrc-id   { font-size:12px; color:#555; }
        .mrc-meta { font-size:13px; color:#888; margin-bottom:0.8rem; }
        .mrc-meta span { color:#fff; }
        .mrc-actions { display:flex; gap:10px; flex-wrap:wrap; }

        .btn-time-in  { flex:1; background:#4caf50; color:#fff; border:none; padding:11px 20px; border-radius:8px; font-size:14px; font-weight:700; cursor:pointer; }
        .btn-time-in:hover  { background:#43a047; }
        .btn-time-out { flex:1; background:#1e1e1e; color:#e8ff47; border:1px solid #e8ff47; padding:11px 20px; border-radius:8px; font-size:14px; font-weight:700; cursor:pointer; }
        .btn-time-out:hover { background:#e8ff47; color:#000; }
        .btn-clear-lookup { background:#1e1e1e; color:#888; border:1px solid #2a2a2a; padding:11px 16px; border-radius:8px; font-size:13px; cursor:pointer; }
        .btn-clear-lookup:hover { color:#fff; border-color:#555; }

        .lookup-error { display:none; background:#3a1a1a; color:#ff4d4d; border:1px solid #5a2a2a; border-radius:8px; padding:10px 14px; font-size:13px; margin-top:0.5rem; }
        .lookup-error.visible { display:block; }

        .today-log-title { font-size:13px; color:#555; text-transform:uppercase; letter-spacing:1px; margin-bottom:0.8rem; margin-top:1.5rem; }
        .member-row.hidden { display:none; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="navbar-logo">Fitness Academy</div>
    <div class="navbar-user">
        Staff Portal &nbsp;|&nbsp; <span><?php echo htmlspecialchars($_SESSION['email']); ?></span>
        <a href="attendance_report.php" style="color:#e8ff47; font-size:13px; text-decoration:none; margin-right:12px;">📊 Attendance Report</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<div class="container">

    <div class="page-title">Staff <span>Dashboard</span></div>

    <?php if ($msg !== ""): ?>
        <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
    <?php endif; ?>

    <div class="grid-3">
        <div class="card">
            <div class="card-title">Total Members</div>
            <div class="card-value"><?php echo $total_members; ?></div>
        </div>
        <div class="card">
            <div class="card-title">Active Memberships</div>
            <div class="card-value"><?php echo $active_members; ?></div>
        </div>
        <div class="card">
            <div class="card-title">Pending Payments</div>
            <div class="card-value" style="color:#e8ff47;"><?php echo $pending_count; ?></div>
        </div>
    </div>

    <div class="tabs">
        <button class="tab active" onclick="switchTab('payments', this)">Pending Payments</button>
        <button class="tab" onclick="switchTab('attendance', this)">Record Attendance</button>
        <button class="tab" onclick="switchTab('members', this)">Members</button>
        <button class="tab" onclick="switchTab('announcements', this)">Announcements</button>
        <button class="tab" onclick="switchTab('promotions', this)">Promotions</button>
    </div>

    <!-- TAB: PENDING PAYMENTS -->
    <div class="tab-content active" id="tab-payments">
        <div class="card">
            <div class="section-title">Pending Payment Requests</div>
            <?php if ($pending_payments->num_rows > 0): ?>
                <table>
                    <thead><tr><th>Member</th><th>Plan</th><th>Amount</th><th>Method</th><th>Reference</th><th>Date</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php while ($row = $pending_payments->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['plan_name']); ?></td>
                                <td>₱<?php echo number_format($row['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($row['transaction_reference']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['payment_date'])); ?></td>
                                <td style="display:flex; gap:6px;">
                                    <form method="POST">
                                        <input type="hidden" name="subscription_id" value="<?php echo $row['subscription_id']; ?>">
                                        <input type="hidden" name="action" value="confirm">
                                        <button type="submit" class="btn btn-confirm">Confirm</button>
                                    </form>
                                    <form method="POST">
                                        <input type="hidden" name="subscription_id" value="<?php echo $row['subscription_id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-reject">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color:#555; font-size:13px;">No pending payments at the moment.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: ATTENDANCE -->
    <div class="tab-content" id="tab-attendance">
        <div class="card">
            <div class="section-title">Record Attendance</div>
            <p style="color:#666; font-size:13px; margin-bottom:1.2rem;">
                Enter a member's <strong style="color:#e8ff47;">Member ID</strong> to look them up, then record their time in or out.
                <!-- QR scan button will plug in here in a future update -->
            </p>

            <div class="lookup-title">🔍 Member ID Lookup</div>
            <div class="lookup-input-row">
                <input
                    type="number"
                    id="member-id-input"
                    placeholder="Enter Member ID (e.g. 12)"
                    min="1"
                    onkeydown="if(event.key==='Enter'){lookupMember();}"
                >
                <button class="btn-lookup" onclick="lookupMember()">Search</button>
            </div>

            <div class="lookup-error" id="lookup-error"></div>

            <div class="member-result-card" id="member-card">
                <div class="mrc-header">
                    <div style="display:flex; align-items:center; gap:14px;">
                        <div style="width:60px; height:60px; border-radius:50%; overflow:hidden; border:2px solid #2a2a2a; flex-shrink:0; background:#1e1e1e; display:flex; align-items:center; justify-content:center;">
                            <img id="mrc-photo" src="" alt="" style="display:none; width:100%; height:100%; object-fit:cover;">
                            <div id="mrc-photo-placeholder" style="font-size:1.8rem; color:#333;">👤</div>
                        </div>
                        <div>
                            <div class="mrc-name" id="mrc-name"></div>
                            <div class="mrc-id"   id="mrc-id"></div>
                        </div>
                    </div>
                    <span class="badge" id="mrc-badge"></span>
                </div>
                <div class="mrc-meta">Plan: <span id="mrc-plan"></span> &nbsp;|&nbsp; Expiry: <span id="mrc-expiry"></span></div>
                <div class="mrc-meta" id="mrc-today-status" style="margin-bottom:1rem;"></div>

                <form method="POST" id="attendance-form">
                    <input type="hidden" name="record_attendance" value="1">
                    <input type="hidden" name="attend_member_id" id="attend-member-id-hidden">
                    <input type="hidden" name="attend_type"      id="attend-type-hidden">
                    <div class="mrc-actions">
                        <button type="button" class="btn-time-in"      onclick="submitAttendance('time_in')">✅ Time In</button>
                        <button type="button" class="btn-time-out"     onclick="submitAttendance('time_out')">🚪 Time Out</button>
                        <button type="button" class="btn-clear-lookup" onclick="clearLookup()">✕ Clear</button>
                    </div>
                </form>
            </div>

            <!-- TODAY'S LOG -->
            <div class="today-log-title">📋 Today's Attendance — <?php echo date('F d, Y'); ?></div>
            <?php if ($today_log->num_rows > 0): ?>
                <table>
                    <thead><tr><th>Member</th><th>ID</th><th>Time In</th><th>Time Out</th></tr></thead>
                    <tbody>
                        <?php while ($tl = $today_log->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tl['first_name'] . ' ' . $tl['last_name']); ?></td>
                                <td style="color:#555;">#<?php echo $tl['member_id']; ?></td>
                                <td><?php echo date('h:i A', strtotime($tl['time_in'])); ?></td>
                                <td><?php echo $tl['time_out'] ? date('h:i A', strtotime($tl['time_out'])) : '<span style="color:#555;">—</span>'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color:#555; font-size:13px;">No attendance recorded today yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: MEMBERS -->
    <div class="tab-content" id="tab-members">
        <?php
        $leaderboard_rows = [];
        while ($lr = $leaderboard->fetch_assoc()) $leaderboard_rows[] = $lr;
        $medals = ['🥇','🥈','🥉','4️⃣','5️⃣'];
        ?>
        <div class="leaderboard">
            <div class="leaderboard-title">🏆 Most Frequent This Month — <?php echo date('F Y'); ?></div>
            <?php if (!empty($leaderboard_rows)): ?>
                <?php foreach ($leaderboard_rows as $i => $lr): ?>
                    <div class="leaderboard-row">
                        <div class="leaderboard-rank"><?php echo $medals[$i]; ?></div>
                        <div class="leaderboard-name"><?php echo htmlspecialchars($lr['first_name'] . ' ' . $lr['last_name']); ?></div>
                        <div class="leaderboard-count"><span><?php echo $lr['visit_count']; ?></span> visits</div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:#555; font-size:13px; margin:0;">No attendance recorded this month yet.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="section-title">Member List</div>
            <div class="search-filter-bar">
                <input type="text" id="member-search" placeholder="🔍  Search by name, email or number..." oninput="filterMembers()">
                <select id="status-filter" onchange="filterMembers()">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="expired">Expired</option>
                    <option value="pending">Pending</option>
                    <option value="none">No Plan</option>
                </select>
            </div>

            <?php if ($members_list->num_rows > 0): ?>
                <table>
                    <thead><tr><th>Photo</th><th>ID</th><th>Name</th><th>Email</th><th>Contact</th><th>Plan</th><th>Status</th><th>Expiry</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php
                        $members_list->data_seek(0);
                        while ($mem = $members_list->fetch_assoc()):
                            $end_ts = $mem['end_date'] ? strtotime($mem['end_date']) : null;
                            $sub_status_lower = strtolower($mem['sub_status'] ?? '');
                            if (!$mem['plan_name']) {
                                $display_status = 'none'; $badge_class = 'badge-expired'; $status_label = 'No Plan';
                            } elseif ($sub_status_lower === 'active' && $end_ts && $end_ts > time()) {
                                $display_status = 'active'; $badge_class = 'badge-active'; $status_label = 'Active';
                            } elseif ($sub_status_lower === 'pending') {
                                $display_status = 'pending'; $badge_class = 'badge-pending'; $status_label = 'Pending';
                            } else {
                                $display_status = 'expired'; $badge_class = 'badge-expired'; $status_label = 'Expired';
                            }
                        ?>
                        <tr class="member-row"
                            data-name="<?php echo strtolower($mem['first_name'] . ' ' . $mem['last_name']); ?>"
                            data-email="<?php echo strtolower($mem['email']); ?>"
                            data-contact="<?php echo $mem['contact_number']; ?>"
                            data-status="<?php echo $display_status; ?>">
                            <td style="color:#555;">#<?php echo $mem['member_id']; ?></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:36px; height:36px; border-radius:50%; overflow:hidden; border:1px solid #2a2a2a; background:#1a1a1a; flex-shrink:0; display:flex; align-items:center; justify-content:center;">
                                        <?php if ($mem['profile_photo']): ?>
                                            <img src="uploads/profile_photos/<?php echo htmlspecialchars($mem['profile_photo']); ?>"
                                                 class="member-photo"
                                                 data-src="uploads/profile_photos/<?php echo htmlspecialchars($mem['profile_photo']); ?>"
                                                 data-name="<?php echo htmlspecialchars($mem['first_name'] . ' ' . $mem['last_name']); ?>"
                                                 style="width:100%; height:100%; object-fit:cover;">
                                        <?php else: ?>
                                            <span style="font-size:1rem; color:#333;">👤</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php echo htmlspecialchars($mem['first_name'] . ' ' . $mem['last_name']); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($mem['email']); ?></td>
                            <td><?php echo htmlspecialchars($mem['contact_number']); ?></td>
                            <td><?php echo $mem['plan_name'] ? htmlspecialchars($mem['plan_name']) : '—'; ?></td>
                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo $status_label; ?></span></td>
                            <td><?php echo $mem['end_date'] ? date('M d, Y', strtotime($mem['end_date'])) : '—'; ?></td>
                            <td style="display:flex; gap:6px; flex-wrap:wrap;">
                                <button class="btn-view-att" onclick="openAttModal(<?php echo $mem['member_id']; ?>, '<?php echo htmlspecialchars(addslashes($mem['first_name'] . ' ' . $mem['last_name'])); ?>')">📅 Attendance</button>
                                <button class="btn-deactivate" onclick="openDeactivateModal(<?php echo $mem['member_id']; ?>, '<?php echo htmlspecialchars(addslashes($mem['first_name'] . ' ' . $mem['last_name'])); ?>')">🚫 Deactivate</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <p id="no-results" style="display:none; color:#555; font-size:13px; margin-top:10px;">No members match your search.</p>
            <?php else: ?>
                <p style="color:#555; font-size:13px;">No members registered yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: ANNOUNCEMENTS -->
    <div class="tab-content" id="tab-announcements">
        <div class="grid-2">
            <div class="card">
                <div class="section-title">Post Announcement</div>
                <form method="POST">
                    <div class="form-group"><label>Title</label><input type="text" name="ann_title" placeholder="e.g. Gym closed this Saturday" required></div>
                    <div class="form-group"><label>Message</label><textarea name="ann_message" placeholder="Write your announcement here..." required></textarea></div>
                    <button type="submit" name="post_announcement" class="submit-btn">Post Announcement</button>
                </form>
            </div>
            <div class="card">
                <div class="section-title">Posted Announcements</div>
                <?php if ($announcements->num_rows > 0): ?>
                    <table>
                        <thead><tr><th>Title</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php while ($ann = $announcements->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ann['title']); ?></td>
                                    <td><?php echo date('M d', strtotime($ann['created_at'])); ?></td>
                                    <td><span class="badge <?php echo $ann['is_active'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $ann['is_active'] ? 'Active' : 'Hidden'; ?></span></td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="announcement_id" value="<?php echo $ann['announcement_id']; ?>">
                                            <input type="hidden" name="current_status"  value="<?php echo $ann['is_active']; ?>">
                                            <button type="submit" name="toggle_announcement" class="btn btn-toggle"><?php echo $ann['is_active'] ? 'Hide' : 'Show'; ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color:#555; font-size:13px;">No announcements yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB: PROMOTIONS -->
    <div class="tab-content" id="tab-promotions">
        <div class="grid-2">
            <div class="card">
                <div class="section-title">Create Promotion</div>
                <form method="POST">
                    <div class="form-group"><label>Promo Name</label><input type="text" name="promo_name" placeholder="e.g. December Sale" required></div>
                    <div class="form-group"><label>Description</label><textarea name="promo_description" placeholder="Brief description..."></textarea></div>
                    <div class="inline-grid">
                        <div class="form-group"><label>Discount (%)</label><input type="number" name="discount_percent" min="1" max="100" placeholder="e.g. 25" required></div>
                        <div class="form-group">
                            <label>Apply to Plan</label>
                            <select name="promo_plan_id">
                                <option value="">All Plans</option>
                                <?php $plans->data_seek(0); while ($pl = $plans->fetch_assoc()): ?>
                                    <option value="<?php echo $pl['plan_id']; ?>"><?php echo htmlspecialchars($pl['plan_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="inline-grid">
                        <div class="form-group"><label>Start Date</label><input type="date" name="promo_start" required></div>
                        <div class="form-group"><label>End Date</label><input type="date" name="promo_end" required></div>
                    </div>
                    <button type="submit" name="create_promo" class="submit-btn">Create Promotion</button>
                </form>
            </div>
            <div class="card">
                <div class="section-title">Active Promotions</div>
                <?php if ($promos->num_rows > 0): ?>
                    <table>
                        <thead><tr><th>Name</th><th>Discount</th><th>Plan</th><th>Until</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php while ($promo = $promos->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($promo['promo_name']); ?></td>
                                    <td style="color:#e8ff47;"><?php echo $promo['discount_percent']; ?>%</td>
                                    <td><?php echo $promo['plan_name'] ?? 'All Plans'; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($promo['end_date'])); ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Delete this promotion?');">
                                            <input type="hidden" name="del_promo_id" value="<?php echo $promo['promo_id']; ?>">
                                            <button type="submit" name="delete_promo" class="btn btn-delete">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color:#555; font-size:13px;">No promotions yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- DEACTIVATE MODAL -->
<div class="modal-overlay" id="deactivate-modal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeDeactivateModal()">✕</button>
        <h3>🚫 Deactivate Account</h3>
        <p id="deactivate-member-name" style="color:#e8ff47; font-size:15px; margin-bottom:0.3rem;"></p>
        <p>Please provide a reason. This note will be visible to the owner.</p>
        <form method="POST" id="deactivate-form">
            <input type="hidden" name="target_member_id" id="deactivate-member-id">
            <textarea name="deactivation_note" placeholder="e.g. Member was caught selling fake supplements on gym premises..." required></textarea>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeDeactivateModal()">Cancel</button>
                <button type="submit" name="deactivate_member" class="btn-confirm-deactivate">Confirm Deactivation</button>
            </div>
        </form>
    </div>
</div>

<!-- ATTENDANCE VIEWER MODAL -->
<div class="modal-overlay" id="att-modal">
    <div class="att-modal-box">
        <button class="modal-close" onclick="closeAttModal()">✕</button>
        <h3 id="att-modal-name" style="color:#fff; margin-bottom:1rem;"></h3>
        <div class="att-month-count">
            <div class="big-num" id="att-month-count-num">—</div>
            <div class="label">
                <strong id="att-month-label">This Month's Visits</strong>
                Great for tracking giveaway eligibility!
            </div>
        </div>
        <div id="att-modal-body"><p style="color:#555; font-size:13px;">Loading...</p></div>
    </div>
</div>

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + name).classList.add('active');
}

function filterMembers() {
    const search = document.getElementById('member-search').value.toLowerCase();
    const filter = document.getElementById('status-filter').value;
    const rows   = document.querySelectorAll('.member-row');
    let visible  = 0;
    rows.forEach(row => {
        const matchSearch = row.dataset.name.includes(search) || row.dataset.email.includes(search) || row.dataset.contact.includes(search);
        const matchFilter = filter === 'all' || row.dataset.status === filter;
        if (matchSearch && matchFilter) { row.classList.remove('hidden'); visible++; }
        else row.classList.add('hidden');
    });
    document.getElementById('no-results').style.display = visible === 0 ? 'block' : 'none';
}

function lookupMember() {
    const id    = document.getElementById('member-id-input').value.trim();
    const card  = document.getElementById('member-card');
    const error = document.getElementById('lookup-error');

    card.classList.remove('visible','status-active','status-expired','status-pending');
    error.classList.remove('visible');

    if (!id || isNaN(id) || parseInt(id) < 1) {
        error.textContent = 'Please enter a valid Member ID.';
        error.classList.add('visible');
        return;
    }

    fetch('lookup_member.php?member_id=' + parseInt(id))
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                error.textContent = data.error;
                error.classList.add('visible');
                return;
            }

            document.getElementById('mrc-name').textContent   = data.name;
            document.getElementById('mrc-id').textContent     = 'Member ID #' + data.member_id;
            document.getElementById('mrc-plan').textContent   = data.plan   || 'No plan';
            document.getElementById('mrc-expiry').textContent = data.expiry || '—';
            document.getElementById('attend-member-id-hidden').value = data.member_id;

            // Show photo if available
            const photoEl = document.getElementById('mrc-photo');
            if (data.photo_url) {
                photoEl.src = data.photo_url;
                photoEl.style.display = 'block';
                photoEl.dataset.src  = data.photo_url;
                photoEl.dataset.name = data.name;
                photoEl.className    = 'member-photo';
                document.getElementById('mrc-photo-placeholder').style.display = 'none';
            } else {
                photoEl.style.display = 'none';
                document.getElementById('mrc-photo-placeholder').style.display = 'flex';
            }

            const badge = document.getElementById('mrc-badge');
            badge.className = 'badge';
            if (data.status === 'active') {
                badge.classList.add('badge-active');  badge.textContent = 'Active';  card.classList.add('status-active');
            } else if (data.status === 'pending') {
                badge.classList.add('badge-pending'); badge.textContent = 'Pending'; card.classList.add('status-pending');
            } else {
                badge.classList.add('badge-expired'); badge.textContent = data.status === 'none' ? 'No Plan' : 'Expired'; card.classList.add('status-expired');
            }

            // Block Time In if membership not active
            const timeInBtn  = document.querySelector('.btn-time-in');
            const timeOutBtn = document.querySelector('.btn-time-out');

            if (!data.can_checkin) {
                timeInBtn.disabled = true;
                timeInBtn.style.opacity = '0.35';
                timeInBtn.style.cursor  = 'not-allowed';
                timeOutBtn.disabled = true;
                timeOutBtn.style.opacity = '0.35';
                timeOutBtn.style.cursor  = 'not-allowed';
            } else {
                timeInBtn.disabled = false;
                timeInBtn.style.opacity = '1';
                timeInBtn.style.cursor  = 'pointer';
                timeOutBtn.disabled = false;
                timeOutBtn.style.opacity = '1';
                timeOutBtn.style.cursor  = 'pointer';
            }

            const todayEl = document.getElementById('mrc-today-status');
            if (!data.can_checkin) {
                const reasonMap = { none: 'No active plan', expired: 'Membership expired', pending: 'Payment pending confirmation' };
                todayEl.innerHTML = '🚫 <span style="color:#ff4d4d;">Cannot check in — ' + (reasonMap[data.status] || 'Inactive membership') + '</span>';
            } else if (data.timed_in_today && !data.timed_out_today) {
                todayEl.innerHTML = '🟢 <span style="color:#4caf50;">Timed in today at ' + data.time_in_today + '</span> — ready to time out';
            } else if (data.timed_in_today && data.timed_out_today) {
                todayEl.innerHTML = '✅ <span style="color:#888;">Completed attendance today (' + data.time_in_today + ' → ' + data.time_out_today + ')</span>';
            } else {
                todayEl.innerHTML = '⚪ <span style="color:#888;">Not yet timed in today</span>';
            }

            card.classList.add('visible');
        })
        .catch(() => {
            error.textContent = 'Could not reach server. Make sure lookup_member.php exists.';
            error.classList.add('visible');
        });
}

function submitAttendance(type) {
    document.getElementById('attend-type-hidden').value = type;
    document.getElementById('attendance-form').submit();
}

function clearLookup() {
    document.getElementById('member-id-input').value = '';
    document.getElementById('member-card').classList.remove('visible','status-active','status-expired','status-pending');
    document.getElementById('lookup-error').classList.remove('visible');
    document.getElementById('member-id-input').focus();
}

function openDeactivateModal(memberId, memberName) {
    document.getElementById('deactivate-member-id').value        = memberId;
    document.getElementById('deactivate-member-name').textContent = memberName;
    document.getElementById('deactivate-modal').classList.add('open');
}
function closeDeactivateModal() {
    document.getElementById('deactivate-modal').classList.remove('open');
    document.getElementById('deactivate-form').reset();
}

function openAttModal(memberId, memberName) {
    document.getElementById('att-modal-name').textContent      = '📅 ' + memberName;
    document.getElementById('att-month-count-num').textContent = '—';
    document.getElementById('att-modal-body').innerHTML        = '<p style="color:#555;font-size:13px;">Loading...</p>';
    document.getElementById('att-modal').classList.add('open');

    fetch('get_member_attendance.php?member_id=' + memberId)
        .then(r => r.json())
        .then(data => {
            document.getElementById('att-month-count-num').textContent = data.month_count;
            document.getElementById('att-month-label').textContent     = 'Visits in ' + data.month_label;
            if (data.records.length === 0) {
                document.getElementById('att-modal-body').innerHTML = '<p style="color:#555;font-size:13px;">No attendance records found.</p>';
                return;
            }
            let html = '<table><thead><tr><th>Date</th><th>Time In</th><th>Time Out</th></tr></thead><tbody>';
            data.records.forEach(r => { html += `<tr><td>${r.date}</td><td>${r.time_in}</td><td>${r.time_out || '—'}</td></tr>`; });
            html += '</tbody></table>';
            document.getElementById('att-modal-body').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('att-modal-body').innerHTML = '<p style="color:#ff4d4d;font-size:13px;">Failed to load attendance.</p>';
        });
}
function closeAttModal() { document.getElementById('att-modal').classList.remove('open'); }

document.getElementById('deactivate-modal').addEventListener('click', function(e) { if (e.target === this) closeDeactivateModal(); });
document.getElementById('att-modal').addEventListener('click',        function(e) { if (e.target === this) closeAttModal(); });
</script>

<script src="app.js"></script>
</body>
</html>
