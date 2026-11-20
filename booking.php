
<?php
session_start();
require_once __DIR__ . '/dbconn.php';

date_default_timezone_set('Asia/Manila');

$loginPage = 'login.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function stopWithMessage(
    int $code,
    string $title,
    string $message,
    ?string $linkUrl = null,
    ?string $linkText = null
): void {
    http_response_code($code);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h($title) ?> | University of Baguio</title>

        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
            rel="stylesheet"
        >

        <link rel="stylesheet" href="style.css">
    </head>

    <body>

        <main class="ub-booking-error-page">

            <div class="ub-booking-error-card">

                <h1><?= h($title) ?></h1>

                <p><?= h($message) ?></p>

                <?php if ($linkUrl): ?>

                    <a href="<?= h($linkUrl) ?>" class="btn-ub">
                        <?= h($linkText ?? 'Back') ?>
                    </a>

                <?php endif; ?>

            </div>

        </main>

    </body>
    </html>
    <?php
    exit;
}


// Only students and employees can book rooms.
$isAllowed = isset($_SESSION['user_id'], $_SESSION['role'])
    && in_array($_SESSION['role'], ['student', 'employee'], true);

if (!$isAllowed) {
    stopWithMessage(
        403,
        'Login Required',
        'Please log in as a student or employee to book a discussion room.',
        $loginPage,
        'Go to Login'
    );
}

$role = $_SESSION['role'];
$userName = $_SESSION['full_name'] ?? 'User';


// Get room ID from URL.
$roomId = filter_input(
    INPUT_GET,
    'room_id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($roomId === false || $roomId === null) {
    stopWithMessage(
        400,
        'Invalid Room',
        'The selected room is missing or invalid.',
        'libraries.php',
        'Back to Libraries'
    );
}


// Retrieve selected room and library.
try {

    $stmt = $pdo->prepare(
        "SELECT r.room_id, r.room_number, r.min_capacity, r.max_capacity,
                l.library_id, l.library_name
         FROM rooms r
         INNER JOIN libraries l ON l.library_id = r.library_id
         WHERE r.room_id = ?"
    );

    $stmt->execute([$roomId]);
    $room = $stmt->fetch();

} catch (PDOException $e) {

    error_log('booking.php DB error: ' . $e->getMessage());

    stopWithMessage(
        500,
        'Database Error',
        'The room details could not be loaded. Please try again later.'
    );
}


// Check if room exists.
if (!$room) {
    stopWithMessage(
        404,
        'Room Not Found',
        'The selected room does not exist.',
        'libraries.php',
        'Back to Libraries'
    );
}


// Generate CSRF token.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// Retrieve previous validation errors and form values.
$errors = $_SESSION['booking_errors'] ?? [];
$old = $_SESSION['booking_old'] ?? [];

unset($_SESSION['booking_errors'], $_SESSION['booking_old']);

$today = date('Y-m-d');

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Book a Discussion Room | University of Baguio</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Centralized Stylesheet -->
    <link rel="stylesheet" href="style.css">

</head>

<body>

