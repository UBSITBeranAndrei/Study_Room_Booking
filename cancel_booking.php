<?php
session_start();
require_once __DIR__ . '/dbconn.php';
date_default_timezone_set('Asia/Manila');

/*
 * Cancel or delete a booking (the "D" in CRUD).
 *
 * - Pending, rejected, or cancelled bookings are DELETED, together with
 *   their participants and equipment.
 * - Approved bookings that have not started yet are CANCELLED
 *   (status changed to 'cancelled') so the approval record is kept.
 */

// Send the user back to their bookings with a message.
function goBack(string $message): void
{
    header('Location: dashboard.php?message=' . urlencode($message) . '#my-bookings');
    exit;
}

// Accept POST requests only.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    goBack('cancel_invalid');
}

// Require login.
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$role = strtolower((string) $_SESSION['role']);
$userId = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);

// Only students and employees can cancel their own bookings.
if (!in_array($role, ['student', 'employee'], true) || $userId === false || $userId < 1) {
    goBack('cancel_invalid');
}

// Check the form token.
$postedToken = (string) ($_POST['csrf_token'] ?? '');

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
    goBack('cancel_invalid');
}

// Validate booking ID.
$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$bookingId) {
    goBack('cancel_invalid');
}

// The owner column depends on the account type (fixed values, not user input).
$ownerColumn = ($role === 'student') ? 'student_id' : 'employee_id';

try {
    $pdo->beginTransaction();

    // Load the booking and lock it so an admin cannot approve it at the same time.
    $stmt = $pdo->prepare(
        "SELECT booking_id, status, booking_date, start_time
         FROM bookings
         WHERE booking_id = :booking_id
           AND $ownerColumn = :user_id
         FOR UPDATE"
    );

    $stmt->execute([
        ':booking_id' => $bookingId,
        ':user_id' => $userId
    ]);

    $booking = $stmt->fetch();

    // Booking does not exist or belongs to someone else.
    if (!$booking) {
        $pdo->rollBack();
        goBack('cancel_invalid');
    }

    $status = (string) $booking['status'];
    $startsAt = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
    $isUpcoming = ($startsAt !== false && $startsAt > time());

    // DELETE: pending, rejected, or cancelled bookings.
    if (in_array($status, ['pending', 'rejected', 'cancelled'], true)) {

        // Remove child records first so foreign keys do not block the delete.
        $stmt = $pdo->prepare('DELETE FROM booking_equipment WHERE booking_id = :booking_id');
        $stmt->execute([':booking_id' => $bookingId]);

        $stmt = $pdo->prepare('DELETE FROM booking_participants WHERE booking_id = :booking_id');
        $stmt->execute([':booking_id' => $bookingId]);

        $stmt = $pdo->prepare('DELETE FROM bookings WHERE booking_id = :booking_id');
        $stmt->execute([':booking_id' => $bookingId]);

        $pdo->commit();
        goBack('booking_deleted');
    }

    // CANCEL: approved bookings that have not started yet.
    if ($status === 'approved' && $isUpcoming) {

        $stmt = $pdo->prepare(
            "UPDATE bookings
             SET status = 'cancelled'
             WHERE booking_id = :booking_id
               AND status = 'approved'"
        );

        $stmt->execute([':booking_id' => $bookingId]);

        $pdo->commit();
        goBack('booking_cancelled');
    }

    // Anything else (e.g. completed or already started) cannot be changed.
    $pdo->rollBack();
    goBack('cancel_invalid');

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('cancel_booking.php DB error: ' . $e->getMessage());
    goBack('cancel_error');
}
