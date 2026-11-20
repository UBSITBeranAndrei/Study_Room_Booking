<?php
session_start();
require_once __DIR__ . '/dbconn.php';

// Only administrators can access this page.
if (!isset($_SESSION['user_id']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'administrator') {
    header('Location: login.php');
    exit;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Create a token for the approval forms.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = 'info';

if (isset($_GET['message'])) {
    if ($_GET['message'] === 'approved') {
        $message = 'Booking approved successfully.';
        $messageType = 'success';
    } elseif ($_GET['message'] === 'rejected') {
        $message = 'Booking rejected successfully.';
        $messageType = 'warning';
    } elseif ($_GET['message'] === 'error') {
        $message = 'Unable to process the booking. Check the booking details and participant capacity.';
        $messageType = 'danger';
    } elseif ($_GET['message'] === 'invalid') {
        $message = 'Invalid request or booking already processed.';
        $messageType = 'danger';
    }
}

// Get pending bookings.
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
                WHERE bp.booking_id = b.booking_id) AS participant_count
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
$pendingCount = count($bookings);
$fullName = $_SESSION['full_name'] ?? 'Administrator';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Management | University of Baguio</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="ub-layout">

    <!-- SHARED ADMIN SIDEBAR -->
    <aside class="ub-sidebar">

        <div class="ub-sidebar-header">
            <small class="ub-sidebar-university">UNIVERSITY OF</small>
            <div class="ub-brand">BAGUIO</div>
            <div class="ub-brand-subtitle">STUDY ROOM BOOKING</div>
        </div>

        <div class="ub-user-profile">
            <strong><?= h($fullName) ?></strong>
            <small>Administrator Account</small>
        </div>

        <nav>
            <a href="dashboard.php">
                <span>▣</span>
                Dashboard
            </a>

            <a href="admin.php" class="active">
                <span>☷</span>
                Manage Bookings
            </a>
        </nav>

        <div class="ub-sidebar-bottom">
            <a href="logout.php" class="ub-nav-link">
                <span>↪</span>
                Logout
            </a>
        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="ub-main">
        <div class="ub-content">

            <!-- PAGE HEADER -->
            <div class="ub-dashboard-topbar">
                <div>
                    <h1 class="ub-page-title">Booking Management</h1>
                    <p class="ub-page-subtitle mb-0">
                        Review and manage student and employee booking applications.
                    </p>
                </div>

                <span class="ub-role-label">Administrator</span>
            </div>

            <!-- WELCOME BANNER -->
            <section class="ub-banner">
                <h2>Manage Booking Applications</h2>
                <p>Review pending room reservations and approve or reject applications.</p>
            </section>

            <!-- PENDING BOOKING SUMMARY -->
            <section class="ub-card ub-admin-pending-summary">
                <div>
                    <h2 class="ub-admin-summary-title">Pending Applications</h2>
                    <p class="ub-admin-summary-description">
                        Booking applications waiting for your review.
                    </p>
                </div>

                <div class="ub-admin-pending-total">
                    <strong><?= $pendingCount ?></strong>
                    <span>Pending Bookings</span>
                </div>
            </section>

            <!-- NOTIFICATION -->
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= h($messageType) ?> alert-dismissible fade show" role="alert">
                    <?= h($message) ?>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close"
                    ></button>
                </div>
            <?php endif; ?>

            <!-- PENDING BOOKING TABLE -->
            <section class="ub-card ub-admin-table-card">

                <div class="ub-admin-table-header">
                    <h2>Pending Booking Applications</h2>
                </div>

                <div class="ub-admin-table-body">

                    <?php if ($pendingCount === 0): ?>

                        <div class="ub-admin-empty-state">
                            <h3>No Pending Bookings</h3>
                            <p>There are currently no booking applications waiting for approval.</p>
                            <a href="dashboard.php" class="btn-ub mt-2">
                                Return to Dashboard
                            </a>
                        </div>

                    <?php else: ?>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover ub-admin-booking-table">
                                <thead>
                                    <tr>
                                        <th>Booking ID</th>
                                        <th>Booker</th>
                                        <th>Type</th>
                                        <th>Library</th>
                                        <th>Room</th>
                                        <th>Date</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Purpose</th>
                                        <th>Participants</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                        <tr>
                                            <td>
                                                <span class="ub-admin-booking-id">
                                                    #<?= (int) $booking['booking_id'] ?>
                                                </span>
                                            </td>

                                            <td>
                                                <strong class="ub-admin-booker-name">
                                                    <?= h($booking['booker_name'] ?? 'Unknown') ?>
                                                </strong>
                                            </td>

                                            <td>
                                                <span class="ub-admin-booker-type">
                                                    <?= h($booking['booker_type']) ?>
                                                </span>
                                            </td>

                                            <td><?= h($booking['library_name']) ?></td>
                                            <td><?= h($booking['room_number']) ?></td>
                                            <td><?= h($booking['booking_date']) ?></td>
                                            <td><?= h($booking['start_time']) ?></td>
                                            <td><?= h($booking['end_time']) ?></td>
                                            <td><?= h($booking['topic_purpose']) ?></td>

                                            <td>
                                                <span class="ub-admin-participant-count">
                                                    <?= (int) $booking['participant_count'] ?>
                                                </span>
                                            </td>

                                            <td>
                                                <form
                                                    method="post"
                                                    action="admin_action.php"
                                                    class="ub-admin-action-form"
                                                    onsubmit="return confirm('Are you sure you want to ' + (event.submitter.value === 'approved' ? 'approve' : 'reject') + ' this booking?');"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="booking_id"
                                                        value="<?= (int) $booking['booking_id'] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= h($_SESSION['csrf_token']) ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        name="action"
                                                        value="approved"
                                                        class="btn-ub-success"
                                                    >
                                                        Approve
                                                    </button>

                                                    <button
                                                        type="submit"
                                                        name="action"
                                                        value="rejected"
                                                        class="btn-ub-outline"
                                                    >
                                                        Reject
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php endif; ?>

                </div>
            </section>

            <!-- FOOTER -->
            <footer class="ub-footer">
                © <?= date('Y') ?> University of Baguio. All rights reserved.<br>
                Study Room Booking System
            </footer>

        </div>
    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
