<?php
session_start();
include "config.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['error' => 'Unauthorized. Please log in again.']);
    exit();
}

$member_id = intval($_GET['member_id'] ?? 0);
if (!$member_id) {
    echo json_encode(['error' => 'Invalid Member ID.']);
    exit();
}

$stmt = $conn->prepare("
    SELECT m.member_id, m.first_name, m.last_name, m.profile_photo,
           u.is_active
    FROM members m
    JOIN user_account u ON u.member_id = m.member_id
    WHERE m.member_id = ? AND u.role = 'member'
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$mem = $stmt->get_result()->fetch_assoc();

if (!$mem) {
    echo json_encode(['error' => 'No member found with ID #' . $member_id . '. Please check and try again.']);
    exit();
}

if ($mem['is_active'] == 0) {
    echo json_encode(['error' => 'This account has been deactivated.']);
    exit();
}

$sub_stmt = $conn->prepare("
    SELECT s.status, s.end_date, pl.plan_name
    FROM subscription s
    JOIN membership_plan pl ON pl.plan_id = s.plan_id
    WHERE s.member_id = ?
    ORDER BY s.created_at DESC LIMIT 1
");
$sub_stmt->bind_param("i", $member_id);
$sub_stmt->execute();
$sub = $sub_stmt->get_result()->fetch_assoc();

$end_ts = $sub && $sub['end_date'] ? strtotime($sub['end_date']) : null;

if (!$sub || !$sub['plan_name']) {
    $status = 'none';
} elseif (strtolower($sub['status']) === 'active' && $end_ts && $end_ts > time()) {
    $status = 'active';
} elseif (strtolower($sub['status']) === 'pending') {
    $status = 'pending';
} else {
    $status = 'expired';
}

$can_checkin = ($status === 'active');

$today    = date('Y-m-d');
$att_stmt = $conn->prepare("SELECT time_in, time_out FROM attendance WHERE member_id = ? AND date = ?");
$att_stmt->bind_param("is", $member_id, $today);
$att_stmt->execute();
$today_att = $att_stmt->get_result()->fetch_assoc();

// Build photo URL
$photo_url = null;
if ($mem['profile_photo']) {
    $photo_url = 'uploads/profile_photos/' . $mem['profile_photo'];
}

header('Content-Type: application/json');
echo json_encode([
    'member_id'       => $mem['member_id'],
    'name'            => $mem['first_name'] . ' ' . $mem['last_name'],
    'plan'            => $sub['plan_name'] ?? null,
    'expiry'          => ($sub && $sub['end_date']) ? date('M d, Y', strtotime($sub['end_date'])) : null,
    'status'          => $status,
    'can_checkin'     => $can_checkin,
    'photo_url'       => $photo_url,
    'timed_in_today'  => $today_att ? true : false,
    'timed_out_today' => ($today_att && $today_att['time_out']) ? true : false,
    'time_in_today'   => $today_att ? date('h:i A', strtotime($today_att['time_in'])) : null,
    'time_out_today'  => ($today_att && $today_att['time_out']) ? date('h:i A', strtotime($today_att['time_out'])) : null,
]);
