<?php
require_once __DIR__ . '/dbconn.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function stop_page($message, $code = 403)
{
    http_response_code($code);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Participants</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head><body><div class="container mt-5"><div class="alert alert-danger">'
        . e($message) . '</div></div></body></html>';
    exit;
}

// ---------- 1. Check login ----------
if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    stop_page('You must be logged in to manage participants.');
}

$userId   = (string)$_SESSION['user_id'];
$role     = strtolower((string)$_SESSION['role']);
$fullName = $_SESSION['full_name'] ?? '';

if ($role !== 'student' && $role !== 'employee') {
    stop_page('Only students or employees can manage participants.');
}

// ---------- 2. Read and validate booking_id ----------
$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
if (!$bookingId) {
    stop_page('Invalid or missing booking ID.', 400);
}

// ---------- 3. Get booking and check owner ----------
$stmt = $pdo->prepare(
    'SELECT booking_id, student_id, employee_id, topic_purpose, booking_date, start_time, end_time, status
     FROM bookings WHERE booking_id = :booking_id'
);
$stmt->execute([':booking_id' => $bookingId]);
$booking = $stmt->fetch();

if (!$booking) {
    stop_page('Booking not found.', 404);
}

$ownerValue = ($role === 'student') ? $booking['student_id'] : $booking['employee_id'];
if ($ownerValue === null || (string)$ownerValue !== $userId) {
    stop_page('You are not allowed to manage participants for this booking.');
}

// ---------- Helper: load saved participants ----------
function load_participants(PDO $pdo, int $bookingId): array
{
    $stmt = $pdo->prepare(
        'SELECT line_number, participant_name, student_no, department
         FROM booking_participants WHERE booking_id = :booking_id ORDER BY line_number'
    );
    $stmt->execute([':booking_id' => $bookingId]);
    return $stmt->fetchAll();
}

$maxParticipants = 10;
$errors  = [];
$success = '';
$oldRows = [];

// ---------- 4. Handle form submit ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $name = trim((string)($row['name'] ?? ''));
        $sno  = trim((string)($row['student_no'] ?? ''));
        $dept = trim((string)($row['department'] ?? ''));

        $oldRows[] = ['name' => $name, 'student_no' => $sno, 'department' => $dept];

        // Skip completely empty rows
        if ($name === '' && $sno === '' && $dept === '') {
            continue;
        }

        if ($name === '') {
            $errors[] = "Row $rowNo: participant name is required.";
        } elseif (mb_strlen($name) > 100) {
            $errors[] = "Row $rowNo: participant name is too long (max 100 characters).";
        }

        if ($sno === '') {
            $errors[] = "Row $rowNo: student number is required.";
        } elseif (!preg_match('/^[A-Za-z0-9\-]{1,20}$/', $sno)) {
            $errors[] = "Row $rowNo: student number may only use letters, numbers, and dashes (max 20).";
        }

        if (mb_strlen($dept) > 100) {
            $errors[] = "Row $rowNo: department is too long (max 100 characters).";
        }

        $valid[] = [
            'name'       => $name,
            'student_no' => $sno,
            'department' => ($dept === '' ? null : $dept),
        ];
    }

    if (!$errors && count($valid) === 0) {
        $errors[] = 'Please enter at least one participant.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Lock and read the line numbers already used
            $stmt = $pdo->prepare(
                'SELECT line_number FROM booking_participants WHERE booking_id = :booking_id FOR UPDATE'
            );
            $stmt->execute([':booking_id' => $bookingId]);
            $used = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (count($used) + count($valid) > $maxParticipants) {
                $pdo->rollBack();
                $left = $maxParticipants - count($used);
                $errors[] = "A booking can only have $maxParticipants participants. You can add $left more.";
            } else {
                // Find free line numbers between 1 and 10
                $free = [];
                for ($i = 1; $i <= $maxParticipants; $i++) {
                    if (!in_array($i, $used, true)) {
                        $free[] = $i;
                    }
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO booking_participants
                        (booking_id, line_number, participant_name, student_no, department)
                     VALUES (:booking_id, :line_number, :name, :student_no, :department)'
                );

                foreach ($valid as $index => $p) {
                    $stmt->execute([
                        ':booking_id'  => $bookingId,
                        ':line_number' => $free[$index],
                        ':name'        => $p['name'],
                        ':student_no'  => $p['student_no'],
                        ':department'  => $p['department'],
                    ]);
                }

                $pdo->commit();
                $success = count($valid) . ' participant(s) saved.';
                $oldRows = [];
            }
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $sqlState   = (string)($ex->errorInfo[0] ?? $ex->getCode());
            $driverCode = (int)($ex->errorInfo[1] ?? 0);
            $dbMessage  = (string)($ex->errorInfo[2] ?? '');

            if ($sqlState === '45000' || $driverCode === 1644) {
                // Error raised by a database trigger (SIGNAL)
                $errors[] = 'The database rejected the participants: ' . $dbMessage;
            } elseif ($driverCode === 1062) {
                $errors[] = 'A participant line number is already used. Please try again.';
            } else {
                $errors[] = 'Could not save the participants. Please try again.';
            }
        }
    }
}

