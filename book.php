<?php
session_start();

// Helper functions
function encryptData($data, $key = "mysecretkey12345") {
    $key = substr(hash('sha256', $key, true), 0, 16);
    return base64_encode(openssl_encrypt($data, "AES-128-ECB", $key, OPENSSL_RAW_DATA));
}

function decryptData($data, $key = "mysecretkey12345") {
    $key = substr(hash('sha256', $key, true), 0, 16);
    return openssl_decrypt(base64_decode($data), "AES-128-ECB", $key, OPENSSL_RAW_DATA);
}

// Validate user
if (!isset($_SESSION['uname'])) {
    die("User not logged in.");
}
$uname = $_SESSION['uname'];
$encryptedUname = encryptData($uname);

// Validate POST data
$flights = $_POST['flights'] ?? [];
if (empty($flights)) {
    die("No flight selected.");
}

// DB connection
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

    // Check booked seats
    $stmt = $conn->prepare("SELECT seatNo FROM booking WHERE flightName = ? AND `date` = ?");
    $stmt->bind_param("ss", $flightName, $date);
    $stmt->execute();
    $result = $stmt->get_result();

    $bookedSeats = [];
    while ($row = $result->fetch_assoc()) {
        $bookedSeats[] = (int)$row['seatNo'];
    }
    $stmt->close();

    // Assign next available seat
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

    // Insert booking
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Flight Ticket</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #eef2f7;
            display: flex;
            justify-content: center;
            padding: 30px;
        }
        .ticket-wrapper {
            max-width: 800px;
            width: 100%;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 30px rgba(0, 0, 0, 0.15);
        }
        h2 {
            text-align: center;
            color: #2ecc71;
        }
        .ticket {
            margin: 20px 0;
            padding: 20px;
            border: 2px dashed #3498db;
            border-radius: 10px;
            position: relative;
        }
        .ticket::before, .ticket::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            background: #eef2f7;
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
        }
        .ticket::before { left: -10px; }
        .ticket::after { right: -10px; }
        .row {
            margin: 10px 0;
            font-size: 16px;
        }
        .row strong {
            width: 150px;
            display: inline-block;
        }
        .btn {
            display: block;
            margin: 20px auto 0;
            padding: 12px 24px;
            font-size: 16px;
            background-color: #2980b9;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
        }
        .btn:hover {
            background-color: #21618c;
        }
    </style>
</head>
<body>

<div class="ticket-wrapper" id="ticket">
    <h2>Booking Successful!</h2>
    <?php foreach ($bookedTickets as $ticket): ?>
        <div class="ticket">
            <div class="row"><strong>Username:</strong> <?= htmlspecialchars($uname) ?></div>
            <div class="row"><strong>Flight:</strong> <?= htmlspecialchars($ticket['flightName']) ?></div>
            <div class="row"><strong>Date:</strong> <?= htmlspecialchars($ticket['date']) ?></div>
            <div class="row"><strong>From:</strong> <?= htmlspecialchars($ticket['source']) ?> → <strong>To:</strong> <?= htmlspecialchars($ticket['destination']) ?></div>
            <div class="row"><strong>Departure:</strong> <?= htmlspecialchars($ticket['depaTime']) ?> | <strong>Arrival:</strong> <?= htmlspecialchars($ticket['arrivalTime']) ?></div>
            <div class="row"><strong>Seat No:</strong> <?= $ticket['seatNo'] ?></div>
            <div class="row"><strong>Price:</strong> ₹<?= htmlspecialchars($ticket['price']) ?></div>
        </div>
    <?php endforeach; ?>

    <!-- Buttons -->
    <button class="btn" onclick="downloadTicket()">Download Ticket</button>
    <button class="btn" onclick="window.location.href='search.php'">Back to Search</button>
</div>

<!-- PDF Generator -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadTicket() {
    const element = document.getElementById("ticket");
    const opt = {
        margin:       0.5,
        filename:     'Flight_Ticket_<?= date("Ymd_His") ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2 },
        jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
}
</script>

</body>
</html>