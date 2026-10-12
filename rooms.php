<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$library = null;
$rooms = [];
$errorMessage = '';

if (!isset($_GET['library_id']) || !is_numeric($_GET['library_id'])) {
    $errorMessage = 'Invalid or missing library ID.';
} else {
    $libraryId = (int) $_GET['library_id'];

    $librarySql = "SELECT library_id, library_name FROM libraries WHERE library_id = $libraryId";
    $libraryResult = mysqli_query($conn, $librarySql);

    if (!$libraryResult || mysqli_num_rows($libraryResult) === 0) {
        $errorMessage = 'Library not found.';
    } else {
        $library = mysqli_fetch_assoc($libraryResult);

        $roomSql = "SELECT room_id, room_number, min_capacity, max_capacity FROM rooms WHERE library_id = $libraryId ORDER BY room_number ASC";
        $roomResult = mysqli_query($conn, $roomSql);

        if ($roomResult) {
            while ($row = mysqli_fetch_assoc($roomResult)) {
                $rooms[] = $row;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rooms</title>
</head>
<body>
    <a href="libraries.php">&laquo; Back to Libraries</a>

    <?php if ($errorMessage): ?>
        <p><?php echo htmlspecialchars($errorMessage); ?></p>
    <?php else: ?>
        <h1>Rooms in <?php echo htmlspecialchars($library['library_name']); ?></h1>

        <?php if (count($rooms) === 0): ?>
            <p>No rooms found for this library.</p>
        <?php else: ?>
            <table border="1">
                <tr>
                    <th>Room Number</th>
                    <th>Min Capacity</th>
                    <th>Max Capacity</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($rooms as $room): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($room['room_number']); ?></td>
                        <td><?php echo htmlspecialchars($room['min_capacity']); ?></td>
                        <td><?php echo htmlspecialchars($room['max_capacity']); ?></td>
                        <td>
                            <a href="booking.php?room_id=<?php echo (int) $room['room_id']; ?>">Book</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>