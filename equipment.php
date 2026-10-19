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
        . '<title>Equipment</title>'
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
    stop_page('Only students or employees can manage equipment.');
}

$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$bookingId) {
    stop_page('Invalid or missing booking ID.', 400);
}

// Retrieve the booking and verify that it belongs to the logged-in user.
$stmt = $pdo->prepare(
    'SELECT booking_id, student_id, employee_id, topic_purpose,
            booking_date, start_time, end_time
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
    stop_page('You are not allowed to manage equipment for this booking.');
}

// Load the available equipment.
$equipmentList = $pdo->query(
    'SELECT equipment_id, equipment_name FROM equipment ORDER BY equipment_name'
)->fetchAll();

$validIds = [];
foreach ($equipmentList as $equipment) {
    $validIds[(int) $equipment['equipment_id']] = true;
}

$errors = [];
$maxQty = 100;
$posted = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = $_POST['selected'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    if (!is_array($selected)) {
        $selected = [];
    }
    if (!is_array($quantities)) {
        $quantities = [];
    }

    $toSave = [];

    foreach ($selected as $idRaw) {
        $id = filter_var($idRaw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);

        if (!$id || !isset($validIds[$id])) {
            $errors[] = 'An invalid equipment item was selected.';
            continue;
        }

        $qtyRaw = $quantities[$id] ?? '';
        $posted[$id] = $qtyRaw;
        $qty = filter_var($qtyRaw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => $maxQty]
        ]);

        if (!$qty) {
            $errors[] = 'Quantity for equipment #' . (int) $id . ' must be a whole number from 1 to ' . $maxQty . '.';
            continue;
        }

        $toSave[$id] = $qty;
    }

    if (!$errors && count($toSave) === 0) {
        $errors[] = 'Please select at least one equipment item.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Insert selected items or update their quantities if already saved.
            $stmt = $pdo->prepare(
                'INSERT INTO booking_equipment (booking_id, equipment_id, quantity)
                 VALUES (:booking_id, :equipment_id, :quantity)
                 ON DUPLICATE KEY UPDATE quantity = :quantity_update'
            );

            foreach ($toSave as $equipmentId => $quantity) {
                $stmt->execute([
                    ':booking_id' => $bookingId,
                    ':equipment_id' => $equipmentId,
                    ':quantity' => $quantity,
                    ':quantity_update' => $quantity
                ]);
            }

            $pdo->commit();

            // After equipment saves successfully, return to the dashboard.
            header('Location: dashboard.php?message=equipment_saved');
            exit;

        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $sqlState = (string) ($ex->errorInfo[0] ?? $ex->getCode());
            $driverCode = (int) ($ex->errorInfo[1] ?? 0);
            $dbMessage = (string) ($ex->errorInfo[2] ?? '');

            if ($sqlState === '45000' || $driverCode === 1644) {
                $errors[] = 'The database rejected the equipment: ' . $dbMessage;
            } else {
                error_log($ex->getMessage());
                $errors[] = 'Could not save the equipment. Please try again.';
            }
        }
    }
}

// Retrieve equipment already saved for this booking.
$stmt = $pdo->prepare(
    'SELECT equipment_id, quantity
     FROM booking_equipment
     WHERE booking_id = :booking_id'
);
$stmt->execute([':booking_id' => $bookingId]);

$saved = [];
foreach ($stmt->fetchAll() as $row) {
    $saved[(int) $row['equipment_id']] = (int) $row['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Equipment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container my-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Equipment for Booking #<?php echo (int) $booking['booking_id']; ?></h1>
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
                <?php foreach (array_unique($errors) as $error): ?>
                    <li><?php echo e($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$equipmentList): ?>
        <div class="alert alert-info">No equipment is available right now.</div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="post" action="equipment.php?booking_id=<?php echo (int) $bookingId; ?>">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 90px;">Select</th>
                                    <th>Equipment</th>
                                    <th style="width: 180px;">Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equipmentList as $equipment): ?>
                                    <?php
                                    $id = (int) $equipment['equipment_id'];
                                    $isChecked = ($_SERVER['REQUEST_METHOD'] === 'POST')
                                        ? isset($posted[$id])
                                        : isset($saved[$id]);
                                    $qtyValue = $posted[$id] ?? ($saved[$id] ?? 1);
                                    ?>
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox"
                                                   class="form-check-input"
                                                   name="selected[]"
                                                   value="<?php echo $id; ?>"
                                                   id="eq<?php echo $id; ?>"
                                                   <?php echo $isChecked ? 'checked' : ''; ?>>
                                        </td>
                                        <td>
                                            <label for="eq<?php echo $id; ?>" class="mb-0">
                                                <?php echo e($equipment['equipment_name']); ?>
                                            </label>
                                        </td>
                                        <td>
                                            <input type="number"
                                                   class="form-control"
                                                   name="quantity[<?php echo $id; ?>]"
                                                   min="1"
                                                   max="<?php echo $maxQty; ?>"
                                                   step="1"
                                                   value="<?php echo e($qtyValue); ?>"
                                                   aria-label="Quantity for <?php echo e($equipment['equipment_name']); ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Equipment and Return to Dashboard</button>
                    <a href="participants.php?booking_id=<?php echo (int) $bookingId; ?>" class="btn btn-outline-secondary ms-2">Back to Participants</a>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
