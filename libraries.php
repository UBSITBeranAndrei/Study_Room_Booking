<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$libraries = [];

$sql = "SELECT library_id, library_name FROM libraries ORDER BY library_name ASC";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $libraries[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Libraries</title>
</head>
<body>
    <h1>Libraries</h1>

    <?php if (count($libraries) === 0): ?>
        <p>No libraries found.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($libraries as $library): ?>
                <li>
                    <?php echo htmlspecialchars($library['library_name']); ?>
                    - <a href="rooms.php?library_id=<?php echo (int) $library['library_id']; ?>">View Rooms</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>