<div class="ub-layout">

    <!-- SIDEBAR -->

    <aside class="ub-sidebar">

        <div class="ub-sidebar-header">

            <small class="ub-sidebar-university">
                UNIVERSITY OF
            </small>

            <div class="ub-brand">
                BAGUIO
            </div>

            <div class="ub-brand-subtitle">
                STUDY ROOM BOOKING
            </div>

        </div>


        <!-- USER PROFILE -->

        <div class="ub-user-profile">

            <strong>
                <?= h($userName) ?>
            </strong>

            <small>
                <?= h(ucfirst($role)) ?> Account
            </small>

        </div>


        <!-- NAVIGATION -->

        <nav>

            <a href="dashboard.php">
                <span>▣</span>
                Dashboard
            </a>

            <a href="libraries.php" class="active">
                <span>▦</span>
                Browse Libraries
            </a>

            <a href="dashboard.php#my-bookings">
                <span>☷</span>
                My Bookings
            </a>

        </nav>


        <!-- SIDEBAR FOOTER -->

        <div class="ub-sidebar-bottom">

            <a href="logout.php" class="ub-nav-link">
                <span>⇥</span>
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

                    <h1 class="ub-page-title">
                        Book a Discussion Room
                    </h1>

                    <p class="ub-page-subtitle">
                        Complete the form below to submit your room booking request.
                    </p>

                </div>

                <span class="ub-role-label">
                    <?= h(ucfirst($role)) ?>
                </span>

            </div>


            <!-- WELCOME BANNER -->

            <section class="ub-booking-banner">

                <h2>
                    Reserve Your Study Space
                </h2>

                <p>
                    Provide your preferred date, time, and purpose
                    for using the discussion room.
                </p>

            </section>


            <!-- VALIDATION ERRORS -->

            <?php if ($errors): ?>

                <div class="alert alert-danger" role="alert">

                    <strong>Please fix the following:</strong>

                    <ul class="mb-0 mt-2">

                        <?php foreach ($errors as $error): ?>

                            <li><?= h($error) ?></li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>


            <!-- SELECTED ROOM -->

            <section class="ub-booking-card">

                <div class="ub-booking-card-header">
                    Selected Room
                </div>

                <div class="ub-booking-card-body">

                    <div class="ub-booking-room-details">


                        <!-- LIBRARY -->

                        <div class="ub-booking-detail-box">

                            <div class="ub-booking-detail-label">
                                LIBRARY
                            </div>

                            <div class="ub-booking-detail-value">
                                <?= h($room['library_name']) ?>
                            </div>

                        </div>


                        <!-- ROOM NUMBER -->

                        <div class="ub-booking-detail-box">

                            <div class="ub-booking-detail-label">
                                ROOM NUMBER
                            </div>

                            <div class="ub-booking-detail-value">
                                <?= h($room['room_number']) ?>
                            </div>

                        </div>


                        <!-- CAPACITY -->

                        <div class="ub-booking-detail-box">

                            <div class="ub-booking-detail-label">
                                CAPACITY
                            </div>

                            <div class="ub-booking-detail-value">

                                <?= (int) $room['min_capacity'] ?>
                                –
                                <?= (int) $room['max_capacity'] ?>
                                people

                            </div>

                        </div>


                    </div>

                </div>

            </section>


            <!-- BOOKING FORM -->

            <section class="ub-booking-card">

                <div class="ub-booking-card-header">
                    Booking Details
                </div>

                <div class="ub-booking-card-body">

                    <form
                        method="post"
                        action="process_booking.php"
                        class="ub-booking-form"
                        novalidate
                    >

                        <!-- ROOM ID -->

                        <input
                            type="hidden"
                            name="room_id"
                            value="<?= (int) $room['room_id'] ?>"
                        >


                        <!-- CSRF TOKEN -->

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= h($_SESSION['csrf_token']) ?>"
                        >


                        <!-- BOOKING DATE -->

                        <div class="mb-4">

                            <label
                                for="booking_date"
                                class="form-label"
                            >
                                Booking Date
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                id="booking_date"
                                name="booking_date"
                                min="<?= h($today) ?>"
                                value="<?= h($old['booking_date'] ?? '') ?>"
                                required
                            >

                            <div class="ub-booking-form-note">
                                You can type the date manually or select it using the calendar icon.
                            </div>

                        </div>


                        <!-- BOOKING TIME -->

                        <div class="row">

                            <div class="col-md-6 mb-4">

                                <label
                                    for="start_time"
                                    class="form-label"
                                >
                                    Start Time
                                </label>

                                <input
                                    type="time"
                                    class="form-control"
                                    id="start_time"
                                    name="start_time"
                                    value="<?= h($old['start_time'] ?? '') ?>"
                                    required
                                >

                            </div>


                            <div class="col-md-6 mb-4">

                                <label
                                    for="end_time"
                                    class="form-label"
                                >
                                    End Time
                                </label>

                                <input
                                    type="time"
                                    class="form-control"
                                    id="end_time"
                                    name="end_time"
                                    value="<?= h($old['end_time'] ?? '') ?>"
                                    required
                                >

                            </div>

                        </div>


                        <!-- TOPIC OR PURPOSE -->

                        <div class="mb-4">

                            <label
                                for="topic_purpose"
                                class="form-label"
                            >
                                Topic or Purpose
                            </label>

                            <textarea
                                class="form-control"
                                id="topic_purpose"
                                name="topic_purpose"
                                rows="4"
                                maxlength="255"
                                placeholder="Explain why you need to use this discussion room..."
                                required
                            ><?= h($old['topic_purpose'] ?? '') ?></textarea>

                            <div class="ub-booking-form-note">
                                Maximum of 255 characters.
                            </div>

                        </div>


                        <!-- FORM ACTIONS -->

                        <div class="ub-booking-actions">

                            <button
                                type="submit"
                                class="btn-ub"
                            >
                                Submit Booking →
                            </button>

                            <a
                                href="rooms.php?library_id=<?= (int) $room['library_id'] ?>"
                                class="btn-ub-outline"
                            >
                                Back to Rooms
                            </a>

                        </div>

                    </form>

                </div>

            </section>


            <!-- FOOTER -->

            <footer class="ub-footer">

                © <?= date('Y') ?> University of Baguio.
                All rights reserved.

                <br>

                Study Room Booking System

            </footer>


        </div>

    </main>

</div>

</body>
</html>
