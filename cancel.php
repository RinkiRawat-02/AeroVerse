<?php
session_start();

function hashData($data) {
    return hash('sha256', $data);
}

if (!isset($_SESSION['uname']) || empty($_SESSION['uname'])) {
    die("User not logged in.");
}

$uname = $_SESSION['uname'];
$hashedUname = hashData($uname);

$conn = new mysqli("localhost", "root", "", "register");
if ($conn->connect_error) {
    error_log("DB connection failed: " . $conn->connect_error);
    die("An internal error occurred.");
}

// Check if booking exists
$stmt = $conn->prepare("SELECT id FROM booking WHERE uname_hash = ?");
$stmt->bind_param("s", $hashedUname);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "No booking has been done.";
} else {
    $del = $conn->prepare("DELETE FROM booking WHERE uname_hash = ?");
    $del->bind_param("s", $hashedUname);
    $del->execute();

    if ($del->affected_rows > 0) {
        echo "Booking cancelled.";
    } else {
        echo "Cancellation failed.";
    }
}

$conn->close();
