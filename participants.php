
<?php
require_once __DIR__ . '/dbconn.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function stop_page($message, $code = 403)
{
    http_response_code($code);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Participants - University of Baguio</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <main class="ub-auth-page">
            <div class="ub-auth-card">
                <div class="ub-auth-header">
                    <div class="ub-sidebar-university">UNIVERSITY OF</div>
                    <div class="ub-brand">BAGUIO</div>
                    <p>STUDY ROOM BOOKING</p>
                    <h1>Unable to Continue</h1>
                </div>

                <div class="alert alert-danger">
                    <?= e($message) ?>
                </div>

                <a href="dashboard.php" class="btn-ub w-100">
                    Return to Dashboard
                </a>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}

// Require login.
if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$userId = (string) $_SESSION['user_id'];
$role = strtolower((string) $_SESSION['role']);
$fullName = $_SESSION['full_name'] ?? 'User';

if ($role !== 'student' && $role !== 'employee') {
    stop_page('Only students or employees can manage participants.');
}

// Dynamic participant number label.
$participantNumberLabel = ($role === 'employee')
    ? 'Employee Number'
    : 'Student Number';

$participantNumberLabelLower = strtolower($participantNumberLabel);

// Validate booking ID.
$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$bookingId) {
    stop_page('Invalid or missing booking ID.', 400);
}

// Get booking details, room capacity, and confirm ownership.
$stmt = $pdo->prepare(
    'SELECT b.booking_id, b.student_id, b.employee_id, b.topic_purpose,
            b.booking_date, b.start_time, b.end_time, b.status,
            r.min_capacity, r.max_capacity
     FROM bookings b
     INNER JOIN rooms r ON r.room_id = b.room_id
     WHERE b.booking_id = :booking_id'
);

$stmt->execute([':booking_id' => $bookingId]);
$booking = $stmt->fetch();

if (!$booking) {
    stop_page('Booking not found.', 404);
}

$ownerValue = ($role === 'student')
    ? $booking['student_id']
    : $booking['employee_id'];

if ($ownerValue === null || (string) $ownerValue !== $userId) {
    stop_page('You are not allowed to manage participants for this booking.');
}

// FIX 1: Only pending bookings can still be changed.
$isLocked = ($booking['status'] !== 'pending');

// FIX 3: Make sure a CSRF token exists for the form.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// FIX 5: Use the room's own capacity instead of a fixed number.
$minParticipants = max(0, (int) $booking['min_capacity']);
$maxParticipants = (int) $booking['max_capacity'];

if ($maxParticipants < 1) {
    $maxParticipants = 10; // Fallback if the room has no capacity set.
}

// Load saved participants.
function load_participants(PDO $pdo, int $bookingId): array
{
    $stmt = $pdo->prepare(
        'SELECT line_number, participant_name, student_no, department
         FROM booking_participants
         WHERE booking_id = :booking_id
         ORDER BY line_number'
    );

    $stmt->execute([':booking_id' => $bookingId]);

    return $stmt->fetchAll();
}

