<?php
session_start();
require_once __DIR__ . '/dbconn.php';
date_default_timezone_set('Asia/Manila');

$loginPage = 'login.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function stopWithMessage(int $code, string $title, string $message, ?string $linkUrl = null, ?string $linkText = null): void
{
    http_response_code($code);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h($title) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
    <div class="container py-5" style="max-width: 640px;">
        <div class="alert alert-danger">
            <h4 class="alert-heading"><?= h($title) ?></h4>
            <p class="mb-0"><?= h($message) ?></p>
        </div>
        <?php if ($linkUrl): ?>
            <a href="<?= h($linkUrl) ?>" class="btn btn-secondary"><?= h($linkText ?? 'Back') ?></a>
        <?php endif; ?>
    </div>
    </body>
    </html>
    <?php
    exit;
}

function normalizeTime(string $time): ?string
{
    if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $time, $m)) {
        return sprintf('%s:%s:%s', $m[1], $m[2], $m[3] ?? '00');
    }
    return null;
}

function backToForm(int $roomId, array $errors, array $old): void
{
    $_SESSION['booking_errors'] = $errors;
    $_SESSION['booking_old']    = $old;
    header('Location: booking.php?room_id=' . $roomId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    stopWithMessage(405, 'Invalid request', 'This page can only be reached by submitting the booking form.');
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    stopWithMessage(403, 'Login required', 'Please log in to book a room.', $loginPage, 'Go to login');
}

$role   = $_SESSION['role'];
$userId = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);

if (!in_array($role, ['student', 'employee'], true) || $userId === false || $userId < 1) {
    stopWithMessage(403, 'Access denied', 'Only students and employees can book discussion rooms.');
}

$postedToken = (string)($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
    stopWithMessage(403, 'Invalid request', 'Your session token is invalid or expired. Please reopen the booking form and try again.');
}

$roomId = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($roomId === false || $roomId === null) {
    stopWithMessage(400, 'Invalid room', 'The selected room is missing or invalid.');
}

$bookingDate  = trim((string)($_POST['booking_date'] ?? ''));
$startRaw     = trim((string)($_POST['start_time'] ?? ''));
$endRaw       = trim((string)($_POST['end_time'] ?? ''));
$topicPurpose = trim((string)($_POST['topic_purpose'] ?? ''));

$old = [
    'booking_date'  => $bookingDate,
    'start_time'    => $startRaw,
    'end_time'      => $endRaw,
    'topic_purpose' => $topicPurpose,
];

$errors = [];
$today  = date('Y-m-d');

$dateObj = DateTime::createFromFormat('!Y-m-d', $bookingDate);
if ($bookingDate === '') {
    $errors[] = 'Booking date is required.';
} elseif (!$dateObj || $dateObj->format('Y-m-d') !== $bookingDate) {
    $errors[] = 'Booking date is not a valid date.';
} elseif ($bookingDate < $today) {
    $errors[] = 'Booking date cannot be in the past.';
}

$startTime = null;
$endTime   = null;

if ($startRaw === '') {
    $errors[] = 'Start time is required.';
} elseif (($startTime = normalizeTime($startRaw)) === null) {
    $errors[] = 'Start time is not a valid time.';
}

if ($endRaw === '') {
    $errors[] = 'End time is required.';
} elseif (($endTime = normalizeTime($endRaw)) === null) {
    $errors[] = 'End time is not a valid time.';
}

if ($startTime !== null && $endTime !== null) {
    if ($endTime <= $startTime) {
        $errors[] = 'End time must be later than start time.';
    }
    if ($bookingDate === $today && $startTime <= date('H:i:s')) {
        $errors[] = 'Start time has already passed for today.';
    }
}

if ($topicPurpose === '') {
    $errors[] = 'Topic or purpose is required.';
} elseif (mb_strlen($topicPurpose) > 255) {
    $errors[] = 'Topic or purpose must not exceed 255 characters.';
}

if ($errors) {
    backToForm($roomId, $errors, $old);
}

try {
    $stmt = $pdo->prepare('SELECT room_id FROM rooms WHERE room_id = ?');
    $stmt->execute([$roomId]);
    $roomExists = $stmt->fetch();

    if (!$roomExists) {
        stopWithMessage(404, 'Room not found', 'The selected room does not exist.');
    }

    $stmt = $pdo->prepare(
        "SELECT booking_id FROM bookings
         WHERE room_id = ?
           AND booking_date = ?
           AND status IN ('pending', 'approved')
           AND ? < end_time
           AND ? > start_time
         LIMIT 1"
    );
    $stmt->execute([$roomId, $bookingDate, $startTime, $endTime]);
    $overlap = $stmt->fetch();

    if ($overlap) {
        backToForm($roomId, ['That time slot overlaps with an existing pending or approved booking for this room. Please choose a different time.'], $old);
    }

    $studentId  = ($role === 'student')  ? $userId : null;
    $employeeId = ($role === 'employee') ? $userId : null;

    $stmt = $pdo->prepare(
        "INSERT INTO bookings
            (room_id, student_id, employee_id, administrator_id,
             topic_purpose, booking_date, start_time, end_time, status, created_at)
         VALUES (?, ?, ?, NULL, ?, ?, ?, ?, 'pending', NOW())"
    );
    $stmt->execute([
        $roomId,
        $studentId,
        $employeeId,
        $topicPurpose,
        $bookingDate,
        $startTime,
        $endTime,
    ]);
    $bookingId = (int)$pdo->lastInsertId();

} catch (PDOException $e) {
    $driverCode = (int)($e->errorInfo[1] ?? 0);

    if ((string)$e->getCode() === '45000' || $driverCode === 1644) {
        backToForm($roomId, ['That time slot was just taken by another booking. Please choose a different time.'], $old);
    }

    error_log('process_booking.php DB error: ' . $e->getMessage());
    backToForm($roomId, ['Something went wrong while saving your booking. Please try again.'], $old);
}

unset($_SESSION['booking_errors'], $_SESSION['booking_old']);
header('Location: participants.php?booking_id=' . $bookingId);
exit;
