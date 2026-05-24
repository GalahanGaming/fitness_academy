<?php include "auth_check.php"; ?>
<?php include "config.php"; ?>

<?php
if ($_SESSION['role'] !== 'owner' && $_SESSION['role'] !== 'manager') {
    header("Location: index.php");
    exit();
}

$msg = "";
$msg_type = "";

// ─── DELETE MEMBER (full cascade) ────────────────────────────────────────────
if (isset($_POST['delete_member'])) {
    $del_member_id = intval($_POST['del_member_id']);

    $stmt = $conn->prepare("DELETE FROM payment WHERE subscription_id IN (SELECT subscription_id FROM subscription WHERE member_id = ?)");
    $stmt->bind_param("i", $del_member_id); $stmt->execute();

    $stmt2 = $conn->prepare("DELETE FROM subscription WHERE member_id = ?");
    $stmt2->bind_param("i", $del_member_id); $stmt2->execute();

    $stmt3 = $conn->prepare("DELETE FROM attendance WHERE member_id = ?");
    $stmt3->bind_param("i", $del_member_id); $stmt3->execute();

    $stmt4 = $conn->prepare("DELETE FROM user_account WHERE member_id = ?");
    $stmt4->bind_param("i", $del_member_id); $stmt4->execute();

    $stmt5 = $conn->prepare("DELETE FROM members WHERE member_id = ?");
    $stmt5->bind_param("i", $del_member_id); $stmt5->execute();

    $msg = "Member deleted successfully."; $msg_type = "success";
}

// ─── REACTIVATE MEMBER ───────────────────────────────────────────────────────
if (isset($_POST['reactivate_member'])) {
    $react_member_id = intval($_POST['react_member_id']);

    $stmt = $conn->prepare("
        UPDATE user_account
        SET is_active = 1, deactivation_note = NULL, deactivated_by = NULL, deactivated_at = NULL
        WHERE member_id = ?
    ");
    $stmt->bind_param("i", $react_member_id);
    $stmt->execute();

    $msg = "Member account has been reactivated."; $msg_type = "success";
}

// ─── ADD MEMBERSHIP PLAN ──────────────────────────────────────────────────────
if (isset($_POST['add_plan'])) {
    $stmt = $conn->prepare("INSERT INTO membership_plan (plan_name, price, duration) VALUES (?, ?, ?)");
    $stmt->bind_param("sdi", $_POST['plan_name'], $_POST['price'], $_POST['duration']);
    $stmt->execute();
    $msg = "New plan added successfully!"; $msg_type = "success";
}

// ─── UPDATE MEMBERSHIP PLAN ───────────────────────────────────────────────────
if (isset($_POST['update_plan'])) {
    $stmt = $conn->prepare("UPDATE membership_plan SET plan_name = ?, price = ?, duration = ? WHERE plan_id = ?");
    $stmt->bind_param("sdii", $_POST['plan_name'], $_POST['price'], $_POST['duration'], $_POST['plan_id']);
    $stmt->execute();
    $msg = "Plan updated successfully!"; $msg_type = "success";
}

// ─── DELETE MEMBERSHIP PLAN ───────────────────────────────────────────────────
if (isset($_POST['delete_plan'])) {
    $stmt = $conn->prepare("DELETE FROM membership_plan WHERE plan_id = ?");
    $stmt->bind_param("i", $_POST['del_plan_id']);
    $stmt->execute();
    $msg = "Plan deleted successfully."; $msg_type = "success";
}

// ─── EXPORT CSV ───────────────────────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $csv_data = $conn->query("
        SELECT DATE_FORMAT(p.payment_date, '%Y-%m') AS month,
               COUNT(p.payment_id) AS total_transactions,
               SUM(p.amount) AS total_income
        FROM payment p WHERE p.status = 'paid'
        GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
        ORDER BY month DESC
    ");
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="income_report.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Month', 'Total Transactions', 'Total Income']);
    while ($row = $csv_data->fetch_assoc()) fputcsv($output, $row);
    fclose($output);
    exit();
}

