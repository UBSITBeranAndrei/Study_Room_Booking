
<?php
require_once __DIR__ . '/dbconn.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check login.
if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

// Check user role.
$role = strtolower((string) $_SESSION['role']);

if (!in_array($role, ['student', 'employee', 'administrator'], true)) {
    header('Location: login.php');
    exit;
}

$userName = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'User';

// Initialize variables.
$library = null;
$rooms = [];
$errorMessage = '';

// Get library ID from URL.
$libraryId = filter_input(
    INPUT_GET,
    'library_id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

// Validate library ID.
if ($libraryId === false || $libraryId === null) {

    $errorMessage = 'Invalid or missing library ID.';

} else {

    // Get selected library.
    $librarySql = "SELECT library_id, library_name
                   FROM libraries
                   WHERE library_id = :library_id";

    $libraryStmt = $pdo->prepare($librarySql);
    $libraryStmt->execute([':library_id' => $libraryId]);

    $library = $libraryStmt->fetch();

    if (!$library) {

        $errorMessage = 'Library not found.';

    } else {

        // Get rooms under the selected library.
        $roomSql = "SELECT room_id, room_number, min_capacity, max_capacity
                    FROM rooms
                    WHERE library_id = :library_id
                    ORDER BY room_number ASC";

        $roomStmt = $pdo->prepare($roomSql);
        $roomStmt->execute([':library_id' => $libraryId]);

        $rooms = $roomStmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Available Rooms | University of Baguio</title>

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
                <?php
                echo htmlspecialchars(
                    $userName,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </strong>

            <small>
                <?php
                echo htmlspecialchars(
                    ucfirst($role),
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
                Account
            </small>

        </div>


        <!-- NAVIGATION -->

        <nav>

            <a href="dashboard.php">
                <span>▣</span>
                Dashboard
            </a>


            <?php if ($role === 'student' || $role === 'employee'): ?>

                <a href="libraries.php" class="active">
                    <span>▦</span>
                    Browse Libraries
                </a>

                <a href="dashboard.php#my-bookings">
                    <span>☷</span>
                    My Bookings
                </a>

            <?php else: ?>

                <a href="admin.php">
                    <span>☷</span>
                    Manage Bookings
                </a>

                <a href="libraries.php" class="active">
                    <span>▦</span>
                    Browse Libraries
                </a>

            <?php endif; ?>

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

            <div class="ub-rooms-page-header">

                <div>

                    <h1 class="ub-page-title">
                        Available Discussion Rooms
                    </h1>

                    <p class="ub-page-subtitle">
                        Explore the discussion rooms in your selected
                        University of Baguio library.
                    </p>

                </div>

                <span class="ub-role-label">
                    <?php
                    echo htmlspecialchars(
                        ucfirst($role),
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </span>

            </div>


            <?php if ($errorMessage !== ''): ?>


                <!-- ERROR MESSAGE -->

                <div class="alert alert-danger" role="alert">

                    <?php
                    echo htmlspecialchars(
                        $errorMessage,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </div>

                <a href="libraries.php" class="ub-room-back">
                    ← Back to Libraries
                </a>


            <?php else: ?>


                <!-- SELECTED LIBRARY BANNER -->

                <section class="ub-rooms-banner">

                    <h2>
                        <?php
                        echo htmlspecialchars(
                            $library['library_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </h2>

                    <p>
                        Select a discussion room below to view its
                        capacity and continue with your booking.
                    </p>

                </section>


                <!-- ROOM SECTION HEADER -->

                <div class="ub-rooms-section-header">

                    <h2>
                        Available Rooms
                    </h2>

                    <span>
                        <?php echo count($rooms); ?>

                        <?php echo count($rooms) === 1 ? 'room' : 'rooms'; ?>
                        found
                    </span>

                </div>


                <?php if (empty($rooms)): ?>


                    <!-- EMPTY STATE -->

                    <div class="ub-room-empty">

                        <h3>
                            No Rooms Available
                        </h3>

                        <p>
                            There are currently no rooms listed for this library.
                        </p>

                    </div>


                <?php else: ?>


                    <!-- ROOM CARDS -->

                    <div class="ub-room-grid">

                        <?php foreach ($rooms as $room): ?>

                            <article class="ub-room-card">


                                <!-- CARD HEADER -->

                                <div class="ub-room-card-header">

                                    <span class="ub-room-icon">
                                        ▤
                                    </span>

                                </div>


                                <!-- CARD BODY -->

                                <div class="ub-room-card-body">

                                    <div class="ub-room-label">
                                        DISCUSSION ROOM
                                    </div>

                                    <h3 class="ub-room-name">

                                        <?php
                                        echo htmlspecialchars(
                                            $room['room_number'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                    </h3>

                                    <p class="ub-room-description">
                                        Study and collaborate with your
                                        group in this discussion room.
                                    </p>


                                    <!-- ROOM CAPACITY -->

                                    <div class="ub-room-capacity">

                                        <div class="ub-room-capacity-item">

                                            <span>
                                                Minimum Capacity
                                            </span>

                                            <strong>
                                                <?php
                                                echo (int) $room['min_capacity'];
                                                ?>
                                                people
                                            </strong>

                                        </div>


                                        <div class="ub-room-capacity-item">

                                            <span>
                                                Maximum Capacity
                                            </span>

                                            <strong>
                                                <?php
                                                echo (int) $room['max_capacity'];
                                                ?>
                                                people
                                            </strong>

                                        </div>

                                    </div>


                                    <!-- BOOKING BUTTON -->

                                    <?php if ($role === 'student' || $role === 'employee'): ?>

                                        <button
                                            type="button"
                                            class="btn-ub ub-room-book-button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#roomGuidelinesModal"
                                            data-room-id="<?php echo (int) $room['room_id']; ?>"
                                        >
                                            Book This Room →
                                        </button>

                                    <?php else: ?>

                                        <div class="ub-room-admin-note">
                                            Room information only
                                        </div>

                                    <?php endif; ?>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>


                <!-- BACK BUTTON -->

                <a href="libraries.php" class="ub-room-back">
                    ← Back to Libraries
                </a>


            <?php endif; ?>


            <?php if ($role === 'student' || $role === 'employee'): ?>

                <!-- DISCUSSION ROOM GUIDELINES MODAL -->

                <div
                    class="modal fade"
                    id="roomGuidelinesModal"
                    tabindex="-1"
                    aria-labelledby="roomGuidelinesModalLabel"
                    aria-hidden="true"
                >

                    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">

                        <div class="modal-content ub-guidelines-modal">

                            <!-- MODAL HEADER -->

                            <div class="modal-header ub-guidelines-modal-header">

                                <div>

                                    <small class="ub-guidelines-eyebrow">
                                        UNIVERSITY OF BAGUIO · LIBRARY
                                    </small>

                                    <h2
                                        class="modal-title"
                                        id="roomGuidelinesModalLabel"
                                    >
                                        Discussion Room Guidelines
                                    </h2>

                                </div>

                                <button
                                    type="button"
                                    class="btn-close btn-close-white"
                                    data-bs-dismiss="modal"
                                    aria-label="Close"
                                ></button>

                            </div>


                            <!-- MODAL BODY -->

                            <div class="modal-body ub-guidelines-modal-body">

                                <p class="ub-guidelines-intro">
                                    Please read and understand the following rules
                                    before proceeding with your reservation.
                                </p>


                                <ol class="ub-guidelines-list">

                                    <li>

                                        <strong>
                                            Room Capacity and Duration
                                        </strong>

                                        <span>
                                            A minimum of 5 and a maximum of 10
                                            people may use the Discussion Room
                                            at a time for one hour. An extension
                                            is allowed as long as there are no
                                            other users.
                                        </span>

                                    </li>


                                    <li>

                                        <strong>
                                            Valid Identification
                                        </strong>

                                        <span>
                                            Users must present their valid ID
                                            cards before entering the
                                            Discussion Room.
                                        </span>

                                    </li>


                                    <li>

                                        <strong>
                                            Reservation Policy
                                        </strong>

                                        <span>
                                            Reservations are on a first-come,
                                            first-served basis.
                                        </span>

                                    </li>


                                    <li>

                                        <strong>
                                            Cleanliness
                                        </strong>

                                        <span>
                                            Switch off the lights and maintain
                                            the cleanliness of the Discussion
                                            Room after use.
                                        </span>

                                    </li>


                                    <li>

                                        <strong>
                                            Prohibited Activities
                                        </strong>

                                        <span>
                                            Vandalism, smoking, sleeping, and
                                            eating inside the Discussion Room
                                            are prohibited.
                                        </span>

                                    </li>


                                    <li>

                                        <strong>
                                            Borrowed Materials
                                        </strong>

                                        <span>
                                            Return all borrowed materials,
                                            such as whiteboard markers and
                                            erasers, to the library personnel.
                                        </span>

                                    </li>

                                </ol>


                                <p class="ub-guidelines-thankyou">
                                    Thank you for patronizing the use of
                                    our library facilities.
                                </p>


                                <!-- AGREEMENT CHECKBOX -->

                                <div class="form-check ub-guidelines-agreement">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        value="1"
                                        id="guidelinesAgreement"
                                    >

                                    <label
                                        class="form-check-label"
                                        for="guidelinesAgreement"
                                    >
                                        I have read and understood the
                                        Discussion Room Guidelines.
                                    </label>

                                </div>

                            </div>


                            <!-- MODAL FOOTER -->

                            <div class="modal-footer ub-guidelines-modal-footer">

                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    data-bs-dismiss="modal"
                                >
                                    Cancel
                                </button>

                                <button
                                    type="button"
                                    class="btn-ub"
                                    id="continueToBooking"
                                    disabled
                                >
                                    Continue to Booking →
                                </button>

                            </div>

                        </div>

                    </div>

                </div>

            <?php endif; ?>


            <!-- FOOTER -->

            <footer class="ub-footer">

                © <?php echo date('Y'); ?> University of Baguio.
                All rights reserved.

                <br>

                Study Room Booking System

            </footer>

        </div>

    </main>

</div>


<!-- BOOTSTRAP JAVASCRIPT -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


<!-- GUIDELINES MODAL SCRIPT -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const modal = document.getElementById('roomGuidelinesModal');

    if (!modal) {
        return;
    }

    const checkbox = document.getElementById('guidelinesAgreement');
    const continueButton = document.getElementById('continueToBooking');

    let selectedRoomId = null;


    // Get the selected room when the modal opens.

    modal.addEventListener('show.bs.modal', function (event) {

        const trigger = event.relatedTarget;

        selectedRoomId = trigger
            ? trigger.getAttribute('data-room-id')
            : null;

        // Reset agreement every time the modal opens.

        checkbox.checked = false;
        continueButton.disabled = true;

    });


    // Enable button only when the user agrees.

    checkbox.addEventListener('change', function () {

        continueButton.disabled = !checkbox.checked;

    });


    // Proceed to booking.php.

    continueButton.addEventListener('click', function () {

        if (!checkbox.checked || !selectedRoomId) {
            return;
        }

        window.location.href =
            'booking.php?room_id=' + encodeURIComponent(selectedRoomId);

    });

});

</script>

</body>
</html>
