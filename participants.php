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
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Participants</title>'
        . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">'
        . '</head><body><div class="container mt-5"><div class="alert alert-danger">'
        . e($message) . '</div><a class="btn btn-outline-secondary" href="dashboard.php">Dashboard</a></div></body></html>';
    exit;
}

// Require login.
if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$userId = (string) $_SESSION['user_id'];
$role = strtolower((string) $_SESSION['role']);
$fullName = $_SESSION['full_name'] ?? '';

if ($role !== 'student' && $role !== 'employee') {
    stop_page('Only students or employees can manage participants.');
}

$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$bookingId) {
    stop_page('Invalid or missing booking ID.', 400);
}

// Get booking details and confirm that the current user owns it.
$stmt = $pdo->prepare(
    'SELECT booking_id, student_id, employee_id, topic_purpose,
            booking_date, start_time, end_time, status
     FROM bookings
     WHERE booking_id = :booking_id'
);
$stmt->execute([':booking_id' => $bookingId]);
$booking = $stmt->fetch();

if (!$booking) {
    stop_page('Booking not found.', 404);
}

$ownerValue = ($role === 'student') ? $booking['student_id'] : $booking['employee_id'];
if ($ownerValue === null || (string) $ownerValue !== $userId) {
    stop_page('You are not allowed to manage participants for this booking.');
}

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

$maxParticipants = 10;
$errors = [];
$oldRows = [];

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

        $name = trim((string) ($row['name'] ?? ''));
        $sno = trim((string) ($row['student_no'] ?? ''));
        $dept = trim((string) ($row['department'] ?? ''));

        $oldRows[] = [
            'name' => $name,
            'student_no' => $sno,
            'department' => $dept
        ];

        // Completely empty rows are allowed and ignored.
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

        // Department is required for every row that is being used.
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

            // Lock existing line numbers so we can assign unused numbers.
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

                // After participants save successfully, continue to equipment for this booking.
                header('Location: equipment.php?booking_id=' . (int) $bookingId);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Participants</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="h3 mb-0">Participants for Booking #<?php echo (int) $booking['booking_id']; ?></h1>
        <a href="dashboard.php" class="btn btn-outline-secondary">Dashboard</a>
    </div>

    <p class="text-muted mb-1">Logged in as: <?php echo e($fullName); ?> (<?php echo e($role); ?>)</p>
    <p class="mb-3">
        <strong>Purpose:</strong> <?php echo e($booking['topic_purpose']); ?><br>
        <strong>Date:</strong> <?php echo e($booking['booking_date']); ?>,
        <?php echo e($booking['start_time']); ?> - <?php echo e($booking['end_time']); ?>
    </p>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach (array_unique($errors) as $err): ?>
                    <li><?php echo e($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5">Saved participants (<?php echo count($saved); ?>/<?php echo $maxParticipants; ?>)</h2>
            <?php if ($saved): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Line</th><th>Name</th><th>Student No.</th><th>Department</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($saved as $participant): ?>
                                <tr>
                                    <td><?php echo e($participant['line_number']); ?></td>
                                    <td><?php echo e($participant['participant_name']); ?></td>
                                    <td><?php echo e($participant['student_no']); ?></td>
                                    <td><?php echo e($participant['department']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No participants saved yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($remaining > 0): ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Add participants (up to <?php echo $remaining; ?> more)</h2>
                <p class="text-muted">Leave a row completely empty if you do not need it. Name, student number, and department are required for each participant entered.</p>

                <form method="post" action="participants.php?booking_id=<?php echo (int) $bookingId; ?>" id="participantsForm">
                    <?php for ($i = 0; $i < $remaining; $i++): ?>
                        <?php
                        $old = $oldRows[$i] ?? [
                            'name' => '',
                            'student_no' => '',
                            'department' => ''
                        ];
                        ?>
                        <div class="row g-2 mb-2 participant-row">
                            <div class="col-md-4">
                                <input type="text" class="form-control"
                                       name="participants[<?php echo $i; ?>][name]"
                                       placeholder="Participant name" maxlength="100"
                                       aria-label="Participant name"
                                       value="<?php echo e($old['name']); ?>">
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control"
                                       name="participants[<?php echo $i; ?>][student_no]"
                                       placeholder="Student number" maxlength="20"
                                       pattern="[A-Za-z0-9\-]{1,20}"
                                       title="Use letters, numbers, and dashes only, up to 20 characters."
                                       aria-label="Student number"
                                       value="<?php echo e($old['student_no']); ?>">
                            </div>
                            <div class="col-md-5">
                                <input type="text" class="form-control"
                                       name="participants[<?php echo $i; ?>][department]"
                                       placeholder="Department" maxlength="100"
                                       aria-label="Department"
                                       value="<?php echo e($old['department']); ?>">
                            </div>
                        </div>
                    <?php endfor; ?>

                    <button type="submit" class="btn btn-primary mt-2">Save Participants and Continue</button>
                    <a href="dashboard.php" class="btn btn-outline-secondary mt-2">Dashboard</a>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">This booking already has the maximum of <?php echo $maxParticipants; ?> participants.</div>
        <a href="equipment.php?booking_id=<?php echo (int) $bookingId; ?>" class="btn btn-primary">Continue to Equipment</a>
    <?php endif; ?>
</div>
<script>
document.querySelectorAll('.participant-row').forEach(function (row) {
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
