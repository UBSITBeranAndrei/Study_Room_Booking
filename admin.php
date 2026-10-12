
<?php
session_start();
require_once "dbconn.php";

// Only administrators can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "administrator") {
    header("location:login.php");
    exit;
}

// Create a token for the approval forms
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = "";

if (isset($_GET['message'])) {
    if ($_GET['message'] == "approved") {
        $message = "Booking approved successfully.";
    } elseif ($_GET['message'] == "rejected") {
        $message = "Booking rejected successfully.";
    } elseif ($_GET['message'] == "error") {
        $message = "Unable to process the booking. Check the booking details and participant capacity.";
    } elseif ($_GET['message'] == "invalid") {
        $message = "Invalid request or booking already processed.";
    }
}

// Get pending bookings
$sql = "SELECT b.booking_id, b.topic_purpose,
               b.booking_date, b.start_time, b.end_time,
               l.library_name, r.room_number,
               COALESCE(s.full_name, e.full_name) AS booker_name,
               CASE
                   WHEN b.student_id IS NOT NULL THEN 'Student'
                   ELSE 'Employee'
               END AS booker_type,
               (SELECT COUNT(*)
                FROM booking_participants bp
                WHERE bp.booking_id = b.booking_id)
                AS participant_count
        FROM bookings b
        JOIN rooms r ON b.room_id = r.room_id
        JOIN libraries l ON r.library_id = l.library_id
        LEFT JOIN students s ON b.student_id = s.student_id
        LEFT JOIN employees e ON b.employee_id = e.employee_id
        WHERE b.status = 'pending'
        ORDER BY b.booking_date, b.start_time";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$bookings = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrator - Booking Management</title>
</head>
<body>

<h2>Library Room Booking - Administrator</h2>

<p>
    Welcome,
    <?php echo htmlspecialchars($_SESSION['full_name']); ?>!
</p>

<a href="dashboard.php">Back to Dashboard</a> |
<a href="logout.php">Logout</a>

<hr>

<h3>Pending Bookings</h3>

<?php if ($message != ""): ?>
    <p><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<?php if (count($bookings) == 0): ?>

    <p>There are no pending bookings.</p>

<?php else: ?>

<table border="1" cellpadding="8" cellspacing="0">
    <tr>
        <th>Booking ID</th>
        <th>Booker</th>
        <th>Type</th>
        <th>Library</th>
        <th>Room</th>
        <th>Date</th>
        <th>Start</th>
        <th>End</th>
        <th>Purpose</th>
        <th>Participants</th>
        <th>Action</th>
    </tr>

    <?php foreach ($bookings as $booking): ?>
    <tr>
        <td><?php echo $booking['booking_id']; ?></td>

        <td>
            <?php echo htmlspecialchars($booking['booker_name'] ?? 'Unknown'); ?>
        </td>

        <td><?php echo htmlspecialchars($booking['booker_type']); ?></td>

        <td><?php echo htmlspecialchars($booking['library_name']); ?></td>

        <td><?php echo htmlspecialchars($booking['room_number']); ?></td>

        <td><?php echo htmlspecialchars($booking['booking_date']); ?></td>

        <td><?php echo htmlspecialchars($booking['start_time']); ?></td>

        <td><?php echo htmlspecialchars($booking['end_time']); ?></td>

        <td><?php echo htmlspecialchars($booking['topic_purpose']); ?></td>

        <td><?php echo $booking['participant_count']; ?></td>

        <td>
            <form method="post" action="admin_action.php">
                <input type="hidden" name="booking_id"
                       value="<?php echo $booking['booking_id']; ?>">

                <input type="hidden" name="csrf_token"
                       value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <button type="submit" name="action" value="approved">
                    Approve
                </button>

                <button type="submit" name="action" value="rejected">
                    Reject
                </button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>

</table>

<?php endif; ?>

</body>
</html>
