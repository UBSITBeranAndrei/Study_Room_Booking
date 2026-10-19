<?php
session_start();
require_once __DIR__ . '/dbconn.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role = strtolower((string) $_SESSION['role']);
$fullName = $_SESSION['full_name'] ?? 'User';

if (!in_array($role, ['student', 'employee', 'administrator'], true)) {
    header('Location: logout.php');
    exit;
}

$bookings = [];

if ($role === 'student' || $role === 'employee') {
    // Role is checked against a whitelist above, so this column is not user-supplied.
    $ownerColumn = ($role === 'student') ? 'b.student_id' : 'b.employee_id';

    $sql = "SELECT b.booking_id, b.topic_purpose, b.booking_date,
                   b.start_time, b.end_time, b.status,
                   l.library_name, r.room_number
            FROM bookings b
            JOIN rooms r ON b.room_id = r.room_id
            JOIN libraries l ON r.library_id = l.library_id
            WHERE $ownerColumn = :user_id
            ORDER BY b.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $bookings = $stmt->fetchAll();
}

$statusLabels = [
    'pending' => 'Pending approval',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'cancelled' => 'Cancelled',
    'completed' => 'Completed'
];

$statusClasses = [
    'pending' => 'warning text-dark',
    'approved' => 'success',
    'rejected' => 'danger',
    'cancelled' => 'secondary',
    'completed' => 'primary'
];

$message = '';
if (isset($_GET['message']) && $_GET['message'] === 'equipment_saved') {
    $message = 'Equipment saved successfully. Check your booking status below.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Library Room Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="mb-4">
        <h1 class="h2">Library Discussion Room Booking</h1>
        <p class="text-muted">
            Welcome, <strong><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></strong>!
            You are logged in as
            <strong><?php echo htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8'); ?></strong>.
        </p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h4">Dashboard</h2>
            <p>Choose an action below to continue.</p>
            <a href="libraries.php" class="btn btn-primary me-2 mb-2">Browse Libraries</a>

            <?php if ($role === 'administrator'): ?>
                <a href="admin.php" class="btn btn-success me-2 mb-2">Manage Bookings</a>
            <?php endif; ?>

            <a href="logout.php" class="btn btn-outline-secondary mb-2">Logout</a>
        </div>
    </div>

    <?php if ($role === 'student' || $role === 'employee'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0">My Bookings</h2>
            </div>
            <div class="card-body">
                <?php if (count($bookings) === 0): ?>
                    <p class="text-muted mb-0">You have not made any bookings yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Library</th>
                                    <th>Room</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                    <?php
                                    $status = (string) $booking['status'];
                                    $statusLabel = $statusLabels[$status] ?? ucfirst($status);
                                    $statusClass = $statusClasses[$status] ?? 'secondary';
                                    ?>
                                    <tr>
                                        <td><?php echo (int) $booking['booking_id']; ?></td>
                                        <td><?php echo htmlspecialchars($booking['library_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['room_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['booking_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(substr($booking['start_time'], 0, 5), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars(substr($booking['end_time'], 0, 5), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['topic_purpose'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="badge bg-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mb-0">Pending approval means your booking is waiting for an administrator to review it.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
