<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Find user by email
    $stmt = $conn->prepare("SELECT u.user_id, u.email, u.password, u.role, u.member_id 
                            FROM user_account u 
                            WHERE u.email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user['password'])) {

            // Store in session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['member_id'] = $user['member_id'];

            // Redirect based on role
            if ($user['role'] === 'owner' || $user['role'] === 'manager') {
                header("Location: owner_dashboard.php");
                exit();
            } elseif ($user['role'] === 'staff') {
                header("Location: staff_dashboard.php");
                exit();
            } elseif ($user['role'] === 'member') {
                header("Location: member_dashboard.php");
                exit();
            } else {
                echo "Unknown role. Contact admin.";
            }

        } else {
            // Wrong password
            header("Location: index.php?msg=invalid");
            exit();
        }

    } else {
        // Email not found
        header("Location: index.php?msg=invalid");
        exit();
    }

    $conn->close();
}
?>