
<?php
session_start();
require_once "dbconn.php";

// Redirect logged-in users to dashboard.
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";
$success = "";
$email = "";

// Account tables: role => [table, ID column]
$accounts = [
    "administrator" => ["administrators", "administrator_id"],
    "student" => ["students", "student_id"],
    "employee" => ["employees", "employee_id"],
];

// Registration success message.
if (isset($_GET['registered'])) {
    $success = "Account created! You can now log in.";
}

// Process login.
if (isset($_POST['submit'])) {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === "" || $password === "") {

        $error = "Please fill in all fields.";

    } else {

        foreach ($accounts as $role => $info) {

            $table = $info[0];
            $idcol = $info[1];

            $select = "SELECT $idcol AS user_id, full_name, password_hash
                       FROM $table
                       WHERE email = :email";

            $prep = $pdo->prepare($select);

            $prep->execute([
                ":email" => $email
            ]);

            $user = $prep->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {

                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $role;

                header("Location: dashboard.php");
                exit;
            }
        }

        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Login | University of Baguio Room Booking</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Centralized Stylesheet -->
    <link rel="stylesheet" href="style.css">

</head>

<body>

<div class="ub-login-layout">

    <!-- UNIVERSITY BRANDING -->

    <aside class="ub-login-branding">

        <div class="ub-login-brand">

            <div class="ub-login-brand-small">
                UNIVERSITY OF
            </div>

            <div class="ub-login-brand-name">
                BAGUIO
            </div>

            <div class="ub-login-brand-divider"></div>

        </div>

        <div class="ub-login-message">

            <h1>
                Find your space.<br>
                Focus on your <span>goals.</span>
            </h1>

            <p>
                Welcome to the University of Baguio
                Study Room Booking System. Reserve a
                discussion room and make your group
                study sessions more productive.
            </p>

        </div>

        <div class="ub-login-brand-footer">
            University of Baguio | Study Room Booking System
        </div>

    </aside>


    <!-- LOGIN SECTION -->

    <main class="ub-login-panel">

        <div class="ub-login-container">

            <!-- MOBILE BRANDING -->

            <div class="ub-login-mobile-brand">

                <div class="ub-login-brand-small">
                    UNIVERSITY OF
                </div>

                <div class="ub-login-brand-name">
                    BAGUIO
                </div>

                <div class="text-white small mt-2">
                    STUDY ROOM BOOKING
                </div>

            </div>


            <!-- LOGIN HEADING -->

            <div class="ub-login-heading">

                <div class="welcome">
                    University of Baguio
                </div>

                <h2>Welcome Back!</h2>

                <p>
                    Sign in to access your booking account.
                </p>

            </div>


            <!-- LOGIN CARD -->

            <div class="ub-login-card">

                <h3 class="h5 fw-bold mb-1">
                    Sign In
                </h3>

                <p class="text-muted small mb-4">
                    Enter your account credentials below.
                </p>


                <!-- ERROR MESSAGE -->

                <?php if ($error !== ""): ?>

                    <div class="alert alert-danger" role="alert">

                        <?php
                        echo htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>

                <?php endif; ?>


                <!-- SUCCESS MESSAGE -->

                <?php if ($success !== ""): ?>

                    <div class="alert alert-success" role="alert">

                        <?php
                        echo htmlspecialchars(
                            $success,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>

                <?php endif; ?>


                <!-- LOGIN FORM -->

                <form method="post" action="login.php">

                    <!-- EMAIL -->

                    <div class="ub-form-group">

                        <label for="email" class="form-label">
                            Email Address
                        </label>

                        <input
                            type="email"
                            class="form-control"
                            id="email"
                            name="email"
                            placeholder="Enter your email"
                            value="<?php
                                echo htmlspecialchars(
                                    $email,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                            autocomplete="username"
                            required
                        >

                    </div>


                    <!-- PASSWORD -->

                    <div class="ub-form-group mb-4">

                        <label for="password" class="form-label">
                            Password
                        </label>

                        <div class="ub-login-password-wrapper">

                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >

                            <button
                                type="button"
                                class="ub-password-toggle"
                                onclick="togglePassword()"
                                id="toggleButton"
                            >
                                Show
                            </button>

                        </div>

                    </div>


                    <!-- SUBMIT BUTTON -->

                    <button
                        type="submit"
                        name="submit"
                        value="1"
                        class="btn-ub w-100"
                    >
                        Sign In to Your Account
                    </button>

                </form>


                <!-- REGISTER LINK -->

                <div class="ub-login-register">

                    Don't have an account?

                    <a href="register.php">
                        Create an account
                    </a>

                </div>

            </div>


            <!-- FOOTER -->

            <div class="ub-login-footer">

                © <?php echo date('Y'); ?> University of Baguio.
                All rights reserved.

                <br>

                Study Room Booking System

            </div>

        </div>

    </main>

</div>


<!-- PASSWORD VISIBILITY SCRIPT -->

<script>

function togglePassword() {

    const password = document.getElementById('password');
    const button = document.getElementById('toggleButton');

    if (password.type === 'password') {

        password.type = 'text';
        button.textContent = 'Hide';

    } else {

        password.type = 'password';
        button.textContent = 'Show';

    }
}

</script>

</body>
</html>
