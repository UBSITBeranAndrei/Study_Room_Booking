
<?php
session_start();
require_once "dbconn.php";

// Only administrators can process bookings
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "administrator") {
    header("location:login.php");
    exit;
}

// Accept POST requests only
if ($_SERVER['REQUEST_METHOD'] != "POST") {
    header("location:admin.php?message=invalid");
    exit;
}

// Check the form token
if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    header("location:admin.php?message=invalid");
    exit;
}

$booking_id = isset($_POST['booking_id'])
    ? (int) $_POST['booking_id']
    : 0;

$action = isset($_POST['action'])
    ? $_POST['action']
    : "";

// Only allow approval or rejection
if (
    $booking_id <= 0 ||
    !in_array($action, ["approved", "rejected"])
) {
    header("location:admin.php?message=invalid");
    exit;
}

try {
    $sql = "UPDATE bookings
            SET status = :status,
                administrator_id = :admin_id
            WHERE booking_id = :booking_id
              AND status = 'pending'";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ":status" => $action,
        ":admin_id" => $_SESSION['user_id'],
        ":booking_id" => $booking_id
    ]);

    if ($stmt->rowCount() > 0) {
        header("location:admin.php?message=" . $action);
    } else {
        // Booking may not exist or may already be processed
        header("location:admin.php?message=invalid");
    }

    exit;

} catch (PDOException $e) {
    // Approval can fail if participant capacity rules are not satisfied
    error_log($e->getMessage());

    header("location:admin.php?message=error");
    exit;
}
?>

