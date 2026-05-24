<?php include "auth_check.php"; ?>
<?php include "config.php"; ?>

<?php
// Get member info
$member_id = $_SESSION['member_id'];

$member_query = $conn->prepare("
    SELECT m.first_name, m.last_name, m.contact_number, m.gender,
           u.email
    FROM members m
    JOIN user_account u ON u.member_id = m.member_id
    WHERE m.member_id = ?
");
$member_query->bind_param("i", $member_id);
$member_query->execute();
$member = $member_query->get_result()->fetch_assoc();

// Get active or latest subscription
$sub_query = $conn->prepare("
    SELECT s.subscription_id, s.start_date, s.end_date, s.status,
           p.plan_name, p.price, p.duration
    FROM subscription s
    JOIN membership_plan p ON p.plan_id = s.plan_id
    WHERE s.member_id = ?
    ORDER BY s.created_at DESC
    LIMIT 1
");
$sub_query->bind_param("i", $member_id);
$sub_query->execute();
$subscription = $sub_query->get_result()->fetch_assoc();

// Get attendance history (last 5)
$attend_query = $conn->prepare("
    SELECT date, time_in, time_out
    FROM attendance
    WHERE member_id = ?
    ORDER BY date DESC, time_in DESC
    LIMIT 5
");
$attend_query->bind_param("i", $member_id);
$attend_query->execute();
$attendance = $attend_query->get_result();

// Get all available plans with active promo discount applied
$plans_query = $conn->query("
    SELECT mp.*,
           pr.discount_percent,
           pr.promo_name,
           ROUND(mp.price - (mp.price * pr.discount_percent / 100), 2) AS discounted_price
    FROM membership_plan mp
    LEFT JOIN promotions pr ON (pr.plan_id = mp.plan_id OR pr.plan_id IS NULL)
        AND pr.is_active = 1
        AND pr.start_date <= CURDATE()
        AND pr.end_date >= CURDATE()
    GROUP BY mp.plan_id
");

// Determine if member has an active (non-expired) membership
$has_active_membership = $subscription && strtolower($subscription['status']) === 'active' && strtotime($subscription['end_date']) > time();

// Handle renewal form submission
$renew_msg = "";
$renew_msg_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['renew'])) {
    $plan_id = $_POST['plan_id'];
    $payment_method = $_POST['payment_method'];
    $transaction_reference = $_POST['transaction_reference'] ?? 'CASH-PENDING';

    // Get plan with promo discount
    $plan_info = $conn->prepare("
        SELECT mp.*,
               pr.discount_percent,
               ROUND(mp.price - (mp.price * pr.discount_percent / 100), 2) AS discounted_price
        FROM membership_plan mp
        LEFT JOIN promotions pr ON (pr.plan_id = mp.plan_id OR pr.plan_id IS NULL)
            AND pr.is_active = 1
            AND pr.start_date <= CURDATE()
            AND pr.end_date >= CURDATE()
        WHERE mp.plan_id = ?
        GROUP BY mp.plan_id
    ");
    $plan_info->bind_param("i", $plan_id);
    $plan_info->execute();
    $plan = $plan_info->get_result()->fetch_assoc();

    // Use discounted price if promo exists, otherwise use regular price
    $final_price = $plan['discount_percent'] ? $plan['discounted_price'] : $plan['price'];

    // START DATE LOGIC
    // If member still has active days left → auto-start day after current plan expires
    // If expired or new → use the date they picked (clamped within today to today+30)
    if ($has_active_membership) {
        $start_date = date('Y-m-d', strtotime($subscription['end_date'] . ' +1 day'));
    } else {
        $chosen_start = $_POST['start_date'] ?? date('Y-m-d');
        $min_date = date('Y-m-d');
        $max_date = date('Y-m-d', strtotime('+30 days'));

        // Clamp within allowed range for safety
        if ($chosen_start < $min_date) $chosen_start = $min_date;
        if ($chosen_start > $max_date) $chosen_start = $max_date;

        $start_date = $chosen_start;
    }

    $end_date = date('Y-m-d', strtotime($start_date . " +{$plan['duration']} days"));

    // Insert new subscription
    $new_sub = $conn->prepare("
        INSERT INTO subscription (plan_id, member_id, start_date, end_date, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    $new_sub->bind_param("iiss", $plan_id, $member_id, $start_date, $end_date);

    if ($new_sub->execute()) {
        $new_sub_id = $conn->insert_id;

        // Insert payment record with final price
        $payment = $conn->prepare("
            INSERT INTO payment (subscription_id, amount, payment_date, payment_method, status, transaction_reference)
            VALUES (?, ?, NOW(), ?, 'pending', ?)
        ");
        $payment->bind_param("idss", $new_sub_id, $final_price, $payment_method, $transaction_reference);

        if ($payment->execute()) {
            // Build a friendly start date message
            if ($has_active_membership) {
                $start_label = "Your new plan will start on " . date('M d, Y', strtotime($start_date)) . " (day after your current plan expires).";
            } else {
                $start_label = "Your membership is scheduled to start on " . date('M d, Y', strtotime($start_date)) . ".";
            }
            $renew_msg = "Renewal request submitted! Please wait for staff confirmation. " . $start_label;
            $renew_msg_type = "success";
        } else {
            $renew_msg = "Error submitting payment. Please try again.";
            $renew_msg_type = "error";
        }
    } else {
        $renew_msg = "Error submitting renewal. Please try again.";
        $renew_msg_type = "error";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Member Dashboard - Fitness Academy</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Date picker styling to match dark theme */
        input[type="date"] {
            background-color: #1a1a1a;
            color: #fff;
            border: 1px solid #2a2a2a;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
            cursor: pointer;
        }
        input[type="date"]:focus {
            outline: none;
            border-color: #e8ff47;
        }
        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }
        .start-date-note {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }
        .start-date-note span {
            color: #e8ff47;
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="navbar-logo">Fitness Academy</div>
    <div class="navbar-user">
        Welcome, <span><?php echo htmlspecialchars($member['first_name']); ?></span>
        <a href="profile.php" style="color:#e8ff47; font-size:13px; text-decoration:none;">👤 My Profile</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<div class="container">

    <div class="page-title">Member <span>Dashboard</span></div>

    <?php if ($renew_msg !== ""): ?>
        <div class="alert alert-<?php echo $renew_msg_type; ?>">
            <?php echo $renew_msg; ?>
        </div>
    <?php endif; ?>

    <?php
    if ($subscription) {
        $days_left = (strtotime($subscription['end_date']) - time()) / 86400;
        if ($days_left <= 7 && $days_left > 0) {
            echo "<div class='expiry-warning'>⚠ Your membership expires in " . ceil($days_left) . " day(s). Consider renewing soon!</div>";
        } elseif ($days_left <= 0) {
            echo "<div class='alert alert-error'>Your membership has expired. Please renew to continue accessing the gym.</div>";
        }
    }
    ?>

    <?php
    $active_ann = $conn->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC");
    if ($active_ann->num_rows > 0):
        while ($ann = $active_ann->fetch_assoc()):
    ?>
        <div class="expiry-warning" style="margin-bottom: 1rem;">
            <strong><?php echo htmlspecialchars($ann['title']); ?></strong><br>
            <span style="font-size: 12px;"><?php echo htmlspecialchars($ann['message']); ?></span>
        </div>
    <?php
        endwhile;
    endif;
    ?>

    <!-- STAT CARDS -->
    <div class="grid-3">
        <div class="card">
            <div class="card-title">Membership Status</div>
            <?php if ($subscription): ?>
                <div style="margin-top: 6px;">
                    <span class="badge badge-<?php echo $subscription['status']; ?>">
                        <?php echo ucfirst($subscription['status']); ?>
                    </span>
                </div>
            <?php else: ?>
                <div style="margin-top: 6px;"><span class="badge badge-expired">No Plan</span></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-title">Current Plan</div>
            <div class="card-value"><?php echo $subscription ? htmlspecialchars($subscription['plan_name']) : '—'; ?></div>
            <div class="card-sub"><?php echo $subscription ? '₱' . number_format($subscription['price'], 2) : 'No active plan'; ?></div>
        </div>

        <div class="card">
            <div class="card-title">Expiry Date</div>
            <div class="card-value" style="font-size: 1.3rem;">
                <?php echo $subscription ? date('M d, Y', strtotime($subscription['end_date'])) : '—'; ?>
            </div>
            <div class="card-sub">
                <?php
                if ($subscription) {
                    $days_left = ceil((strtotime($subscription['end_date']) - time()) / 86400);
                    echo $days_left > 0 ? $days_left . ' days remaining' : 'Expired';
                }
                ?>
            </div>
        </div>
    </div>

    <div class="grid-2">

        <!-- ATTENDANCE HISTORY -->
        <div class="card">
            <div class="section-title">Recent Attendance</div>
            <?php if ($attendance->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $attendance->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($row['time_in'])); ?></td>
                                <td><?php echo $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '—'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #555; font-size: 13px;">No attendance records yet.</p>
            <?php endif; ?>
        </div>

        <!-- RENEWAL FORM -->
        <div class="card">
            <div class="section-title">Renew Membership</div>

            <?php
            $can_renew = false;

            if (!$subscription) {
                $can_renew = true; // New member, no plan yet
            } else {
                $days_left = ceil((strtotime($subscription['end_date']) - time()) / 86400);
                if ($days_left <= 7) {
                    $can_renew = true; // Expired or expiring soon
                }
            }

            // Block if already has a pending renewal
            $pending_check = $conn->prepare("
                SELECT COUNT(*) as count
                FROM subscription s
                JOIN payment p ON p.subscription_id = s.subscription_id
                WHERE s.member_id = ?
                AND s.status = 'pending'
                AND p.status = 'pending'
            ");
            $pending_check->bind_param("i", $member_id);
            $pending_check->execute();
            $pending_result = $pending_check->get_result()->fetch_assoc();

            if ($pending_result['count'] > 0) {
                $can_renew = false;
                $block_reason = "pending";
            }

            // Date picker values for expired/new members
            $today        = date('Y-m-d');
            $max_date     = date('Y-m-d', strtotime('+30 days'));
            ?>

            <?php if ($can_renew): ?>
                <form method="POST" action="">

                    <div class="form-group">
                        <label>Select Plan</label>
                        <select name="plan_id" required>
                            <option value="">-- Choose a Plan --</option>
                            <?php
                            $plans_query->data_seek(0);
                            while ($plan = $plans_query->fetch_assoc()):
                            ?>
                                <option value="<?php echo $plan['plan_id']; ?>">
                                    <?php echo htmlspecialchars($plan['plan_name']); ?> —
                                    <?php if ($plan['discount_percent']): ?>
                                        ₱<?php echo number_format($plan['discounted_price'], 2); ?>
                                        (<?php echo $plan['discount_percent']; ?>% off — <?php echo htmlspecialchars($plan['promo_name']); ?>) /
                                    <?php else: ?>
                                        ₱<?php echo number_format($plan['price'], 2); ?> /
                                    <?php endif; ?>
                                    <?php echo $plan['duration']; ?> days
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <?php if (!$has_active_membership): ?>
                    <!-- DATE PICKER: only shown for new/expired members -->
                    <div class="form-group">
                        <label>Membership Start Date</label>
                        <input
                            type="date"
                            name="start_date"
                            id="start_date"
                            min="<?php echo $today; ?>"
                            max="<?php echo $max_date; ?>"
                            value="<?php echo $today; ?>"
                            required
                            onchange="updateStartNote(this.value)"
                        >
                        <p class="start-date-note" id="start-note">
                            Your membership will start on <span id="start-label"><?php echo date('M d, Y'); ?></span>.
                            You can schedule up to <span>30 days</span> from today.
                        </p>
                    </div>
                    <?php else: ?>
                    <!-- Auto-start notice for members with active plan -->
                    <div class="form-group">
                        <p class="start-date-note" style="font-size: 13px; color: #aaa;">
                            ℹ️ Your new plan will automatically start on
                            <span style="color: #e8ff47; font-weight: 600;">
                                <?php echo date('M d, Y', strtotime($subscription['end_date'] . ' +1 day')); ?>
                            </span>
                            — the day after your current plan expires. No days will be wasted.
                        </p>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method" id="payment_method" onchange="toggleRef(this.value)" required>
                            <option value="">-- Choose Payment --</option>
                            <option value="GCash">GCash</option>
                            <option value="Maya">Maya</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Cash">Cash (Pay at Counter)</option>
                        </select>
                    </div>

                    <div class="form-group ref-group" id="ref-group">
                        <label>Reference Number</label>
                        <input type="text" name="transaction_reference" id="transaction_reference" placeholder="Enter reference number">
                    </div>

                    <button type="submit" name="renew" class="renew-btn">Submit Renewal Request</button>
                </form>

            <?php elseif (isset($block_reason) && $block_reason === 'pending'): ?>
                <div style="text-align: center; padding: 1.5rem 0;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">⏳</div>
                    <p style="color: #e8ff47; font-size: 14px; font-weight: 500;">Renewal Pending</p>
                    <p style="color: #555; font-size: 13px; margin-top: 6px;">
                        You already have a renewal request waiting for staff confirmation.
                    </p>
                </div>

            <?php else: ?>
                <div style="text-align: center; padding: 1.5rem 0;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">✅</div>
                    <p style="color: #4caf50; font-size: 14px; font-weight: 500;">Membership Active</p>
                    <p style="color: #555; font-size: 13px; margin-top: 6px;">
                        Your membership is still active until
                        <strong style="color: #fff;">
                            <?php echo date('M d, Y', strtotime($subscription['end_date'])); ?>
                        </strong>.
                        Renewal will be available 7 days before expiry.
                    </p>
                </div>

            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function toggleRef(value) {
    const refGroup = document.getElementById('ref-group');
    const refInput = document.getElementById('transaction_reference');
    if (value === 'GCash' || value === 'Maya' || value === 'Credit Card') {
        refGroup.style.display = 'block';
        refInput.required = true;
    } else {
        refGroup.style.display = 'none';
        refInput.required = false;
        refInput.value = '';
    }
}

function updateStartNote(value) {
    const label = document.getElementById('start-label');
    if (!label || !value) return;
    // Format date nicely: YYYY-MM-DD → Mon DD, YYYY
    const [y, m, d] = value.split('-');
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    label.textContent = months[parseInt(m) - 1] + ' ' + parseInt(d) + ', ' + y;
}
</script>

<script src="app.js"></script>
</body>
</html>
