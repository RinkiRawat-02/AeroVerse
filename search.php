<?php
session_start();

function timeToMinutes($time) {
    list($h, $m, $s) = explode(':', $time);
    return $h * 60 + $m;
}

function encryptData($data, $key = "mysecretkey12345") {
    $key = substr(hash('sha256', $key, true), 0, 16);
    return base64_encode(openssl_encrypt($data, "AES-128-ECB", $key, OPENSSL_RAW_DATA));
}

// DB Connection
$conn = new mysqli("localhost", "root", "", "register");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$source = $_POST['source'] ?? '';
$destination = $_POST['destination'] ?? '';
$date = $_POST['date'] ?? '';
$uname = $_SESSION['uname'] ?? '';

if (!$uname || !$source || !$destination || !$date) die("Missing input.");

$sql = "SELECT * FROM flights WHERE date = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

$graph = [];
$allFlights = [];

while ($row = $result->fetch_assoc()) {
    $from = $row['source'];
    $to = $row['destination'];
    $dep = timeToMinutes($row['depaTime']);
    $arr = timeToMinutes($row['arrivalTime']);
    $duration = $arr - $dep;
    if ($duration < 0) $duration += 1440;

    $flight = [
        'flightName' => $row['flightName'],
        'source' => $from,
        'destination' => $to,
        'depaTime' => $row['depaTime'],
        'arrivalTime' => $row['arrivalTime'],
        'duration' => $duration,
        'price' => $row['price'],
        'date' => $row['date']
    ];

    $graph[$from][] = ['to' => $to, 'duration' => $duration, 'flight' => $flight];
    $allFlights[] = $flight;
}

// Shortest path using Dijkstra
$dist = [];
$prev = [];
foreach ($graph as $node => $_) $dist[$node] = INF;
$dist[$source] = 0;
$queue = [[$source, 0]];
$visited = [];

while (!empty($queue)) {
    usort($queue, fn($a, $b) => $a[1] <=> $b[1]);
    [$u, $d] = array_shift($queue);
    if (isset($visited[$u])) continue;
    $visited[$u] = true;

    foreach ($graph[$u] ?? [] as $edge) {
        $v = $edge['to'];
        $alt = $d + $edge['duration'];
        if ($alt < ($dist[$v] ?? INF)) {
            $dist[$v] = $alt;
            $prev[$v] = ['from' => $u, 'flight' => $edge['flight']];
            $queue[] = [$v, $alt];
        }
    }
}

$shortestPath = [];
$node = $destination;
while (isset($prev[$node])) {
    array_unshift($shortestPath, $prev[$node]['flight']);
    $node = $prev[$node]['from'];
}

// DFS for cheapest routes
function findRoutes($graph, $src, $dst, $path = [], $visited = [], $maxStops = 3) {
    if (count($path) > $maxStops) return [];
    $routes = [];
    $visited[$src] = true;
    foreach ($graph[$src] ?? [] as $edge) {
        if ($visited[$edge['to']] ?? false) continue;
        $newPath = [...$path, $edge['flight']];
        if ($edge['to'] === $dst) {
            $routes[] = $newPath;
        } else {
            $routes = array_merge($routes, findRoutes($graph, $edge['to'], $dst, $newPath, $visited));
        }
    }
    return $routes;
}
$cheapestRoutes = findRoutes($graph, $source, $destination);
usort($cheapestRoutes, fn($a, $b) => array_sum(array_column($a, 'price')) <=> array_sum(array_column($b, 'price')));
?>

