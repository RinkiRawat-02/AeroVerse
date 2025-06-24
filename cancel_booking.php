<?php
session_start();
if (!isset($_SESSION['uname'])) { echo "User not logged in."; exit; }

$uname = $_SESSION['uname'];

function encryptData($data, $key = "mysecretkey12345") {
    $key = substr(hash('sha256', $key, true), 0, 16);
    return base64_encode(openssl_encrypt($data, "AES-128-ECB", $key, OPENSSL_RAW_DATA));
}

$conn = new mysqli("localhost", "root", "", "register");
if ($conn->connect_error) { echo "DB error"; exit; }

$encryptedUname = encryptData($uname);
$stmt = $conn->prepare("SELECT id FROM booking WHERE uname = ?");
$stmt->bind_param("s", $encryptedUname);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "No booking has been done.";
} else {
    $del = $conn->prepare("DELETE FROM booking WHERE uname = ?");
    $del->bind_param("s", $encryptedUname);
    $del->execute();
    echo "Booking cancelled.";
}
$conn->close();
?>
