<?php
require_once __DIR__ . '/dbconn.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sql = "SELECT library_id, library_name FROM libraries ORDER BY library_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$libraries = $stmt->fetchAll();
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