<!DOCTYPE html>
<html>
<head>
    <title>Flight Results</title>
    <style>
        body { font-family: Arial; background: #eef2f7; }
        .container { max-width: 1100px; margin: 40px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        table { width: 100%; margin-top: 20px; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #ddd; }
        th { background: #2c3e50; color: white; }
        .hidden { display: none; }
        .button, input[type=submit], button {
            padding: 8px 16px;
            background: #3498db;
            border: none;
            color: white;
            border-radius: 5px;
            cursor: pointer;
        }
        .button:hover, input[type=submit]:hover, button:hover { background: #2980b9; }
    </style>
</head>
<body>
<div class="container">
    <h2>Flights from <?= htmlspecialchars($source) ?> to <?= htmlspecialchars($destination) ?> on <?= htmlspecialchars($date) ?></h2>
    <div style="text-align:center;">
        <button class="button" onclick="showTab('shortest')">Shortest Path</button>
        <button class="button" onclick="showTab('direct')">Direct Flights</button>
        <button class="button" onclick="showTab('cheapest')">Cheapest Routes</button>
        <button class="button" onclick="cancelBooking()">Cancel Booking</button>
    </div>

    <!-- TABS: Shortest -->
    <div id="shortest" class="tab hidden">
        <h3>Shortest Route</h3>
        <?php if ($shortestPath): ?>
        <form method="POST" action="book.php">
            <table><tr><th>Flight</th><th>From</th><th>To</th><th>Dep</th><th>Arr</th><th>Duration</th><th>Price</th></tr>
            <?php $total = 0; foreach ($shortestPath as $i => $f): $total += $f['price']; ?>
                <tr>
                    <?php foreach ($f as $k => $v): ?><input type="hidden" name="flights[<?= $i ?>][<?= $k ?>]" value="<?= htmlspecialchars($v) ?>"><?php endforeach; ?>
                    <td><?= $f['flightName'] ?></td><td><?= $f['source'] ?></td><td><?= $f['destination'] ?></td>
                    <td><?= $f['depaTime'] ?></td><td><?= $f['arrivalTime'] ?></td><td><?= $f['duration'] ?> mins</td><td>₹<?= $f['price'] ?></td>
                </tr>
            <?php endforeach; ?>
            <tr><td colspan="6" style="text-align:right;"><strong>Total:</strong></td><td><strong>₹<?= $total ?></strong></td></tr>
            </table>
            <div style="text-align:center;"><input type="submit" value="Book This Route" class="button"></div>
        </form>
        <?php else: ?><p>No route found.</p><?php endif; ?>
    </div>

    <!-- TABS: Direct Flights -->
    <div id="direct" class="tab hidden">
        <h3>Direct Flights</h3>
        <table><tr><th>Flight</th><th>From</th><th>To</th><th>Dep</th><th>Arr</th><th>Dur</th><th>Price</th><th>Action</th></tr>
        <?php
        $found = false;
        foreach ($allFlights as $f):
            if ($f['source'] === $source && $f['destination'] === $destination):
                $found = true; ?>
                <tr>
                    <td><?= $f['flightName'] ?></td><td><?= $f['source'] ?></td><td><?= $f['destination'] ?></td>
                    <td><?= $f['depaTime'] ?></td><td><?= $f['arrivalTime'] ?></td><td><?= $f['duration'] ?> mins</td><td>₹<?= $f['price'] ?></td>
                    <td>
                        <form method="POST" action="book.php" style="display:inline;">
                            <?php foreach ($f as $k => $v): ?>
                                <input type="hidden" name="flights[0][<?= $k ?>]" value="<?= htmlspecialchars($v) ?>">
                            <?php endforeach; ?>
                            <input type="submit" value="Book" class="button">
                        </form>
                    </td>
                </tr>
            <?php endif; endforeach;
        if (!$found) echo "<tr><td colspan='8'>No direct flights.</td></tr>"; ?>
        </table>
    </div>

    <!-- TABS: Cheapest -->
    <div id="cheapest" class="tab hidden">
        <h3>Cheapest Routes</h3>
        <?php if ($cheapestRoutes): ?>
            <?php foreach ($cheapestRoutes as $index => $route): ?>
                <form method="POST" action="book.php">
                    <table style="margin-bottom:20px;"><tr><th>Flight</th><th>From</th><th>To</th><th>Dep</th><th>Arr</th><th>Dur</th><th>Price</th></tr>
                    <?php $sum = 0; foreach ($route as $i => $f): $sum += $f['price']; ?>
                        <tr>
                            <?php foreach ($f as $k => $v): ?>
                                <input type="hidden" name="flights[<?= $i ?>][<?= $k ?>]" value="<?= htmlspecialchars($v) ?>">
                            <?php endforeach; ?>
                            <td><?= $f['flightName'] ?></td><td><?= $f['source'] ?></td><td><?= $f['destination'] ?></td>
                            <td><?= $f['depaTime'] ?></td><td><?= $f['arrivalTime'] ?></td><td><?= $f['duration'] ?> mins</td><td>₹<?= $f['price'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr><td colspan="6" style="text-align:right;"><strong>Total:</strong></td><td><strong>₹<?= $sum ?></strong></td></tr>
                    </table>
                    <div style="text-align:center;"><input type="submit" value="Book This Route" class="button"></div>
                </form>
            <?php endforeach; ?>
        <?php else: ?><p>No cheap routes found.</p><?php endif; ?>
    </div>
</div>

<script>
function showTab(id) {
    ['shortest', 'direct', 'cheapest'].forEach(tab => document.getElementById(tab).classList.add('hidden'));
    document.getElementById(id).classList.remove('hidden');
}

function cancelBooking() {
    if (!confirm("Are you sure you want to cancel your booking?")) return;
    fetch("cancel_booking.php", { method: "POST" })
        .then(res => res.text())
        .then(msg => { alert(msg); location.reload(); })
        .catch(err => alert("Error cancelling: " + err));
}
</script>
</body>
</html>
