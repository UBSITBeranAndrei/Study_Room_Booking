
<?php
session_start();
require_once __DIR__ . '/dbconn.php';

// Check login.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role = strtolower((string) $_SESSION['role']);
$fullName = $_SESSION['full_name'] ?? 'User';

// Allow only valid account types.
if (!in_array($role, ['student', 'employee', 'administrator'], true)) {
    header('Location: logout.php');
    exit;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

date_default_timezone_set('Asia/Manila');

$bookings = [];
$pendingCount = 0;

// Get personal bookings for students and employees.
if ($role === 'student' || $role === 'employee') {

    $ownerColumn = ($role === 'student')
        ? 'b.student_id'
        : 'b.employee_id';

    $sql = "SELECT
                b.booking_id,
                b.topic_purpose,
                b.booking_date,
                b.start_time,
                b.end_time,
                b.status,
                l.library_name,
                r.room_number,
                r.min_capacity,
                (
                    SELECT COUNT(*)
                    FROM booking_participants bp
                    WHERE bp.booking_id = b.booking_id
                ) AS participant_count
            FROM bookings b
            INNER JOIN rooms r ON b.room_id = r.room_id
            INNER JOIN libraries l ON r.library_id = l.library_id
            WHERE $ownerColumn = :user_id
            ORDER BY b.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);

    $bookings = $stmt->fetchAll();
}

// Get pending application count for administrators.
if ($role === 'administrator') {

    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM bookings
         WHERE status = 'pending'"
    );

    $pendingCount = (int) $stmt->fetchColumn();
}

// Booking status labels and styles.
$statusLabels = [
    'pending' => 'Pending approval',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'cancelled' => 'Cancelled',
    'completed' => 'Completed'
];

$statusClasses = [
    'pending' => 'status-pending',
    'approved' => 'status-approved',
    'rejected' => 'status-rejected',
    'cancelled' => 'status-cancelled',
    'completed' => 'status-completed'
];

// CSRF token for booking cancellation/deletion forms.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Dashboard messages.
$messages = [
    'equipment_saved' => [
        'success',
        'Equipment saved successfully. Check your booking status below.'
    ],
    'booking_deleted' => [
        'success',
        'Booking deleted successfully.'
    ],
    'booking_cancelled' => [
        'success',
        'Your approved booking was cancelled. The room is now free for other users.'
    ],
    'cancel_invalid' => [
        'danger',
        'That booking can no longer be cancelled or deleted.'
    ],
    'cancel_error' => [
        'danger',
        'Something went wrong while cancelling the booking. Please try again.'
    ]
];

$message = '';
$messageType = 'success';

if (
    isset($_GET['message']) &&
    is_string($_GET['message']) &&
    isset($messages[$_GET['message']])
) {
    [$messageType, $message] = $messages[$_GET['message']];
}

