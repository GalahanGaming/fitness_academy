<?php
session_start();
include "config.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$member_id = intval($_GET['member_id'] ?? 0);
if (!$member_id) {
    echo json_encode(['error' => 'Invalid member']);
    exit();
}

$current_month = date('Y-m');
$month_label   = date('F Y');

$month_q = $conn->prepare("
    SELECT COUNT(*) as cnt
    FROM attendance
    WHERE member_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
");
$month_q->bind_param("is", $member_id, $current_month);
$month_q->execute();
$month_count = $month_q->get_result()->fetch_assoc()['cnt'];

$hist_q = $conn->prepare("
    SELECT date, time_in, time_out
    FROM attendance
    WHERE member_id = ?
    ORDER BY date DESC, time_in DESC
");
$hist_q->bind_param("i", $member_id);
$hist_q->execute();
$result = $hist_q->get_result();

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = [
        'date'     => date('M d, Y', strtotime($row['date'])),
        'time_in'  => date('h:i A',  strtotime($row['time_in'])),
        'time_out' => $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : null,
    ];
}

echo json_encode([
    'month_count' => $month_count,
    'month_label' => $month_label,
    'records'     => $records,
]);
