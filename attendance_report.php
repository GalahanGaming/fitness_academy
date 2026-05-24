<?php include "auth_check.php"; ?>
<?php include "config.php"; ?>

<?php
// Only staff and owner/manager can access
if (!in_array($_SESSION['role'], ['staff', 'owner', 'manager'])) {
    header("Location: index.php");
    exit();
}

// ─── EXPORT CSV ───────────────────────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to   = $_GET['date_to']   ?? date('Y-m-d');

    $stmt = $conn->prepare("
        SELECT m.member_id, m.first_name, m.last_name,
               a.date, a.time_in, a.time_out
        FROM attendance a
        JOIN members m ON m.member_id = a.member_id
        WHERE a.date BETWEEN ? AND ?
        ORDER BY a.date DESC, a.time_in ASC
    ");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $rows = $stmt->get_result();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . $date_from . '_to_' . $date_to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Member ID', 'Name', 'Date', 'Time In', 'Time Out']);
    while ($r = $rows->fetch_assoc()) {
        fputcsv($out, [
            '#' . $r['member_id'],
            $r['first_name'] . ' ' . $r['last_name'],
            date('M d, Y', strtotime($r['date'])),
            date('h:i A', strtotime($r['time_in'])),
            $r['time_out'] ? date('h:i A', strtotime($r['time_out'])) : '—',
        ]);
    }
    fclose($out);
    exit();
}

// ─── FILTERS ──────────────────────────────────────────────────────────────────
$filter_type = $_GET['filter_type'] ?? 'today';
$date_from   = $_GET['date_from']   ?? date('Y-m-d');
$date_to     = $_GET['date_to']     ?? date('Y-m-d');
$month       = $_GET['month']       ?? date('Y-m');

// Resolve date range based on filter type
if ($filter_type === 'today') {
    $date_from = $date_to = date('Y-m-d');
} elseif ($filter_type === 'this_week') {
    $date_from = date('Y-m-d', strtotime('monday this week'));
    $date_to   = date('Y-m-d');
} elseif ($filter_type === 'this_month') {
    $date_from = date('Y-m-01');
    $date_to   = date('Y-m-d');
} elseif ($filter_type === 'month_pick') {
    $date_from = $month . '-01';
    $date_to   = date('Y-m-t', strtotime($date_from));
} elseif ($filter_type === 'custom') {
    // use date_from and date_to from GET as-is
}

