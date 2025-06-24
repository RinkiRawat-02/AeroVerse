<?php
session_start();

// Encrypt function
function encryptData($data, $key = "mysecretkey12345") {
    $key = substr(hash('sha256', $key, true), 0, 16);
    return base64_encode(openssl_encrypt($data, "AES-128-ECB", $key, OPENSSL_RAW_DATA));
}

if (!isset($_SESSION['uname'])) {
    die("User not logged in.");
}
$uname = $_SESSION['uname'];
$encryptedUname = encryptData($uname);

$flights = $_POST['flights'] ?? [];
if (empty($flights)) {
    die("No flight selected.");
}

// DB
$conn = new mysqli("localhost", "root", "", "register");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$bookedTickets = [];
$maxSeats = 100;

foreach ($flights as $flight) {
    $flightName   = $flight['flightName'] ?? '';
    $date         = $flight['date'] ?? '';
    $source       = $flight['source'] ?? '';
    $destination  = $flight['destination'] ?? '';
    $depaTime     = $flight['depaTime'] ?? '';
    $arrivalTime  = $flight['arrivalTime'] ?? '';
    $price        = $flight['price'] ?? 0;

    if (!$flightName || !$date || !$source || !$destination || !$depaTime || !$arrivalTime || !$price) {
        continue;
    }

    $stmt = $conn->prepare("SELECT seatNo FROM booking WHERE flightName = ? AND `date` = ?");
    $stmt->bind_param("ss", $flightName, $date);
    $stmt->execute();
    $result = $stmt->get_result();

    $bookedSeats = [];
    while ($row = $result->fetch_assoc()) {
        $bookedSeats[] = (int)$row['seatNo'];
    }
    $stmt->close();

    $seatNo = null;
    for ($i = 1; $i <= $maxSeats; $i++) {
        if (!in_array($i, $bookedSeats)) {
            $seatNo = $i;
            break;
        }
    }

    if ($seatNo === null) {
        die("No seats available on $flightName.");
    }

    $insert = $conn->prepare("INSERT INTO booking (uname, flightName, seatNo, `date`, source, destination, depaTime, arrivalTime, price)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insert->bind_param("ssisssssd", $encryptedUname, $flightName, $seatNo, $date, $source, $destination, $depaTime, $arrivalTime, $price);
    $insert->execute();
    $insert->close();

    $bookedTickets[] = [
        'flightName' => $flightName,
        'date' => $date,
        'source' => $source,
        'destination' => $destination,
        'depaTime' => $depaTime,
        'arrivalTime' => $arrivalTime,
        'price' => $price,
        'seatNo' => $seatNo
    ];
}

$conn->close();

// Store tickets in session & redirect
$_SESSION['bookedTickets'] = $bookedTickets;
header("Location: ticket.php");
exit;
