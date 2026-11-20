
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/dbconn.php';

date_default_timezone_set('Asia/Manila');

/* =========================================================
   HELPER FUNCTIONS
========================================================= */

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function stop_page(string $message, int $code = 403): void
{
    http_response_code($code);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Equipment | University of Baguio</title>

        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
            rel="stylesheet"
        >

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

/* =========================================================
   LOGIN AND ROLE VALIDATION
========================================================= */

if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role = strtolower((string) $_SESSION['role']);
$fullName = $_SESSION['full_name'] ?? 'User';

if (!in_array($role, ['student', 'employee'], true)) {
    stop_page('Only students and employees can manage equipment.');
}

/* =========================================================
   BOOKING ID VALIDATION
========================================================= */

$bookingId = filter_input(
    INPUT_GET,
    'booking_id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$bookingId) {
    stop_page('Invalid or missing booking ID.', 400);
}

$bookingId = (int) $bookingId;

/* =========================================================
   LOAD BOOKING DETAILS AND VERIFY OWNERSHIP
========================================================= */

try {

    $stmt = $pdo->prepare(
        "SELECT
            b.booking_id,
            b.student_id,
            b.employee_id,
            b.topic_purpose,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.status,
            r.library_id,
            l.library_name
         FROM bookings b
         INNER JOIN rooms r ON b.room_id = r.room_id
         INNER JOIN libraries l ON r.library_id = l.library_id
         WHERE b.booking_id = :booking_id
         LIMIT 1"
    );

    $stmt->execute([
        ':booking_id' => $bookingId
    ]);

    $booking = $stmt->fetch();

} catch (PDOException $ex) {

    error_log('Equipment booking query error: ' . $ex->getMessage());

    stop_page('Unable to load the booking details. Please try again later.', 500);
}

if (!$booking) {
    stop_page('Booking not found.', 404);
}

$ownerValue = ($role === 'student')
    ? $booking['student_id']
    : $booking['employee_id'];

if ($ownerValue === null || (int) $ownerValue !== $userId) {
    stop_page('You are not allowed to manage equipment for this booking.');
}

/* =========================================================
   BOOKING STATUS
========================================================= */

$isLocked = ($booking['status'] !== 'pending');

$maxQty = 100;

/* =========================================================
   CSRF TOKEN
========================================================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =========================================================
   LOAD AVAILABLE EQUIPMENT
========================================================= */

try {

    $stmt = $pdo->prepare(
        "SELECT
            equipment_id,
            equipment_name
         FROM equipment
         WHERE library_id = :library_id
         ORDER BY equipment_name"
    );

    $stmt->execute([
        ':library_id' => $booking['library_id']
    ]);

    $equipmentList = $stmt->fetchAll();

} catch (PDOException $ex) {

    error_log('Equipment list query error: ' . $ex->getMessage());

    stop_page('Unable to load the available equipment.', 500);
}

/* =========================================================
   VALID EQUIPMENT IDS
========================================================= */

$validIds = [];

foreach ($equipmentList as $equipment) {
    $validIds[(int) $equipment['equipment_id']] = true;
}

/* =========================================================
   INITIAL FORM VALUES
========================================================= */

$errors = [];
$posted = [];
$toSave = [];

/* =========================================================
   PROCESS EQUIPMENT FORM
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    if ($isLocked) {

        $errors[] = 'This booking is already ' .
            $booking['status'] .
            ' and can no longer be changed.';

    } elseif (
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $postedToken)
    ) {

        $errors[] = 'Your session token is invalid or expired. Please reload the page and try again.';

    } else {

        $selected = $_POST['selected'] ?? [];
        $quantities = $_POST['quantity'] ?? [];

        if (!is_array($selected)) {
            $selected = [];
            $errors[] = 'Invalid equipment selection submitted.';
        }

        if (!is_array($quantities)) {
            $quantities = [];
            $errors[] = 'Invalid equipment quantities submitted.';
        }

        /* -----------------------------------------------
           VALIDATE SELECTED EQUIPMENT
        ----------------------------------------------- */

        foreach ($selected as $idRaw) {

            $id = filter_var(
                $idRaw,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if (!$id || !isset($validIds[$id])) {

                $errors[] = 'An invalid equipment item was selected.';
                continue;

            }

            $qtyRaw = $quantities[$id] ?? '';

            $posted[$id] = $qtyRaw;

            $qty = filter_var(
                $qtyRaw,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                        'max_range' => $maxQty
                    ]
                ]
            );

            if ($qty === false || $qty === null) {

                $errors[] = 'Quantity for ' .
                    $id .
                    ' must be a whole number from 1 to ' .
                    $maxQty .
                    '.';

                continue;

            }

            $toSave[$id] = $qty;

        }

        /* -----------------------------------------------
           REQUIRE AT LEAST ONE EQUIPMENT ITEM
        ----------------------------------------------- */

        if (!$errors && count($toSave) === 0) {

            $errors[] = 'Please select at least one equipment item.';

        }

        /* -----------------------------------------------
           SAVE EQUIPMENT
        ----------------------------------------------- */

        if (!$errors) {

            try {

                $pdo->beginTransaction();

                // Lock the booking record before updating equipment.
                $stmt = $pdo->prepare(
                    "SELECT status
                     FROM bookings
                     WHERE booking_id = :booking_id
                     FOR UPDATE"
                );

                $stmt->execute([
                    ':booking_id' => $bookingId
                ]);

                $currentStatus = $stmt->fetchColumn();

                if ($currentStatus !== 'pending') {

                    $pdo->rollBack();

                    $errors[] = 'This booking is no longer pending and cannot be changed.';

                } else {

                    // Remove previously saved items that are no longer selected.
                    $placeholders = implode(
                        ',',
                        array_fill(0, count($toSave), '?')
                    );

                    $deleteStmt = $pdo->prepare(
                        "DELETE FROM booking_equipment
                         WHERE booking_id = ?
                         AND equipment_id NOT IN ($placeholders)"
                    );

                    $deleteStmt->execute(
                        array_merge(
                            [$bookingId],
                            array_keys($toSave)
                        )
                    );

                    // Insert selected equipment or update its quantity.
                    $insertStmt = $pdo->prepare(
                        "INSERT INTO booking_equipment
                            (
                                booking_id,
                                equipment_id,
                                quantity
                            )
                         VALUES
                            (
                                :booking_id,
                                :equipment_id,
                                :quantity
                            )
                         ON DUPLICATE KEY UPDATE
                            quantity = :updated_quantity"
                    );

                    foreach ($toSave as $equipmentId => $quantity) {

                        $insertStmt->execute([
                            ':booking_id' => $bookingId,
                            ':equipment_id' => $equipmentId,
                            ':quantity' => $quantity,
                            ':updated_quantity' => $quantity
                        ]);

                    }

                    $pdo->commit();

                    // Return to dashboard after successful saving.
                    header('Location: dashboard.php?message=equipment_saved');
                    exit;

                }

            } catch (PDOException $ex) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $sqlState = (string) (
                    $ex->errorInfo[0] ?? $ex->getCode()
                );

                $driverCode = (int) (
                    $ex->errorInfo[1] ?? 0
                );

                if ($sqlState === '45000' || $driverCode === 1644) {

                    $errors[] = 'The database rejected the equipment selection. Please check your selected items and quantities.';

                } else {

                    error_log('Equipment saving error: ' . $ex->getMessage());

                    $errors[] = 'Could not save the equipment. Please try again.';

                }

            }

        }

    }

}