// ---------- 5. Load data for display ----------
$saved     = load_participants($pdo, $bookingId);
$remaining = max(0, $maxParticipants - count($saved));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Participants</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container my-4">
    <h1 class="h3">Participants for Booking #<?= e($booking['booking_id']) ?></h1>
    <p class="text-muted mb-1">Logged in as: <?= e($fullName) ?> (<?= e($role) ?>)</p>
    <p class="mb-3">
        <strong>Purpose:</strong> <?= e($booking['topic_purpose']) ?><br>
        <strong>Date:</strong> <?= e($booking['booking_date']) ?>,
        <?= e($booking['start_time']) ?> - <?= e($booking['end_time']) ?>
    </p>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= e($success) ?>
            <a href="equipment.php?booking_id=<?= (int)$bookingId ?>" class="alert-link">Continue to equipment</a>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h2 class="h5">Saved participants (<?= count($saved) ?>/<?= $maxParticipants ?>)</h2>
    <?php if ($saved): ?>
        <table class="table table-bordered table-sm">
            <thead>
                <tr><th>Line</th><th>Name</th><th>Student No.</th><th>Department</th></tr>
            </thead>
            <tbody>
            <?php foreach ($saved as $s): ?>
                <tr>
                    <td><?= e($s['line_number']) ?></td>
                    <td><?= e($s['participant_name']) ?></td>
                    <td><?= e($s['student_no']) ?></td>
                    <td><?= e($s['department'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted">No participants saved yet.</p>
    <?php endif; ?>

    <?php if ($remaining > 0): ?>
        <h2 class="h5 mt-4">Add participants (up to <?= $remaining ?> more)</h2>
        <p class="text-muted">Leave a row empty if you do not need it.</p>
        <form method="post" action="participants.php?booking_id=<?= (int)$bookingId ?>">
            <?php for ($i = 0; $i < $remaining; $i++):
                $old = $oldRows[$i] ?? ['name' => '', 'student_no' => '', 'department' => ''];
            ?>
                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="participants[<?= $i ?>][name]"
                               placeholder="Participant name" maxlength="100" value="<?= e($old['name']) ?>">
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="participants[<?= $i ?>][student_no]"
                               placeholder="Student number" maxlength="20" value="<?= e($old['student_no']) ?>">
                    </div>
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="participants[<?= $i ?>][department]"
                               placeholder="Department (optional)" maxlength="100" value="<?= e($old['department']) ?>">
                    </div>
                </div>
            <?php endfor; ?>
            <button type="submit" class="btn btn-primary mt-2">Save Participants</button>
        </form>
    <?php else: ?>
        <div class="alert alert-info">This booking already has the maximum of <?= $maxParticipants ?> participants.</div>
    <?php endif; ?>

    <hr>
    <a href="equipment.php?booking_id=<?= (int)$bookingId ?>" class="btn btn-outline-secondary">Go to Equipment</a>
</div>
</body>
</html>