$errors = [];
$oldRows = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    if ($isLocked) {
        // FIX 1: Block changes after the admin has processed the booking.
        $errors[] = 'This booking is already ' . $booking['status'] . ' and can no longer be changed.';
    } elseif (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        // FIX 3: Reject requests without a valid token.
        $errors[] = 'Your session token is invalid or expired. Please reload the page and try again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {

    $submitted = $_POST['participants'] ?? [];

    if (!is_array($submitted)) {
        $submitted = [];
    }

    $valid = [];
    $rowNo = 0;

    foreach ($submitted as $row) {

        $rowNo++;

        if (!is_array($row)) {
            continue;
        }

        $name = trim((string) ($row['name'] ?? ''));
        $sno = trim((string) ($row['student_no'] ?? ''));
        $dept = trim((string) ($row['department'] ?? ''));

        $oldRows[] = [
            'name' => $name,
            'student_no' => $sno,
            'department' => $dept
        ];

        // Ignore completely empty rows.
        if ($name === '' && $sno === '' && $dept === '') {
            continue;
        }

        if ($name === '') {
            $errors[] = "Row $rowNo: participant name is required.";
        } elseif (mb_strlen($name) > 100) {
            $errors[] = "Row $rowNo: participant name is too long (max 100 characters).";
        }

        if ($sno === '') {
            $errors[] = "Row $rowNo: $participantNumberLabelLower is required.";
        } elseif (!preg_match('/^[A-Za-z0-9\-]{1,20}$/', $sno)) {
            $errors[] = "Row $rowNo: $participantNumberLabelLower may only use letters, numbers, and dashes (max 20).";
        }

        if ($dept === '') {
            $errors[] = "Row $rowNo: department is required.";
        } elseif (mb_strlen($dept) > 100) {
            $errors[] = "Row $rowNo: department is too long (max 100 characters).";
        }

        $valid[] = [
            'name' => $name,
            'student_no' => $sno,
            'department' => $dept
        ];
    }

    if (!$errors && count($valid) === 0) {
        $errors[] = 'Please enter at least one participant.';
    }

    if (!$errors) {

        try {
            $pdo->beginTransaction();

            // Lock existing participant line numbers.
            $stmt = $pdo->prepare(
                'SELECT line_number
                 FROM booking_participants
                 WHERE booking_id = :booking_id
                 FOR UPDATE'
            );

            $stmt->execute([':booking_id' => $bookingId]);

            $used = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (count($used) + count($valid) > $maxParticipants) {

                $pdo->rollBack();

                $left = max(0, $maxParticipants - count($used));

                $errors[] = "A booking can only have $maxParticipants participants. You can add $left more.";

            } else {

                $free = [];

                for ($i = 1; $i <= $maxParticipants; $i++) {
                    if (!in_array($i, $used, true)) {
                        $free[] = $i;
                    }
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO booking_participants
                        (booking_id, line_number, participant_name, student_no, department)
                     VALUES
                        (:booking_id, :line_number, :name, :student_no, :department)'
                );

                foreach ($valid as $index => $participant) {

                    $stmt->execute([
                        ':booking_id' => $bookingId,
                        ':line_number' => $free[$index],
                        ':name' => $participant['name'],
                        ':student_no' => $participant['student_no'],
                        ':department' => $participant['department']
                    ]);
                }

                $pdo->commit();

                // FIX 5: Stay on this page until the room minimum is reached.
                $total = count($used) + count($valid);

                if ($total < $minParticipants) {
                    header('Location: participants.php?booking_id=' . (int) $bookingId . '&saved=1');
                } else {
                    header('Location: equipment.php?booking_id=' . (int) $bookingId);
                }
                exit;
            }

        } catch (PDOException $ex) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $sqlState = (string) ($ex->errorInfo[0] ?? $ex->getCode());
            $driverCode = (int) ($ex->errorInfo[1] ?? 0);
            $dbMessage = (string) ($ex->errorInfo[2] ?? '');

            if ($sqlState === '45000' || $driverCode === 1644) {
                $errors[] = 'The database rejected the participants: ' . $dbMessage;
            } elseif ($driverCode === 1062) {
                $errors[] = 'A participant line number is already used. Please try again.';
            } else {
                error_log($ex->getMessage());
                $errors[] = 'Could not save the participants. Please try again.';
            }
        }
    }
}

