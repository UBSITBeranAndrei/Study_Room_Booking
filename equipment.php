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
    <title>Equipment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head><body><div class="container mt-5"><div class="alert alert-danger">'
        . e($message) . '</div></div></body></html>';
    exit;
}

// ---------- 1. Check login ----------
if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    stop_page('You must be logged in to manage equipment.');
}

$userId   = (string)$_SESSION['user_id'];
$role     = strtolower((string)$_SESSION['role']);
$fullName = $_SESSION['full_name'] ?? '';

if ($role !== 'student' && $role !== 'employee') {
    stop_page('Only students or employees can manage equipment.');
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
    'SELECT booking_id, student_id, employee_id, topic_purpose, booking_date, start_time, end_time
     FROM bookings WHERE booking_id = :booking_id'
);
$stmt->execute([':booking_id' => $bookingId]);
$booking = $stmt->fetch();

if (!$booking) {
    stop_page('Booking not found.', 404);
}

$ownerValue = ($role === 'student') ? $booking['student_id'] : $booking['employee_id'];
if ($ownerValue === null || (string)$ownerValue !== $userId) {
    stop_page('You are not allowed to manage equipment for this booking.');
}

// ---------- Load all equipment ----------
$equipmentList = $pdo->query(
    'SELECT equipment_id, equipment_name FROM equipment ORDER BY equipment_name'
)->fetchAll();

$validIds = [];
foreach ($equipmentList as $eq) {
    $validIds[(int)$eq['equipment_id']] = true;
}

$errors  = [];
$success = '';
$maxQty  = 100;
$posted  = []; // equipment_id => quantity (to refill the form after an error)

// ---------- 4. Handle form submit ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected   = $_POST['selected'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    if (!is_array($selected))   { $selected = []; }
    if (!is_array($quantities)) { $quantities = []; }

    $toSave = [];

    foreach ($selected as $idRaw) {
        $id = filter_var($idRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
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
            $errors[] = "Quantity for equipment #$id must be a whole number from 1 to $maxQty.";
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

            // If (booking_id, equipment_id) already exists, update the quantity instead of failing
            $stmt = $pdo->prepare(
                'INSERT INTO booking_equipment (booking_id, equipment_id, quantity)
                 VALUES (:booking_id, :equipment_id, :quantity)
                 ON DUPLICATE KEY UPDATE quantity = :quantity_update'
            );
            foreach ($toSave as $equipmentId => $qty) {
                $stmt->execute([
                    ':booking_id'      => $bookingId,
                    ':equipment_id'    => $equipmentId,
                    ':quantity'        => $qty,
                    ':quantity_update' => $qty,
                ]);
            }

            $pdo->commit();
            $success = 'Equipment saved.';
            $posted = [];
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $sqlState   = (string)($ex->errorInfo[0] ?? $ex->getCode());
            $driverCode = (int)($ex->errorInfo[1] ?? 0);
            $dbMessage  = (string)($ex->errorInfo[2] ?? '');

            if ($sqlState === '45000' || $driverCode === 1644) {
                $errors[] = 'The database rejected the equipment: ' . $dbMessage;
            } else {
                $errors[] = 'Could not save the equipment. Please try again.';
            }
        }
    }
}

// ---------- 5. Load saved equipment for this booking ----------
$stmt = $pdo->prepare('SELECT equipment_id, quantity FROM booking_equipment WHERE booking_id = :booking_id');
$stmt->execute([':booking_id' => $bookingId]);

$saved = [];
foreach ($stmt->fetchAll() as $r) {
    $saved[(int)$r['equipment_id']] = (int)$r['quantity'];
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
<body>
<div class="container my-4">
    <h1 class="h3">Equipment for Booking #<?= e($booking['booking_id']) ?></h1>
    <p class="text-muted mb-1">Logged in as: <?= e($fullName) ?> (<?= e($role) ?>)</p>
    <p class="mb-3">
        <strong>Purpose:</strong> <?= e($booking['topic_purpose']) ?><br>
        <strong>Date:</strong> <?= e($booking['booking_date']) ?>,
        <?= e($booking['start_time']) ?> - <?= e($booking['end_time']) ?>
    </p>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach (array_unique($errors) as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$equipmentList): ?>
        <div class="alert alert-info">No equipment is available right now.</div>
    <?php else: ?>
        <form method="post" action="equipment.php?booking_id=<?= (int)$bookingId ?>">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr><th style="width:80px">Select</th><th>Equipment</th><th style="width:160px">Quantity</th></tr>
                </thead>
                <tbody>
                <?php foreach ($equipmentList as $eq):
                    $id = (int)$eq['equipment_id'];
                    $isChecked = ($_SERVER['REQUEST_METHOD'] === 'POST')
                        ? isset($posted[$id])
                        : isset($saved[$id]);
                    $qtyValue = $posted[$id] ?? ($saved[$id] ?? 1);
                ?>
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input" name="selected[]"
                                   value="<?= $id ?>" id="eq<?= $id ?>" <?= $isChecked ? 'checked' : '' ?>>
                        </td>
                        <td><label for="eq<?= $id ?>"><?= e($eq['equipment_name']) ?></label></td>
                        <td>
                            <input type="number" class="form-control" name="quantity[<?= $id ?>]"
                                   min="1" max="<?= $maxQty ?>" step="1" value="<?= e($qtyValue) ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-primary">Save Equipment</button>
        </form>
    <?php endif; ?>

    <hr>
    <a href="participants.php?booking_id=<?= (int)$bookingId ?>" class="btn btn-outline-secondary">Back to Participants</a>
</div>
</body>
</html>
