<?php
include "config.php";

$sql = "SELECT * FROM members";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "ID: " . $row["member_id"] . " - Name: " . $row["first_name"] . " " . $row["last_name"] . "<br>";
    }
} else {
    echo "No data found";
}

$conn->close();
?>