$saved = load_participants($pdo, (int) $bookingId);
$remaining = max(0, $maxParticipants - count($saved));
$belowMin = count($saved) < $minParticipants;
$justSaved = isset($_GET['saved']) && !$errors;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Booking Participants - University of Baguio</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="ub-layout">

    <!-- SIDEBAR -->

    <aside class="ub-sidebar">

        <div class="ub-sidebar-header">

            <div class="ub-sidebar-university">UNIVERSITY OF</div>

            <div class="ub-brand">BAGUIO</div>

            <div class="ub-brand-subtitle">
                STUDY ROOM BOOKING
            </div>

        </div>

        <div class="ub-user-profile">
            <strong><?= e($fullName) ?></strong>
            <small><?= e(ucfirst($role)) ?> Account</small>
        </div>

        <nav>

            <a href="dashboard.php">
                <span>▣</span> Dashboard
            </a>

            <a href="libraries.php">
                <span>▦</span> Browse Libraries
            </a>

            <a href="dashboard.php#my-bookings">
                <span>▤</span> My Bookings
            </a>

        </nav>

        <div class="ub-sidebar-bottom">
            <nav>
                <a href="logout.php">
                    <span>↪</span> Logout
                </a>
            </nav>
        </div>

    </aside>

    <!-- MAIN CONTENT -->

    <main class="ub-main">

        <div class="ub-content">

            <!-- PAGE HEADING -->

            <div class="ub-content-header">

                <div>
                    <h1 class="ub-page-title">Booking Participants</h1>

                    <p class="ub-page-subtitle">
                        Add the people who will join your discussion room booking.
                    </p>
                </div>

                <span class="ub-role-label">
                    <?= e(ucfirst($role)) ?> Account
                </span>

            </div>

            <!-- WELCOME BANNER -->

            <div class="ub-banner">

                <h2>Manage Your Participants</h2>

                <p>
                    Add your group members before continuing to equipment selection.
                </p>

            </div>

            <!-- BOOKING SUMMARY -->

            <section class="ub-card ub-participants-card">

                <div class="ub-participants-card-header">
                    <h2>Booking #<?= (int) $booking['booking_id'] ?> Summary</h2>
                </div>

                <div class="ub-participants-card-body">

                    <div class="row g-3">

                        <div class="col-md-4">
                            <div class="ub-participant-summary-item">
                                <small>BOOKING PURPOSE</small>
                                <strong><?= e($booking['topic_purpose']) ?></strong>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="ub-participant-summary-item">
                                <small>BOOKING DATE</small>
                                <strong><?= e($booking['booking_date']) ?></strong>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="ub-participant-summary-item">
                                <small>BOOKING TIME</small>
                                <strong>
                                    <?= e($booking['start_time']) ?> -
                                    <?= e($booking['end_time']) ?>
                                </strong>
                            </div>
                        </div>

                    </div>

                </div>

            </section>

            <!-- STATUS MESSAGES -->

            <?php if ($isLocked): ?>

                <div class="alert alert-info" role="alert">
                    This booking is <strong><?= e($booking['status']) ?></strong>.
                    Participants can no longer be added or changed.
                </div>

            <?php else: ?>

                <?php if ($justSaved): ?>
                    <div class="alert alert-success" role="alert">
                        Participants saved.
                    </div>
                <?php endif; ?>

                <?php if ($belowMin): ?>
                    <div class="alert alert-warning" role="alert">
                        This room needs at least <strong><?= (int) $minParticipants ?></strong> participants.
                        You have <?= count($saved) ?>. Add <?= (int) ($minParticipants - count($saved)) ?> more
                        before an administrator can approve your booking.
                    </div>
                <?php endif; ?>

                <?php if (count($saved) === 0): ?>
                    <div class="alert alert-secondary" role="alert">
                        Add at least one participant within 15 minutes, or this booking
                        will be released so other users can book the time slot.
                    </div>
                <?php endif; ?>

            <?php endif; ?>

            <!-- VALIDATION ERRORS -->

            <?php if ($errors): ?>

                <div class="alert alert-danger" role="alert">

                    <strong>Please check the following:</strong>

                    <ul class="mb-0 mt-2">
                        <?php foreach (array_unique($errors) as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>

                </div>

            <?php endif; ?>

            <!-- SAVED PARTICIPANTS -->

            <section class="ub-card ub-participants-card">

                <div class="ub-participants-card-header">
                    <h2>Saved Participants</h2>
                </div>

                <div class="ub-participants-card-body">

                    <div class="ub-participants-list-heading">

                        <strong>Participant List</strong>

                        <span class="ub-participants-count">
                            <?= count($saved) ?> / <?= (int) $maxParticipants ?> Participants
                            (minimum <?= (int) $minParticipants ?>)
                        </span>

                    </div>

                    <?php if ($saved): ?>

                        <div class="table-responsive">

                            <table class="table table-bordered table-hover">

                                <thead>
                                    <tr>
                                        <th>Line</th>
                                        <th>Participant Name</th>
                                        <th><?= e($participantNumberLabel) ?></th>
                                        <th>Department</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($saved as $participant): ?>

                                        <tr>
                                            <td><?= e($participant['line_number']) ?></td>
                                            <td><?= e($participant['participant_name']) ?></td>
                                            <td><?= e($participant['student_no']) ?></td>
                                            <td><?= e($participant['department']) ?></td>
                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php else: ?>

                        <div class="ub-empty-state bg-light rounded">
                            <strong>No participants saved yet.</strong>

                            <p class="mb-0 mt-2">
                                Add your group members using the form below.
                            </p>
                        </div>

                    <?php endif; ?>

                </div>

            </section>

            <!-- ADD PARTICIPANTS FORM -->

            <?php if ($isLocked): ?>

                <a href="dashboard.php" class="btn-ub">
                    Back to Dashboard
                </a>

            <?php elseif ($remaining > 0): ?>

                <section class="ub-card ub-participants-card">

                    <div class="ub-participants-card-header">
                        <h2>Add Participants</h2>
                    </div>

                    <div class="ub-participants-card-body">

                        <h3 class="ub-section-title">
                            Participant Information
                        </h3>

                        <p class="ub-form-help mb-3">
                            You can add up to <?= $remaining ?> more participant(s).
                            Leave a row completely empty if you do not need it.
                            All three fields are required for every participant entered.
                        </p>

                        <form
                            method="post"
                            action="participants.php?booking_id=<?= (int) $bookingId ?>"
                            id="participantsForm"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($_SESSION['csrf_token']) ?>"
                            >

                            <?php for ($i = 0; $i < $remaining; $i++): ?>

                                <?php
                                $old = $oldRows[$i] ?? [
                                    'name' => '',
                                    'student_no' => '',
                                    'department' => ''
                                ];
                                ?>

                                <div class="ub-participant-form-row">

                                    <div class="ub-participant-row-title">
                                        Participant <?= $i + count($saved) + 1 ?>
                                    </div>

                                    <div class="row g-3">

                                        <div class="col-md-4">

                                            <label class="form-label">
                                                Participant Name
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                name="participants[<?= $i ?>][name]"
                                                placeholder="Enter participant name"
                                                maxlength="100"
                                                aria-label="Participant name"
                                                value="<?= e($old['name']) ?>"
                                            >

                                        </div>

                                        <div class="col-md-3">

                                            <label class="form-label">
                                                <?= e($participantNumberLabel) ?>
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                name="participants[<?= $i ?>][student_no]"
                                                placeholder="Enter number"
                                                maxlength="20"
                                                pattern="[A-Za-z0-9\-]{1,20}"
                                                title="Use letters, numbers, and dashes only, up to 20 characters."
                                                aria-label="<?= e($participantNumberLabel) ?>"
                                                value="<?= e($old['student_no']) ?>"
                                            >

                                        </div>

                                        <div class="col-md-5">

                                            <label class="form-label">
                                                Department
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                name="participants[<?= $i ?>][department]"
                                                placeholder="Enter department"
                                                maxlength="100"
                                                aria-label="Department"
                                                value="<?= e($old['department']) ?>"
                                            >

                                        </div>

                                    </div>

                                </div>

                            <?php endfor; ?>

                            <div class="ub-form-actions">

                                <button type="submit" class="btn-ub">
                                    Save Participants and Continue →
                                </button>

                                <?php if (!$belowMin && count($saved) > 0): ?>
                                    <a
                                        href="equipment.php?booking_id=<?= (int) $bookingId ?>"
                                        class="btn-ub-outline"
                                    >
                                        Skip to Equipment
                                    </a>
                                <?php endif; ?>

                                <a href="dashboard.php" class="btn-ub-outline">
                                    Dashboard
                                </a>

                            </div>

                        </form>

                    </div>

                </section>

            <?php else: ?>

                <div class="alert alert-info">
                    This booking already has the maximum of <?= (int) $maxParticipants ?> participants.
                </div>

                <a
                    href="equipment.php?booking_id=<?= (int) $bookingId ?>"
                    class="btn-ub"
                >
                    Continue to Equipment →
                </a>

            <?php endif; ?>

            <footer class="ub-footer">
                © 2026 University of Baguio. All rights reserved.
                <br>
                Study Room Booking System
            </footer>

        </div>

    </main>

</div>

<script>
document.querySelectorAll('.ub-participant-form-row').forEach(function (row) {

    const fields = row.querySelectorAll('input');

    function updateRequired() {

        const hasValue = Array.from(fields).some(function (field) {
            return field.value.trim() !== '';
        });

        fields.forEach(function (field) {
            field.required = hasValue;
        });
    }

    fields.forEach(function (field) {
        field.addEventListener('input', updateRequired);
    });

    updateRequired();
});
</script>

</body>
</html>
