<?php
require_once __DIR__ . '/dbconn.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only logged-in users can view and book rooms.
if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$library = null;
$rooms = [];
$errorMessage = '';

$libraryId = filter_input(
    INPUT_GET,
    'library_id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($libraryId === false || $libraryId === null) {
    $errorMessage = 'Invalid or missing library ID.';
} else {
    $librarySql = 'SELECT library_id, library_name
                   FROM libraries
                   WHERE library_id = :library_id';
    $libraryStmt = $pdo->prepare($librarySql);
    $libraryStmt->execute([':library_id' => $libraryId]);
    $library = $libraryStmt->fetch();

    if (!$library) {
        $errorMessage = 'Library not found.';
    } else {
        $roomSql = 'SELECT room_id, room_number, min_capacity, max_capacity
                    FROM rooms
                    WHERE library_id = :library_id
                    ORDER BY room_number ASC';
        $roomStmt = $pdo->prepare($roomSql);
        $roomStmt->execute([':library_id' => $libraryId]);
        $rooms = $roomStmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Available Rooms - Library Room Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="h3 mb-0">Available Discussion Rooms</h1>
        <div class="d-flex flex-wrap gap-2">
            <a href="dashboard.php" class="btn btn-outline-secondary">Dashboard</a>
            <a href="libraries.php" class="btn btn-outline-primary">Back to Libraries</a>
        </div>
    </div>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php else: ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h4 mb-2"><?php echo htmlspecialchars($library['library_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="text-muted mb-0">Select a room below to make a booking.</p>
            </div>
        </div>

        <?php if (count($rooms) === 0): ?>
            <div class="alert alert-info">No rooms are currently listed for this library.</div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Room Number</th>
                                    <th>Minimum Capacity</th>
                                    <th>Maximum Capacity</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rooms as $room): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int) $room['min_capacity']; ?> people</td>
                                        <td><?php echo (int) $room['max_capacity']; ?> people</td>
                                        <td>
                                            <a href="booking.php?room_id=<?php echo (int) $room['room_id']; ?>" class="btn btn-primary btn-sm">Book This Room</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
