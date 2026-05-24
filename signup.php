<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $contact = $_POST['contact'];
    $gender = $_POST['gender'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if email already exists
    $check = $conn->prepare("SELECT * FROM user_account WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        echo "Email already exists. Try logging in.";
    } else {

        // Insert into members
        $sql1 = $conn->prepare("INSERT INTO members (first_name, last_name, contact_number, gender) VALUES (?, ?, ?, ?)");
        $sql1->bind_param("ssss", $first_name, $last_name, $contact, $gender);

        if ($sql1->execute()) {
            $member_id = $conn->insert_id;

            // Insert into user_account
            $sql2 = $conn->prepare("INSERT INTO user_account (member_id, email, password, role) VALUES (?, ?, ?, 'member')");
            $sql2->bind_param("iss", $member_id, $email, $hashed_password);

            if ($sql2->execute()) {

                // Fix #3 — redirect instead of echo
                header("Location: index.php?msg=registered");
                exit();

            } else {
                echo "Error creating account: " . $conn->error;
            }
        } else {
            echo "Error inserting member: " . $conn->error;
        }
    }

    $conn->close();
}
?>