/* =========================================================
   LOAD PREVIOUSLY SAVED EQUIPMENT
========================================================= */

try {

    $stmt = $pdo->prepare(
        "SELECT
            equipment_id,
            quantity
         FROM booking_equipment
         WHERE booking_id = :booking_id"
    );

    $stmt->execute([
        ':booking_id' => $bookingId
    ]);

    $saved = [];

    foreach ($stmt->fetchAll() as $row) {

        $saved[(int) $row['equipment_id']] = (int) $row['quantity'];

    }

} catch (PDOException $ex) {

    error_log('Saved equipment query error: ' . $ex->getMessage());

    stop_page('Unable to load the saved equipment selections.', 500);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Booking Equipment | University of Baguio</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="style.css">

</head>

<body>

<div class="ub-layout">

    <!-- SIDEBAR -->

    <aside class="ub-sidebar">

        <div class="ub-sidebar-header">

            <div class="ub-sidebar-university">
                UNIVERSITY OF
            </div>

            <div class="ub-brand">
                BAGUIO
            </div>

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
                <span>▣</span>
                Dashboard
            </a>

            <a href="libraries.php">
                <span>▦</span>
                Browse Libraries
            </a>

            <a href="dashboard.php#my-bookings">
                <span>▤</span>
                My Bookings
            </a>

        </nav>

        <div class="ub-sidebar-bottom">

            <nav>

                <a href="logout.php">
                    <span>↪</span>
                    Logout
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

                    <h1 class="ub-page-title">
                        Booking Equipment
                    </h1>

                    <p class="ub-page-subtitle">
                        Select the equipment you need for your discussion room booking.
                    </p>

                </div>

                <span class="ub-role-label">
                    <?= e(ucfirst($role)) ?> Account
                </span>

            </div>

            <!-- WELCOME BANNER -->

            <div class="ub-banner">

                <h2>
                    Select Your Equipment
                </h2>

                <p>
                    Choose the available equipment and specify the quantity needed for your booking.
                </p>

            </div>

            <!-- BOOKING SUMMARY -->

            <section class="ub-card ub-equipment-summary">

                <div class="ub-equipment-card-header">

                    Booking #<?= (int) $booking['booking_id'] ?> Summary

                </div>

                <div class="ub-equipment-card-body">

                    <div class="row g-3">

                        <div class="col-md-6">

                            <div class="ub-equipment-summary-item">

                                <small>LIBRARY</small>

                                <strong>
                                    <?= e($booking['library_name']) ?>
                                </strong>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="ub-equipment-summary-item">

                                <small>BOOKING PURPOSE</small>

                                <strong>
                                    <?= e($booking['topic_purpose']) ?>
                                </strong>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="ub-equipment-summary-item">

                                <small>BOOKING DATE</small>

                                <strong>
                                    <?= e($booking['booking_date']) ?>
                                </strong>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="ub-equipment-summary-item">

                                <small>BOOKING TIME</small>

                                <strong>
                                    <?= e(substr($booking['start_time'], 0, 5)) ?>
                                    -
                                    <?= e(substr($booking['end_time'], 0, 5)) ?>
                                </strong>

                            </div>

                        </div>

                    </div>

                </div>

            </section>

            <!-- LOCKED BOOKING MESSAGE -->

            <?php if ($isLocked): ?>

                <div class="alert alert-info" role="alert">

                    This booking is
                    <strong><?= e($booking['status']) ?></strong>.

                    Equipment can no longer be changed.

                </div>

            <?php endif; ?>

            <!-- VALIDATION ERRORS -->

            <?php if (!empty($errors)): ?>

                <div class="alert alert-danger" role="alert">

                    <strong>Please check the following:</strong>

                    <ul class="mb-0 mt-2">

                        <?php foreach (array_unique($errors) as $error): ?>

                            <li><?= e($error) ?></li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>

            <!-- EQUIPMENT SELECTION -->

            <section class="ub-card ub-equipment-card">

                <div class="ub-equipment-card-header">
                    Available Equipment
                </div>

                <div class="ub-equipment-card-body">

                    <?php if (empty($equipmentList)): ?>

                        <div class="ub-empty-state bg-light rounded">

                            <h5>No Equipment Available</h5>

                            <p class="mb-0">
                                No equipment is currently listed for this library.
                            </p>

                        </div>

                        <div class="ub-form-actions">

                            <a
                                href="participants.php?booking_id=<?= (int) $bookingId ?>"
                                class="btn-ub-outline"
                            >
                                Back to Participants
                            </a>

                            <a
                                href="dashboard.php"
                                class="btn-ub"
                            >
                                Return to Dashboard
                            </a>

                        </div>

                    <?php else: ?>

                        <h2 class="ub-section-title">
                            Select Equipment
                        </h2>

                        <p class="ub-form-help mb-3">

                            Choose at least one equipment item.

                            Enter a quantity from 1 to <?= (int) $maxQty ?>
                            for each selected item.

                        </p>

                        <form
                            method="post"
                            action="equipment.php?booking_id=<?= (int) $bookingId ?>"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($_SESSION['csrf_token']) ?>"
                            >

                            <fieldset <?= $isLocked ? 'disabled' : '' ?>>

                                <div class="table-responsive">

                                    <table class="table table-bordered table-hover ub-equipment-table">

                                        <thead>

                                            <tr>

                                                <th class="text-center ub-equipment-select-column">
                                                    Select
                                                </th>

                                                <th>
                                                    Equipment Name
                                                </th>

                                                <th class="ub-equipment-quantity-column">
                                                    Quantity
                                                </th>

                                            </tr>

                                        </thead>

                                        <tbody>

                                            <?php foreach ($equipmentList as $equipment): ?>

                                                <?php

                                                $id = (int) $equipment['equipment_id'];

                                                $isChecked = ($_SERVER['REQUEST_METHOD'] === 'POST')
                                                    ? isset($posted[$id])
                                                    : isset($saved[$id]);

                                                $qtyValue = $posted[$id]
                                                    ?? ($saved[$id] ?? 1);

                                                ?>

                                                <tr>

                                                    <td class="text-center">

                                                        <input
                                                            type="checkbox"
                                                            class="form-check-input equipment-checkbox"
                                                            name="selected[]"
                                                            value="<?= $id ?>"
                                                            id="eq<?= $id ?>"
                                                            <?= $isChecked ? 'checked' : '' ?>
                                                        >

                                                    </td>

                                                    <td>

                                                        <label
                                                            for="eq<?= $id ?>"
                                                            class="ub-equipment-name"
                                                        >
                                                            <?= e($equipment['equipment_name']) ?>
                                                        </label>

                                                        <div class="ub-equipment-description">

                                                            Available in
                                                            <?= e($booking['library_name']) ?>

                                                        </div>

                                                    </td>

                                                    <td>

                                                        <input
                                                            type="number"
                                                            class="form-control ub-quantity-input"
                                                            name="quantity[<?= $id ?>]"
                                                            min="1"
                                                            max="<?= (int) $maxQty ?>"
                                                            step="1"
                                                            value="<?= e($qtyValue) ?>"
                                                            aria-label="Quantity for <?= e($equipment['equipment_name']) ?>"
                                                        >

                                                    </td>

                                                </tr>

                                            <?php endforeach; ?>

                                        </tbody>

                                    </table>

                                </div>

                            </fieldset>

                            <!-- FORM BUTTONS: ONLY ONE SAVE BUTTON -->

                            <div class="ub-form-actions">

                                <?php if ($isLocked): ?>

                                    <a
                                        href="dashboard.php"
                                        class="btn-ub"
                                    >
                                        Back to Dashboard
                                    </a>

                                <?php else: ?>

                                    <button
                                        type="submit"
                                        class="btn-ub"
                                    >
                                        Save Equipment and Return to Dashboard →
                                    </button>

                                    <a
                                        href="participants.php?booking_id=<?= (int) $bookingId ?>"
                                        class="btn-ub-outline"
                                    >
                                        Back to Participants
                                    </a>

                                <?php endif; ?>

                            </div>

                        </form>

                    <?php endif; ?>

                </div>

            </section>

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