$roleLabel = ucfirst($role);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Dashboard | University of Baguio Room Booking</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="ub-layout">

    <!-- SHARED SIDEBAR -->
    <aside class="ub-sidebar">

        <div class="ub-sidebar-header">
            <small class="ub-sidebar-university">UNIVERSITY OF</small>

            <div class="ub-brand">BAGUIO</div>

            <div class="ub-brand-subtitle">
                STUDY ROOM BOOKING
            </div>
        </div>

        <div class="ub-user-profile">
            <strong><?= h($fullName) ?></strong>
            <small><?= h($roleLabel) ?> Account</small>
        </div>

        <nav>

            <a href="dashboard.php" class="active">
                <span>▣</span>
                Dashboard
            </a>

            <?php if ($role === 'student' || $role === 'employee'): ?>

                <a href="libraries.php">
                    <span>▦</span>
                    Browse Libraries
                </a>

                <a href="#my-bookings">
                    <span>☷</span>
                    My Bookings
                </a>

            <?php endif; ?>

            <?php if ($role === 'administrator'): ?>

                <a href="admin.php">
                    <span>☷</span>
                    Manage Bookings
                </a>

            <?php endif; ?>

        </nav>

        <div class="ub-sidebar-bottom">
            <a href="logout.php" class="ub-nav-link">
                <span>↪</span>
                Logout
            </a>
        </div>

    </aside>

    <!-- MAIN DASHBOARD -->
    <main class="ub-main">

        <div class="ub-content">

            <!-- PAGE HEADER -->
            <div class="ub-dashboard-topbar">

                <div>
                    <h1 class="ub-page-title">Dashboard</h1>

                    <p class="ub-page-subtitle">
                        University of Baguio Study Room Booking System
                    </p>
                </div>

                <span class="ub-role-label">
                    <?= h($roleLabel) ?>
                </span>

            </div>

            <!-- SUCCESS / ERROR MESSAGE -->
            <?php if ($message !== ''): ?>

                <div
                    class="alert alert-<?= h($messageType) ?>"
                    role="alert"
                >
                    <?= h($message) ?>
                </div>

            <?php endif; ?>

            <!-- WELCOME BANNER -->
            <section class="ub-dashboard-banner">

                <h2>
                    Welcome back, <?= h($fullName) ?>!
                </h2>

                <?php if ($role === 'administrator'): ?>

                    <p>
                        Manage room booking applications and monitor reservation requests.
                    </p>

                <?php else: ?>

                    <p>
                        Find a discussion room and make your next group study session productive.
                    </p>

                <?php endif; ?>

            </section>

            <!-- STUDENT AND EMPLOYEE DASHBOARD -->
            <?php if ($role === 'student' || $role === 'employee'): ?>

                <h2 class="ub-dashboard-section-title">
                    Quick Actions
                </h2>

                <div class="ub-dashboard-action-grid">

                    <a href="libraries.php" class="ub-action-card">

                        <div class="ub-action-icon">▦</div>

                        <h3>Browse Libraries</h3>

                        <p>
                            Explore available libraries and discussion rooms.
                        </p>

                    </a>

                    <a href="#my-bookings" class="ub-action-card">

                        <div class="ub-action-icon">☷</div>

                        <h3>My Bookings</h3>

                        <p>
                            View your booking history and application status.
                        </p>

                    </a>

                </div>

                <!-- PERSONAL BOOKINGS -->
                <section
                    class="ub-card ub-dashboard-bookings"
                    id="my-bookings"
                >

                    <div class="ub-card-header">

                        <h2 class="ub-card-title">
                            My Bookings
                        </h2>

                        <span class="text-muted small">
                            <?= count($bookings) ?> booking(s)
                        </span>

                    </div>

                    <?php if (count($bookings) === 0): ?>

                        <div class="ub-dashboard-empty">

                            <div class="ub-dashboard-empty-icon">
                                ▦
                            </div>

                            <h3>No Bookings Yet</h3>

                            <p>
                                You have not made any room bookings yet.
                            </p>

                            <a href="libraries.php" class="btn-ub">
                                Browse Libraries
                            </a>

                        </div>

                    <?php else: ?>

                        <div class="table-responsive">

                            <table class="table table-bordered table-hover">

                                <thead>
                                    <tr>
                                        <th>Booking ID</th>
                                        <th>Library</th>
                                        <th>Room</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($bookings as $booking): ?>

                                        <?php
                                        $status = (string) $booking['status'];

                                        $statusLabel = $statusLabels[$status]
                                            ?? ucfirst($status);

                                        $statusClass = $statusClasses[$status]
                                            ?? 'status-cancelled';

                                        $participantCount = (int) $booking['participant_count'];

                                        $minimumCapacity = (int) $booking['min_capacity'];

                                        $neededCount = max(
                                            0,
                                            $minimumCapacity - $participantCount
                                        );

                                        // Check if pending booking still needs participants.
                                        $isIncomplete = (
                                            $status === 'pending' &&
                                            (
                                                $participantCount === 0 ||
                                                $neededCount > 0
                                            )
                                        );

                                        // Determine whether the booking has started.
                                        $startsAt = strtotime(
                                            $booking['booking_date'] . ' ' . $booking['start_time']
                                        );

                                        $isUpcoming = (
                                            $startsAt !== false &&
                                            $startsAt > time()
                                        );

                                        // Pending, rejected, and cancelled bookings can be deleted.
                                        $canDelete = in_array(
                                            $status,
                                            ['pending', 'rejected', 'cancelled'],
                                            true
                                        );

                                        // Approved bookings can only be cancelled before they start.
                                        $canCancel = (
                                            $status === 'approved' &&
                                            $isUpcoming
                                        );

                                        if ($status === 'pending') {

                                            $actionLabel = 'Cancel Booking';

                                            $confirmText = 'Cancel this booking? It will be permanently deleted together with its participants and equipment.';

                                        } elseif ($canDelete) {

                                            $actionLabel = 'Delete';

                                            $confirmText = 'Delete this booking from your list? This cannot be undone.';

                                        } else {

                                            $actionLabel = 'Cancel Booking';

                                            $confirmText = 'Cancel this approved booking? The room will be released for other users.';

                                        }
                                        ?>

                                        <tr>

                                            <td>
                                                #<?= (int) $booking['booking_id'] ?>
                                            </td>

                                            <td>
                                                <?= h($booking['library_name']) ?>
                                            </td>

                                            <td>
                                                <?= h($booking['room_number']) ?>
                                            </td>

                                            <td>
                                                <?= h($booking['booking_date']) ?>
                                            </td>

                                            <td>
                                                <?= h(substr($booking['start_time'], 0, 5)) ?>
                                                -
                                                <?= h(substr($booking['end_time'], 0, 5)) ?>
                                            </td>

                                            <td>
                                                <?= h($booking['topic_purpose']) ?>
                                            </td>

                                            <td>

                                                <span class="status-badge <?= h($statusClass) ?>">
                                                    <?= h($statusLabel) ?>
                                                </span>

                                                <?php if ($isIncomplete): ?>

                                                    <a
                                                        href="participants.php?booking_id=<?= (int) $booking['booking_id'] ?>"
                                                        class="d-block small fw-semibold mt-1"
                                                    >

                                                        <?php if ($participantCount === 0): ?>

                                                            Incomplete: add participants

                                                        <?php else: ?>

                                                            Add <?= $neededCount ?> more participant(s)

                                                        <?php endif; ?>

                                                    </a>

                                                <?php endif; ?>

                                            </td>

                                            <td>

                                                <?php if ($canDelete || $canCancel): ?>

                                                    <form
                                                        method="post"
                                                        action="cancel_booking.php"
                                                        class="ub-booking-action-form"
                                                        onsubmit="return confirm(<?= h(json_encode($confirmText)) ?>);"
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
                                                            class="btn-ub-danger ub-btn-sm"
                                                        >
                                                            <?= h($actionLabel) ?>
                                                        </button>

                                                    </form>

                                                <?php else: ?>

                                                    <span class="text-muted small">
                                                        —
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                        <p class="text-muted small mt-3 mb-0">

                            Pending approval means your booking is waiting for an administrator to review it.

                            You can cancel a pending booking or an approved booking that has not started yet.

                        </p>

                    <?php endif; ?>

                </section>

            <?php endif; ?>

            <!-- ADMINISTRATOR DASHBOARD -->
            <?php if ($role === 'administrator'): ?>

                <h2 class="ub-dashboard-section-title">
                    Booking Management
                </h2>

                <div class="ub-dashboard-action-grid">

                    <div class="ub-action-card">

                        <div class="ub-action-icon">
                            ☷
                        </div>

                        <div class="ub-dashboard-count">
                            <?= (int) $pendingCount ?>
                        </div>

                        <h3>Pending Applications</h3>

                        <p>
                            Booking requests waiting for administrator review.
                        </p>

                    </div>

                    <a href="admin.php" class="ub-action-card">

                        <div class="ub-action-icon">
                            ✓
                        </div>

                        <h3>Manage Bookings</h3>

                        <p>
                            Review applications and approve or reject requests.
                        </p>

                        <span class="btn-ub d-inline-flex mt-3">
                            Open Booking Management
                        </span>

                    </a>

                </div>

            <?php endif; ?>

            <!-- FOOTER -->
            <footer class="ub-footer">

                © <?= date('Y') ?> University of Baguio. All rights reserved.

                <br>

                Study Room Booking System

            </footer>

        </div>

    </main>

</div>

</body>
</html>
