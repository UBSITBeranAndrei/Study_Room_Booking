
<?php
require_once __DIR__ . '/dbconn.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$role = strtolower((string) $_SESSION['role']);
$fullName = $_SESSION['full_name'] ?? 'User';

// Allow valid account types only.
if (!in_array($role, ['student', 'employee', 'administrator'], true)) {
    header('Location: logout.php');
    exit;
}

// Retrieve libraries.
$sql = "SELECT library_id, library_name
        FROM libraries
        ORDER BY library_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$libraries = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Browse Libraries | University of Baguio</title>

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
                    $fullName,
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

            <?php endif; ?>


            <?php if ($role === 'administrator'): ?>

                <a href="admin.php">
                    <span>☷</span>
                    Manage Bookings
                </a>

            <?php endif; ?>

        </nav>


        <!-- SIDEBAR FOOTER -->

        <div class="ub-sidebar-bottom">

            <a href="logout.php" class="ub-nav-link">
                <span>↪</span>
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
                        Browse Libraries
                    </h1>

                    <p class="ub-page-subtitle">
                        Choose a library to explore its discussion rooms.
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


            <!-- WELCOME BANNER -->

            <section class="ub-library-banner">

                <h2>
                    Find Your Study Space
                </h2>

                <p>
                    Select your preferred University of Baguio library
                    to view its available discussion rooms.
                </p>

            </section>


            <!-- LIBRARY SECTION HEADER -->

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">

                <h2 class="ub-dashboard-section-title mb-0">
                    Available Libraries
                </h2>

                <span class="text-muted small">
                    <?php echo count($libraries); ?> libraries found
                </span>

            </div>


            <!-- LIBRARY LIST -->

            <?php if (count($libraries) === 0): ?>

                <div class="ub-library-empty">

                    <h3>
                        No Libraries Found
                    </h3>

                    <p>
                        There are currently no libraries available.
                        Please check again later.
                    </p>

                    <a href="dashboard.php" class="btn-ub">
                        Back to Dashboard
                    </a>

                </div>

            <?php else: ?>

                <div class="ub-library-grid">

                    <?php foreach ($libraries as $index => $library): ?>

                        <article class="ub-library-card">

                            <!-- CARD HEADER -->

                            <div class="ub-library-card-header">

                                <div class="ub-library-card-icon">
                                    &#127963;
                                </div>

                            </div>


                            <!-- CARD BODY -->

                            <div class="ub-library-card-body">

                                <div class="ub-library-number">
                                    UNIVERSITY LIBRARY
                                    <?php echo $index + 1; ?>
                                </div>

                                <h3 class="ub-library-card-title">

                                    <?php
                                    echo htmlspecialchars(
                                        $library['library_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </h3>

                                <p class="ub-library-card-description">
                                    Explore the discussion rooms available
                                    in this library.
                                </p>

                                <div class="ub-library-card-note">
                                    <span style="color: var(--ub-red);">
                                        &#9679;
                                    </span>
                                    Discussion rooms available
                                </div>

                                <a
                                    href="rooms.php?library_id=<?php echo (int) $library['library_id']; ?>"
                                    class="btn-ub"
                                >
                                    View Rooms &nbsp; →
                                </a>

                            </div>

                        </article>

                    <?php endforeach; ?>

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

</body>
</html>