// ─── FETCH STATS ──────────────────────────────────────────────────────────────
$total_members   = $conn->query("SELECT COUNT(*) as c FROM members m JOIN user_account u ON u.member_id = m.member_id WHERE u.role = 'member' AND u.is_active = 1")->fetch_assoc()['c'];
$active_members  = $conn->query("SELECT COUNT(DISTINCT member_id) as c FROM subscription WHERE status = 'active'")->fetch_assoc()['c'];
$expired_members = $conn->query("SELECT COUNT(DISTINCT member_id) as c FROM subscription WHERE status = 'expired' OR end_date < CURDATE()")->fetch_assoc()['c'];
$total_income    = $conn->query("SELECT SUM(amount) as total FROM payment WHERE status = 'paid'")->fetch_assoc()['total'] ?? 0;
$pending_count   = $conn->query("SELECT COUNT(*) as c FROM payment WHERE status = 'pending'")->fetch_assoc()['c'];
$deactivated_count = $conn->query("SELECT COUNT(*) as c FROM user_account WHERE is_active = 0 AND role = 'member'")->fetch_assoc()['c'];

// Monthly income
$monthly_income = $conn->query("
    SELECT DATE_FORMAT(p.payment_date, '%M %Y') AS month,
           COUNT(p.payment_id) AS total_transactions,
           SUM(p.amount) AS total_income
    FROM payment p WHERE p.status = 'paid'
    GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
    ORDER BY MIN(p.payment_date) DESC
    LIMIT 12
");

// Popular plans
$popular_plans = $conn->query("
    SELECT mp.plan_name, COUNT(s.subscription_id) as total_subscriptions
    FROM subscription s JOIN membership_plan mp ON mp.plan_id = s.plan_id
    GROUP BY s.plan_id ORDER BY total_subscriptions DESC
");

// All active members
$all_members = $conn->query("
    SELECT m.member_id, m.first_name, m.last_name, m.contact_number, m.gender,
           m.profile_photo, u.email, s.status as sub_status, s.end_date, mp.plan_name
    FROM members m
    JOIN user_account u ON u.member_id = m.member_id
    LEFT JOIN subscription s ON s.subscription_id = (
        SELECT subscription_id FROM subscription WHERE member_id = m.member_id ORDER BY created_at DESC LIMIT 1
    )
    LEFT JOIN membership_plan mp ON mp.plan_id = s.plan_id
    WHERE u.role = 'member' AND u.is_active = 1
    ORDER BY m.first_name
");

// All payments
$all_payments = $conn->query("
    SELECT p.payment_id, p.amount, p.payment_date, p.payment_method,
           p.status, p.transaction_reference,
           m.first_name, m.last_name, mp.plan_name
    FROM payment p
    JOIN subscription s ON s.subscription_id = p.subscription_id
    JOIN members m ON m.member_id = s.member_id
    JOIN membership_plan mp ON mp.plan_id = s.plan_id
    ORDER BY p.payment_date DESC
");

// All plans
$all_plans = $conn->query("SELECT * FROM membership_plan ORDER BY price");

// Announcements
$all_announcements = $conn->query("
    SELECT a.*, u.email FROM announcements a
    JOIN user_account u ON u.user_id = a.posted_by
    ORDER BY a.created_at DESC
");

// Promotions
$all_promotions = $conn->query("
    SELECT pr.*, mp.plan_name FROM promotions pr
    LEFT JOIN membership_plan mp ON mp.plan_id = pr.plan_id
    ORDER BY pr.start_date DESC
");

// Deactivated accounts
$deactivated_accounts = $conn->query("
    SELECT m.member_id, m.first_name, m.last_name, m.contact_number,
           u.email, u.deactivation_note, u.deactivated_at,
           staff.email AS deactivated_by_email
    FROM user_account u
    JOIN members m ON m.member_id = u.member_id
    LEFT JOIN user_account staff ON staff.user_id = u.deactivated_by
    WHERE u.is_active = 0 AND u.role = 'member'
    ORDER BY u.deactivated_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Owner Dashboard - Fitness Academy</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ── Search & Filter ── */
        .search-filter-bar { display:flex; gap:12px; margin-bottom:1.2rem; flex-wrap:wrap; }
        .search-filter-bar input[type="text"] { flex:1; min-width:200px; background:#1a1a1a; color:#fff; border:1px solid #2a2a2a; padding:10px 14px; border-radius:8px; font-size:14px; }
        .search-filter-bar input[type="text"]:focus { outline:none; border-color:#e8ff47; }
        .search-filter-bar select { background:#1a1a1a; color:#fff; border:1px solid #2a2a2a; padding:10px 14px; border-radius:8px; font-size:14px; cursor:pointer; }
        .search-filter-bar select:focus { outline:none; border-color:#e8ff47; }

        /* ── Reactivate button ── */
        .btn-reactivate { background:#1a3a1a; color:#4caf50; border:1px solid #4caf50; padding:5px 10px; border-radius:6px; font-size:12px; cursor:pointer; white-space:nowrap; }
        .btn-reactivate:hover { background:#4caf50; color:#fff; }

        /* ── Deactivated accounts tab ── */
        .deact-card { background:#1a1a1a; border:1px solid #2a2a2a; border-left:3px solid #ff4d4d; border-radius:10px; padding:1.2rem 1.5rem; margin-bottom:1rem; }
        .deact-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:0.8rem; flex-wrap:wrap; gap:10px; }
        .deact-name { font-size:16px; font-weight:700; color:#fff; margin-bottom:3px; }
        .deact-meta { font-size:12px; color:#555; }
        .deact-note { background:#141414; border:1px solid #2a2a2a; border-radius:8px; padding:10px 14px; font-size:13px; color:#aaa; margin-bottom:1rem; line-height:1.6; }
        .deact-note strong { color:#ff4d4d; display:block; font-size:11px; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; }
        .deact-footer { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .deact-by { font-size:12px; color:#555; flex:1; }
        .deact-by span { color:#888; }

        /* Hide for search */
        .member-row.hidden { display:none; }
        .payment-row.hidden { display:none; }
        .deact-card.hidden { display:none; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="navbar-logo">Fitness Academy</div>
    <div class="navbar-user">
        Owner Portal &nbsp;|&nbsp; <span><?php echo htmlspecialchars($_SESSION['email']); ?></span>
        <a href="attendance_report.php" style="color:#e8ff47; font-size:13px; text-decoration:none; margin-right:12px;">📊 Attendance Report</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<div class="container">

    <div class="page-title">Owner <span>Dashboard</span></div>

    <?php if ($msg !== ""): ?>
        <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
    <?php endif; ?>

    <!-- STAT CARDS -->
    <div class="grid-4">
        <div class="card">
            <div class="card-title">Total Members</div>
            <div class="card-value"><?php echo $total_members; ?></div>
        </div>
        <div class="card">
            <div class="card-title">Active Members</div>
            <div class="card-value" style="color:#4caf50;"><?php echo $active_members; ?></div>
        </div>
        <div class="card">
            <div class="card-title">Total Income</div>
            <div class="card-value" style="font-size:1.4rem;">₱<?php echo number_format($total_income, 2); ?></div>
        </div>
        <div class="card">
            <div class="card-title">Pending Payments</div>
            <div class="card-value" style="color:#e8ff47;"><?php echo $pending_count; ?></div>
        </div>
    </div>

    <!-- TABS -->
    <div class="tabs">
        <button class="tab active" onclick="switchTab('reports', this)">Reports</button>
        <button class="tab" onclick="switchTab('members', this)">Members</button>
        <button class="tab" onclick="switchTab('payments', this)">Payments</button>
        <button class="tab" onclick="switchTab('plans', this)">Membership Plans</button>
        <button class="tab" onclick="switchTab('announcements', this)">Announcements</button>
        <button class="tab" onclick="switchTab('promotions', this)">Promotions</button>
        <button class="tab" onclick="switchTab('deactivated', this)">
            Deactivated
            <?php if ($deactivated_count > 0): ?>
                <span style="background:#ff4d4d; color:#fff; border-radius:10px; padding:1px 7px; font-size:11px; margin-left:5px;"><?php echo $deactivated_count; ?></span>
            <?php endif; ?>
        </button>
    </div>

    <!-- TAB: REPORTS ──────────────────────────────────────────────────────── -->
    <div class="tab-content active" id="tab-reports">
        <div class="grid-2">
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <div class="section-title" style="margin-bottom:0;">Monthly Income</div>
                    <a href="?export_csv=1" class="btn-export">Export CSV</a>
                </div>
                <?php if ($monthly_income->num_rows > 0): ?>
                    <table>
                        <thead><tr><th>Month</th><th>Transactions</th><th>Income</th></tr></thead>
                        <tbody>
                            <?php while ($row = $monthly_income->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['month']; ?></td>
                                    <td><?php echo $row['total_transactions']; ?></td>
                                    <td style="color:#e8ff47;">₱<?php echo number_format($row['total_income'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color:#555; font-size:13px;">No income data yet.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="section-title">Most Popular Plans</div>
                <?php
                $max_query = $conn->query("SELECT COUNT(subscription_id) as cnt FROM subscription GROUP BY plan_id ORDER BY cnt DESC LIMIT 1");
                $max_row   = $max_query->fetch_assoc();
                $max_count = $max_row ? $max_row['cnt'] : 1;
                $popular_plans->data_seek(0);
                while ($plan = $popular_plans->fetch_assoc()):
                    $percent = $max_count > 0 ? ($plan['total_subscriptions'] / $max_count) * 100 : 0;
                ?>
                    <div class="bar-wrap">
                        <div class="bar-label">
                            <span><?php echo htmlspecialchars($plan['plan_name']); ?></span>
                            <span><?php echo $plan['total_subscriptions']; ?> subscriptions</span>
                        </div>
                        <div class="bar-track"><div class="bar-fill" style="width:<?php echo $percent; ?>%"></div></div>
                    </div>
                <?php endwhile; ?>

                <div style="margin-top:1.5rem;">
                    <div class="section-title">Active vs Expired</div>
                    <div style="display:flex; gap:1rem; margin-top:0.5rem;">
                        <div style="flex:1; background:#1a3a1a; border:1px solid #2a5a2a; border-radius:8px; padding:1rem; text-align:center;">
                            <div style="font-family:'Bebas Neue',sans-serif; font-size:2rem; color:#4caf50;"><?php echo $active_members; ?></div>
                            <div style="font-size:11px; color:#4caf50; text-transform:uppercase; letter-spacing:1px;">Active</div>
                        </div>
                        <div style="flex:1; background:#3a1a1a; border:1px solid #5a2a2a; border-radius:8px; padding:1rem; text-align:center;">
                            <div style="font-family:'Bebas Neue',sans-serif; font-size:2rem; color:#f44336;"><?php echo $expired_members; ?></div>
                            <div style="font-size:11px; color:#f44336; text-transform:uppercase; letter-spacing:1px;">Expired</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: MEMBERS ──────────────────────────────────────────────────────── -->
    <div class="tab-content" id="tab-members">
        <div class="card">
            <div class="section-title">All Members</div>
            <div class="search-filter-bar">
                <input type="text" id="member-search" placeholder="🔍  Search by name, email or contact..." oninput="filterMembers()">
                <select id="member-status-filter" onchange="filterMembers()">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="expired">Expired</option>
                    <option value="pending">Pending</option>
                    <option value="none">No Plan</option>
                </select>
            </div>
            <div class="scrollable">
                <?php if ($all_members->num_rows > 0): ?>
                    <table>
                        <thead><tr><th>Name</th><th>Email</th><th>Contact</th><th>Plan</th><th>Status</th><th>Expiry</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php while ($m = $all_members->fetch_assoc()):
                                $end_ts = $m['end_date'] ? strtotime($m['end_date']) : null;
                                $sub_status_lower = strtolower($m['sub_status'] ?? '');
                                if (!$m['plan_name']) {
                                    $ds = 'none'; $bc = 'badge-expired'; $sl = 'No Plan';
                                } elseif ($sub_status_lower === 'active' && $end_ts && $end_ts > time()) {
                                    $ds = 'active'; $bc = 'badge-active'; $sl = 'Active';
                                } elseif ($sub_status_lower === 'pending') {
                                    $ds = 'pending'; $bc = 'badge-pending'; $sl = 'Pending';
                                } else {
                                    $ds = 'expired'; $bc = 'badge-expired'; $sl = 'Expired';
                                }
                            ?>
                            <tr class="member-row"
                                data-name="<?php echo strtolower($m['first_name'] . ' ' . $m['last_name']); ?>"
                                data-email="<?php echo strtolower($m['email']); ?>"
                                data-contact="<?php echo $m['contact_number']; ?>"
                                data-status="<?php echo $ds; ?>">
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div style="width:36px; height:36px; border-radius:50%; overflow:hidden; border:1px solid #2a2a2a; background:#1a1a1a; flex-shrink:0; display:flex; align-items:center; justify-content:center;">
                                            <?php if ($m['profile_photo']): ?>
                                                <img src="uploads/profile_photos/<?php echo htmlspecialchars($m['profile_photo']); ?>"
                                                     class="member-photo"
                                                     data-src="uploads/profile_photos/<?php echo htmlspecialchars($m['profile_photo']); ?>"
                                                     data-name="<?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?>"
                                                     style="width:100%; height:100%; object-fit:cover;">
                                            <?php else: ?>
                                                <span style="font-size:1rem; color:#333;">👤</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($m['email']); ?></td>
                                <td><?php echo htmlspecialchars($m['contact_number']); ?></td>
                                <td><?php echo $m['plan_name'] ? htmlspecialchars($m['plan_name']) : '—'; ?></td>
                                <td><span class="badge <?php echo $bc; ?>"><?php echo $sl; ?></span></td>
                                <td><?php echo $m['end_date'] ? date('M d, Y', strtotime($m['end_date'])) : '—'; ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Delete this member permanently? This cannot be undone.');" style="display:inline;">
                                        <input type="hidden" name="del_member_id" value="<?php echo $m['member_id']; ?>">
                                        <button type="submit" name="delete_member" class="btn btn-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <p id="no-member-results" style="display:none; color:#555; font-size:13px; margin-top:10px;">No members match your search.</p>
                <?php else: ?>
                    <p style="color:#555; font-size:13px;">No members yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB: PAYMENTS ─────────────────────────────────────────────────────── -->
    <div class="tab-content" id="tab-payments">
        <div class="card">
            <div class="section-title">All Payments</div>
            <div class="search-filter-bar">
                <input type="text" id="payment-search" placeholder="🔍  Search by member name or plan..." oninput="filterPayments()">
                <select id="payment-status-filter" onchange="filterPayments()">
                    <option value="all">All Status</option>
                    <option value="paid">Paid</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="scrollable">
                <?php if ($all_payments->num_rows > 0): ?>
                    <table>
                        <thead><tr><th>Member</th><th>Plan</th><th>Amount</th><th>Method</th><th>Reference</th><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php while ($pay = $all_payments->fetch_assoc()): ?>
                            <tr class="payment-row"
                                data-name="<?php echo strtolower($pay['first_name'] . ' ' . $pay['last_name']); ?>"
                                data-plan="<?php echo strtolower($pay['plan_name']); ?>"
                                data-status="<?php echo strtolower($pay['status']); ?>">
                                <td><?php echo htmlspecialchars($pay['first_name'] . ' ' . $pay['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($pay['plan_name']); ?></td>
                                <td style="color:#e8ff47;">₱<?php echo number_format($pay['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($pay['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($pay['transaction_reference'] ?? '—'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($pay['payment_date'])); ?></td>
                                <td><span class="badge badge-<?php echo strtolower($pay['status']); ?>"><?php echo ucfirst($pay['status']); ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <p id="no-payment-results" style="display:none; color:#555; font-size:13px; margin-top:10px;">No payments match your search.</p>
                <?php else: ?>
                    <p style="color:#555; font-size:13px;">No payments yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB: MEMBERSHIP PLANS ─────────────────────────────────────────────── -->
    <div class="tab-content" id="tab-plans">
        <div class="grid-2">
            <div class="card">
                <div class="section-title">Add New Plan</div>
                <form method="POST">
                    <div class="form-group"><label>Plan Name</label><input type="text" name="plan_name" placeholder="e.g. Premium Monthly" required></div>
                    <div class="inline-grid">
                        <div class="form-group"><label>Price (₱)</label><input type="number" name="price" step="0.01" placeholder="e.g. 1000" required></div>
                        <div class="form-group"><label>Duration (days)</label><input type="number" name="duration" placeholder="e.g. 30" required></div>
                    </div>
                    <button type="submit" name="add_plan" class="submit-btn">Add Plan</button>
                </form>
            </div>
            <div class="card">
                <div class="section-title">Existing Plans</div>
                <?php $all_plans->data_seek(0); while ($pl = $all_plans->fetch_assoc()): ?>
                    <div class="plan-card">
                        <div class="plan-card-header">
                            <div>
                                <div class="plan-name"><?php echo htmlspecialchars($pl['plan_name']); ?></div>
                                <div class="plan-duration"><?php echo $pl['duration']; ?> days</div>
                            </div>
                            <div class="plan-price">₱<?php echo number_format($pl['price'], 2); ?></div>
                        </div>
                        <div class="plan-actions">
                            <button class="btn btn-edit" onclick="toggleEdit(<?php echo $pl['plan_id']; ?>)">Edit</button>
                            <form method="POST" onsubmit="return confirm('Delete this plan?');" style="display:inline;">
                                <input type="hidden" name="del_plan_id" value="<?php echo $pl['plan_id']; ?>">
                                <button type="submit" name="delete_plan" class="btn btn-delete">Delete</button>
                            </form>
                        </div>
                        <div class="edit-form" id="edit-<?php echo $pl['plan_id']; ?>">
                            <form method="POST">
                                <input type="hidden" name="plan_id" value="<?php echo $pl['plan_id']; ?>">
                                <div class="form-group"><label>Plan Name</label><input type="text" name="plan_name" value="<?php echo htmlspecialchars($pl['plan_name']); ?>" required></div>
                                <div class="inline-grid">
                                    <div class="form-group"><label>Price (₱)</label><input type="number" name="price" step="0.01" value="<?php echo $pl['price']; ?>" required></div>
                                    <div class="form-group"><label>Duration (days)</label><input type="number" name="duration" value="<?php echo $pl['duration']; ?>" required></div>
                                </div>
                                <button type="submit" name="update_plan" class="submit-btn">Save Changes</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- TAB: ANNOUNCEMENTS ────────────────────────────────────────────────── -->
    <div class="tab-content" id="tab-announcements">
        <div class="card">
            <div class="section-title">All Announcements</div>
            <?php if ($all_announcements->num_rows > 0): ?>
                <table>
                    <thead><tr><th>Title</th><th>Message</th><th>Posted By</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php while ($ann = $all_announcements->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ann['title']); ?></td>
                                <td style="max-width:300px;"><?php echo htmlspecialchars($ann['message']); ?></td>
                                <td><?php echo htmlspecialchars($ann['email']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($ann['created_at'])); ?></td>
                                <td><span class="badge <?php echo $ann['is_active'] ? 'badge-active' : 'badge-expired'; ?>"><?php echo $ann['is_active'] ? 'Active' : 'Hidden'; ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color:#555; font-size:13px;">No announcements yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: PROMOTIONS ───────────────────────────────────────────────────── -->
    <div class="tab-content" id="tab-promotions">
        <div class="card">
            <div class="section-title">All Promotions</div>
            <?php if ($all_promotions->num_rows > 0): ?>
                <table>
                    <thead><tr><th>Promo Name</th><th>Description</th><th>Discount</th><th>Plan</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php while ($promo = $all_promotions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($promo['promo_name']); ?></td>
                                <td style="max-width:200px;"><?php echo htmlspecialchars($promo['description'] ?? '—'); ?></td>
                                <td style="color:#e8ff47;"><?php echo $promo['discount_percent']; ?>%</td>
                                <td><?php echo $promo['plan_name'] ?? 'All Plans'; ?></td>
                                <td><?php echo date('M d, Y', strtotime($promo['start_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($promo['end_date'])); ?></td>
                                <td><span class="badge <?php echo $promo['is_active'] ? 'badge-active' : 'badge-expired'; ?>"><?php echo $promo['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color:#555; font-size:13px;">No promotions yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: DEACTIVATED ACCOUNTS ─────────────────────────────────────────── -->
    <div class="tab-content" id="tab-deactivated">
        <div class="card">
            <div class="section-title">Deactivated Accounts</div>

            <?php if ($deactivated_accounts->num_rows > 0): ?>

                <!-- Search deactivated -->
                <div class="search-filter-bar" style="margin-bottom:1.5rem;">
                    <input type="text" id="deact-search" placeholder="🔍  Search by name or email..." oninput="filterDeactivated()">
                </div>

                <div id="deact-list">
                <?php while ($da = $deactivated_accounts->fetch_assoc()): ?>
                    <div class="deact-card"
                         data-name="<?php echo strtolower($da['first_name'] . ' ' . $da['last_name']); ?>"
                         data-email="<?php echo strtolower($da['email']); ?>">

                        <div class="deact-header">
                            <div>
                                <div class="deact-name"><?php echo htmlspecialchars($da['first_name'] . ' ' . $da['last_name']); ?></div>
                                <div class="deact-meta"><?php echo htmlspecialchars($da['email']); ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($da['contact_number']); ?></div>
                            </div>
                            <span class="badge badge-expired">Deactivated</span>
                        </div>

                        <div class="deact-note">
                            <strong>📋 Staff Note</strong>
                            <?php echo htmlspecialchars($da['deactivation_note'] ?? 'No note provided.'); ?>
                        </div>

                        <div class="deact-footer">
                            <div class="deact-by">
                                Deactivated by <span><?php echo htmlspecialchars($da['deactivated_by_email'] ?? 'Unknown'); ?></span>
                                <?php if ($da['deactivated_at']): ?>
                                    on <span><?php echo date('M d, Y h:i A', strtotime($da['deactivated_at'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Reactivate this account?');">
                                <input type="hidden" name="react_member_id" value="<?php echo $da['member_id']; ?>">
                                <button type="submit" name="reactivate_member" class="btn-reactivate">✅ Reactivate</button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete this member? This cannot be undone.');">
                                <input type="hidden" name="del_member_id" value="<?php echo $da['member_id']; ?>">
                                <button type="submit" name="delete_member" class="btn btn-delete">🗑 Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
                </div>
                <p id="no-deact-results" style="display:none; color:#555; font-size:13px; margin-top:10px;">No deactivated accounts match your search.</p>

            <?php else: ?>
                <div style="text-align:center; padding:2rem 0;">
                    <div style="font-size:2.5rem; margin-bottom:0.5rem;">✅</div>
                    <p style="color:#4caf50; font-size:14px; font-weight:600;">No Deactivated Accounts</p>
                    <p style="color:#555; font-size:13px; margin-top:4px;">All member accounts are currently active.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + name).classList.add('active');
}

function toggleEdit(planId) {
    document.getElementById('edit-' + planId).classList.toggle('active');
}

// Members search + filter
function filterMembers() {
    const search = document.getElementById('member-search').value.toLowerCase();
    const filter = document.getElementById('member-status-filter').value;
    const rows   = document.querySelectorAll('.member-row');
    let visible  = 0;
    rows.forEach(row => {
        const matchSearch = row.dataset.name.includes(search) || row.dataset.email.includes(search) || row.dataset.contact.includes(search);
        const matchFilter = filter === 'all' || row.dataset.status === filter;
        if (matchSearch && matchFilter) { row.classList.remove('hidden'); visible++; }
        else row.classList.add('hidden');
    });
    document.getElementById('no-member-results').style.display = visible === 0 ? 'block' : 'none';
}

// Payments search + filter
function filterPayments() {
    const search = document.getElementById('payment-search').value.toLowerCase();
    const filter = document.getElementById('payment-status-filter').value;
    const rows   = document.querySelectorAll('.payment-row');
    let visible  = 0;
    rows.forEach(row => {
        const matchSearch = row.dataset.name.includes(search) || row.dataset.plan.includes(search);
        const matchFilter = filter === 'all' || row.dataset.status === filter;
        if (matchSearch && matchFilter) { row.classList.remove('hidden'); visible++; }
        else row.classList.add('hidden');
    });
    document.getElementById('no-payment-results').style.display = visible === 0 ? 'block' : 'none';
}

// Deactivated search
function filterDeactivated() {
    const search = document.getElementById('deact-search').value.toLowerCase();
    const cards  = document.querySelectorAll('.deact-card');
    let visible  = 0;
    cards.forEach(card => {
        const match = card.dataset.name.includes(search) || card.dataset.email.includes(search);
        if (match) { card.classList.remove('hidden'); visible++; }
        else card.classList.add('hidden');
    });
    document.getElementById('no-deact-results').style.display = visible === 0 ? 'block' : 'none';
}
</script>

<script src="app.js"></script>
</body>
</html>