// ─── ATTENDANCE LOG ───────────────────────────────────────────────────────────
$log_stmt = $conn->prepare("
    SELECT a.attendance_id, a.date, a.time_in, a.time_out,
           m.member_id, m.first_name, m.last_name
    FROM attendance a
    JOIN members m ON m.member_id = a.member_id
    WHERE a.date BETWEEN ? AND ?
    ORDER BY a.date DESC, a.time_in DESC
");
$log_stmt->bind_param("ss", $date_from, $date_to);
$log_stmt->execute();
$log = $log_stmt->get_result();

// ─── VISIT SUMMARY (per member for the period) ────────────────────────────────
$summary_stmt = $conn->prepare("
    SELECT m.member_id, m.first_name, m.last_name,
           COUNT(a.attendance_id) AS visit_count,
           MAX(a.date) AS last_visit
    FROM members m
    JOIN user_account u ON u.member_id = m.member_id
    LEFT JOIN attendance a ON a.member_id = m.member_id AND a.date BETWEEN ? AND ?
    WHERE u.role = 'member'
    GROUP BY m.member_id
    ORDER BY visit_count DESC, m.first_name ASC
");
$summary_stmt->bind_param("ss", $date_from, $date_to);
$summary_stmt->execute();
$summary = $summary_stmt->get_result();

// ─── TOTAL STATS ──────────────────────────────────────────────────────────────
$total_visits_stmt = $conn->prepare("SELECT COUNT(*) as c FROM attendance WHERE date BETWEEN ? AND ?");
$total_visits_stmt->bind_param("ss", $date_from, $date_to);
$total_visits_stmt->execute();
$total_visits = $total_visits_stmt->get_result()->fetch_assoc()['c'];

$unique_members_stmt = $conn->prepare("SELECT COUNT(DISTINCT member_id) as c FROM attendance WHERE date BETWEEN ? AND ?");
$unique_members_stmt->bind_param("ss", $date_from, $date_to);
$unique_members_stmt->execute();
$unique_members = $unique_members_stmt->get_result()->fetch_assoc()['c'];

$unique_days_stmt = $conn->prepare("SELECT COUNT(DISTINCT date) as c FROM attendance WHERE date BETWEEN ? AND ?");
$unique_days_stmt->bind_param("ss", $date_from, $date_to);
$unique_days_stmt->execute();
$unique_days = $unique_days_stmt->get_result()->fetch_assoc()['c'];

// Period label
$period_labels = [
    'today'      => 'Today — ' . date('F d, Y'),
    'this_week'  => 'This Week (' . date('M d', strtotime($date_from)) . ' – ' . date('M d, Y') . ')',
    'this_month' => 'This Month — ' . date('F Y'),
    'month_pick' => date('F Y', strtotime($date_from)),
    'custom'     => date('M d, Y', strtotime($date_from)) . ' – ' . date('M d, Y', strtotime($date_to)),
];
$period_label = $period_labels[$filter_type] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Report - Fitness Academy</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ── Filter bar ── */
        .filter-bar {
            background: #141414;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 11px; color: #555; text-transform: uppercase; letter-spacing: 1px; }
        .filter-group select,
        .filter-group input[type="date"],
        .filter-group input[type="month"] {
            background: #1a1a1a;
            color: #fff;
            border: 1px solid #2a2a2a;
            padding: 9px 14px;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            cursor: pointer;
        }
        .filter-group select:focus,
        .filter-group input:focus { border-color: #e8ff47; }
        .filter-group input[type="date"]::-webkit-calendar-picker-indicator,
        .filter-group input[type="month"]::-webkit-calendar-picker-indicator { filter: invert(1); cursor: pointer; }

        .btn-filter {
            background: #e8ff47;
            color: #000;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            align-self: flex-end;
        }
        .btn-filter:hover { opacity: 0.88; }

        .custom-range { display: none; gap: 10px; flex-wrap: wrap; }
        .custom-range.visible { display: flex; }
        .month-pick { display: none; }
        .month-pick.visible { display: flex; }

        /* ── Period heading ── */
        .period-heading {
            font-size: 13px;
            color: #e8ff47;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        /* ── Stat cards row ── */
        .stat-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .stat-mini {
            flex: 1;
            min-width: 120px;
            background: #141414;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            padding: 1rem 1.2rem;
            text-align: center;
        }
        .stat-mini .num { font-size: 2rem; font-weight: 800; color: #e8ff47; line-height: 1; }
        .stat-mini .lbl { font-size: 11px; color: #555; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }

        /* ── Visit summary ── */
        .visit-bar-wrap { margin-bottom: 10px; }
        .visit-bar-label { display: flex; justify-content: space-between; font-size: 13px; color: #888; margin-bottom: 4px; }
        .visit-bar-label .vname { color: #fff; }
        .visit-bar-label .vcount { color: #e8ff47; font-weight: 700; }
        .visit-bar-track { background: #1a1a1a; border-radius: 4px; height: 6px; overflow: hidden; }
        .visit-bar-fill  { background: #e8ff47; height: 100%; border-radius: 4px; transition: width 0.6s ease; }
        .zero-visits { color: #333; }

        /* ── Back link ── */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #888;
            font-size: 13px;
            text-decoration: none;
            margin-bottom: 1.2rem;
            transition: color 0.2s;
        }
        .back-link:hover { color: #e8ff47; }

        /* ── Export btn ── */
        .export-link {
            display: inline-block;
            background: #1a1a1a;
            color: #e8ff47;
            border: 1px solid #e8ff47;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .export-link:hover { background: #e8ff47; color: #000; }

        /* ── Search ── */
        .log-search {
            background: #1a1a1a;
            color: #fff;
            border: 1px solid #2a2a2a;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 1rem;
            outline: none;
        }
        .log-search:focus { border-color: #e8ff47; }
        .log-row.hidden { display: none; }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 10px;
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="navbar-logo">Fitness Academy</div>
    <div class="navbar-user">
        <?php echo ucfirst($_SESSION['role']); ?> Portal &nbsp;|&nbsp;
        <span><?php echo htmlspecialchars($_SESSION['email']); ?></span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<div class="container">

    <!-- Back link -->
    <?php
    $back_url = in_array($_SESSION['role'], ['owner', 'manager']) ? 'owner_dashboard.php' : 'staff_dashboard.php';
    ?>
    <a href="<?php echo $back_url; ?>" class="back-link">← Back to Dashboard</a>

    <div class="page-title">Attendance <span>Report</span></div>

    <!-- FILTER BAR -->
    <form method="GET" id="filter-form">
        <div class="filter-bar">
            <div class="filter-group">
                <label>View</label>
                <select name="filter_type" id="filter-type" onchange="toggleDateInputs(this.value)">
                    <option value="today"      <?php echo $filter_type === 'today'      ? 'selected' : ''; ?>>Today</option>
                    <option value="this_week"  <?php echo $filter_type === 'this_week'  ? 'selected' : ''; ?>>This Week</option>
                    <option value="this_month" <?php echo $filter_type === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                    <option value="month_pick" <?php echo $filter_type === 'month_pick' ? 'selected' : ''; ?>>Pick a Month</option>
                    <option value="custom"     <?php echo $filter_type === 'custom'     ? 'selected' : ''; ?>>Custom Range</option>
                </select>
            </div>

            <!-- Month picker -->
            <div class="filter-group month-pick <?php echo $filter_type === 'month_pick' ? 'visible' : ''; ?>" id="month-pick-wrap">
                <label>Month</label>
                <input type="month" name="month" value="<?php echo $month; ?>">
            </div>

            <!-- Custom range -->
            <div class="custom-range <?php echo $filter_type === 'custom' ? 'visible' : ''; ?>" id="custom-range-wrap">
                <div class="filter-group">
                    <label>From</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="filter-group">
                    <label>To</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                </div>
            </div>

            <button type="submit" class="btn-filter">Apply</button>

            <!-- Export CSV -->
            <a class="export-link" href="?<?php echo http_build_query(array_merge($_GET, ['export_csv' => 1])); ?>">
                ⬇ Export CSV
            </a>
        </div>
    </form>

    <!-- PERIOD LABEL + STATS -->
    <div class="period-heading">📅 <?php echo $period_label; ?></div>

    <div class="stat-row">
        <div class="stat-mini">
            <div class="num"><?php echo $total_visits; ?></div>
            <div class="lbl">Total Visits</div>
        </div>
        <div class="stat-mini">
            <div class="num"><?php echo $unique_members; ?></div>
            <div class="lbl">Unique Members</div>
        </div>
        <div class="stat-mini">
            <div class="num"><?php echo $unique_days; ?></div>
            <div class="lbl">Days with Visits</div>
        </div>
    </div>

    <div class="grid-2">

        <!-- VISIT SUMMARY (rankings) -->
        <div class="card">
            <div class="section-title">Member Visit Rankings</div>
            <p style="color:#666; font-size:13px; margin-bottom:1.2rem;">
                All members ranked by visits for this period — great for giveaway decisions!
            </p>
            <?php
            $summary_rows = [];
            while ($sr = $summary->fetch_assoc()) $summary_rows[] = $sr;
            $max_visits = !empty($summary_rows) ? ($summary_rows[0]['visit_count'] > 0 ? $summary_rows[0]['visit_count'] : 1) : 1;
            $medals = ['🥇', '🥈', '🥉'];
            $rank = 0;
            foreach ($summary_rows as $sr):
                $pct = ($sr['visit_count'] / $max_visits) * 100;
                $rank++;
            ?>
                <div class="visit-bar-wrap <?php echo $sr['visit_count'] == 0 ? 'zero-visits' : ''; ?>">
                    <div class="visit-bar-label">
                        <span class="vname">
                            <?php echo isset($medals[$rank - 1]) && $sr['visit_count'] > 0 ? $medals[$rank - 1] . ' ' : ($rank . '. '); ?>
                            <?php echo htmlspecialchars($sr['first_name'] . ' ' . $sr['last_name']); ?>
                            <?php if ($sr['visit_count'] > 0): ?>
                                <span style="color:#555; font-size:11px;">
                                    — last visit <?php echo date('M d', strtotime($sr['last_visit'])); ?>
                                </span>
                            <?php endif; ?>
                        </span>
                        <span class="vcount"><?php echo $sr['visit_count']; ?> visit<?php echo $sr['visit_count'] !== '1' ? 's' : ''; ?></span>
                    </div>
                    <div class="visit-bar-track">
                        <div class="visit-bar-fill" style="width:<?php echo $pct; ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($summary_rows)): ?>
                <p style="color:#555; font-size:13px;">No data for this period.</p>
            <?php endif; ?>
        </div>

        <!-- ATTENDANCE LOG -->
        <div class="card">
            <div class="section-header">
                <div class="section-title" style="margin-bottom:0;">Attendance Log</div>
                <span style="color:#555; font-size:13px;"><?php echo $log->num_rows; ?> record<?php echo $log->num_rows !== 1 ? 's' : ''; ?></span>
            </div>

            <?php if ($log->num_rows > 0): ?>
                <input type="text" class="log-search" id="log-search" placeholder="🔍  Search by member name..." oninput="filterLog()">
                <table>
                    <thead>
                        <tr><th>Member</th><th>Date</th><th>Time In</th><th>Time Out</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        // Reset result pointer
                        $log->data_seek(0);
                        while ($lr = $log->fetch_assoc()):
                        ?>
                            <tr class="log-row" data-name="<?php echo strtolower($lr['first_name'] . ' ' . $lr['last_name']); ?>">
                                <td>
                                    <?php echo htmlspecialchars($lr['first_name'] . ' ' . $lr['last_name']); ?>
                                    <span style="color:#555; font-size:11px; display:block;">#<?php echo $lr['member_id']; ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($lr['date'])); ?></td>
                                <td style="color:#4caf50;"><?php echo date('h:i A', strtotime($lr['time_in'])); ?></td>
                                <td><?php echo $lr['time_out'] ? date('h:i A', strtotime($lr['time_out'])) : '<span style="color:#555;">—</span>'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <p id="no-log-results" style="display:none; color:#555; font-size:13px; margin-top:10px;">No records match your search.</p>
            <?php else: ?>
                <div style="text-align:center; padding:2rem 0;">
                    <div style="font-size:2rem; margin-bottom:0.5rem;">📭</div>
                    <p style="color:#555; font-size:13px;">No attendance records for this period.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function toggleDateInputs(val) {
    document.getElementById('custom-range-wrap').classList.toggle('visible', val === 'custom');
    document.getElementById('month-pick-wrap').classList.toggle('visible', val === 'month_pick');
}

function filterLog() {
    const search  = document.getElementById('log-search').value.toLowerCase();
    const rows    = document.querySelectorAll('.log-row');
    let visible   = 0;
    rows.forEach(row => {
        if (row.dataset.name.includes(search)) { row.classList.remove('hidden'); visible++; }
        else row.classList.add('hidden');
    });
    document.getElementById('no-log-results').style.display = visible === 0 ? 'block' : 'none';
}
</script>

<script src="app.js"></script>
</body>
</html>
