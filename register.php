
<?php
session_start();
require_once "dbconn.php";

$error = "";
$role = "";
$idno = "";
$fname = "";
$email = "";

// Only students and employees can register.
$roles = [
    "student" => ["students", "student_no"],
    "employee" => ["employees", "employee_no"],
];

// Process registration.
if (isset($_POST['submit'])) {

    $role = $_POST['role'] ?? "";
    $idno = trim($_POST['idno'] ?? "");
    $fname = trim($_POST['full_name'] ?? "");
    $email = trim($_POST['email'] ?? "");
    $password = $_POST['password'] ?? "";
    $confirm = $_POST['confirm'] ?? "";

    if (!isset($roles[$role])) {

        $error = "Please choose if you are a student or an employee.";

    } elseif (
        $idno === "" ||
        $fname === "" ||
        $email === "" ||
        $password === "" ||
        $confirm === ""
    ) {

        $error = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters.";

    } elseif ($password !== $confirm) {

        $error = "Passwords do not match.";

    } else {

        $table = $roles[$role][0];
        $idcol = $roles[$role][1];

        // Check whether the email is already used.
        $check = "SELECT email FROM students WHERE email = :e1
                  UNION
                  SELECT email FROM employees WHERE email = :e2
                  UNION
                  SELECT email FROM administrators WHERE email = :e3";

        $prep = $pdo->prepare($check);

        $prep->execute([
            ":e1" => $email,
            ":e2" => $email,
            ":e3" => $email
        ]);

        $emailTaken = $prep->fetch();

        // Check whether the student or employee number is already registered.
        $check = "SELECT $idcol FROM $table WHERE $idcol = :idno";

        $prep = $pdo->prepare($check);

        $prep->execute([
            ":idno" => $idno
        ]);

        $idTaken = $prep->fetch();

        if ($emailTaken) {

            $error = "That email is already registered.";

        } elseif ($idTaken) {

            $error = "That " .
                ($role === "student" ? "student" : "employee") .
                " number is already registered.";

        } else {

            // Create the account.
            $insert = "INSERT INTO $table
                       ($idcol, full_name, email, password_hash)
                       VALUES (:idno, :fname, :email, :hash)";

            $prep = $pdo->prepare($insert);

            $prep->execute([
                ":idno" => $idno,
                ":fname" => $fname,
                ":email" => $email,
                ":hash" => password_hash($password, PASSWORD_DEFAULT)
            ]);

            header("Location: login.php?registered=1");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Create Account | University of Baguio Room Booking</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Centralized Stylesheet -->
    <link rel="stylesheet" href="style.css">

</head>

<body>

<div class="ub-register-layout">

    <!-- UNIVERSITY BRANDING -->

    <aside class="ub-register-branding">

        <div class="ub-register-brand">

            <div class="ub-register-brand-small">
                UNIVERSITY OF
            </div>

            <div class="ub-register-brand-name">
                BAGUIO
            </div>

            <div class="ub-register-brand-divider"></div>

        </div>

        <div class="ub-register-message">

            <h1>
                Join your campus.<br>
                Start your <span>journey.</span>
            </h1>

            <p>
                Create your account to access the University
                of Baguio Study Room Booking System. Book
                discussion rooms and organize productive
                group study sessions.
            </p>

        </div>

        <div class="ub-register-brand-footer">
            University of Baguio | Study Room Booking System
        </div>

    </aside>


    <!-- REGISTRATION SECTION -->

    <main class="ub-register-panel">

        <div class="ub-register-container">

            <!-- MOBILE BRANDING -->

            <div class="ub-register-mobile-brand">

                <div class="ub-register-brand-small">
                    UNIVERSITY OF
                </div>

                <div class="ub-register-brand-name">
                    BAGUIO
                </div>

                <div class="text-white small mt-2">
                    STUDY ROOM BOOKING
                </div>

            </div>


            <!-- REGISTRATION HEADING -->

            <div class="ub-register-heading">

                <div class="welcome">
                    University of Baguio
                </div>

                <h2>Create Your Account</h2>

                <p>
                    Register to start booking discussion rooms.
                </p>

            </div>


            <!-- REGISTRATION CARD -->

            <div class="ub-register-card">

                <h3 class="h5 fw-bold mb-1">
                    Account Registration
                </h3>

                <p class="text-muted small mb-4">
                    Please provide the required information.
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


                <!-- REGISTRATION FORM -->

                <form method="post" action="register.php">

                    <!-- ACCOUNT TYPE -->

                    <div class="ub-form-group">

                        <label for="role" class="form-label">
                            I am a
                        </label>

                        <select
                            class="form-select"
                            id="role"
                            name="role"
                            required
                        >

                            <option value="">
                                Select account type
                            </option>

                            <option
                                value="student"
                                <?php echo ($role === "student") ? "selected" : ""; ?>
                            >
                                Student
                            </option>

                            <option
                                value="employee"
                                <?php echo ($role === "employee") ? "selected" : ""; ?>
                            >
                                Employee
                            </option>

                        </select>

                    </div>


                    <!-- STUDENT / EMPLOYEE NUMBER -->

                    <div class="ub-form-group">

                        <label for="idno" class="form-label">
                            Student / Employee Number
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="idno"
                            name="idno"
                            placeholder="Enter your ID number"
                            value="<?php
                                echo htmlspecialchars(
                                    $idno,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                            required
                        >

                    </div>


                    <!-- FULL NAME -->

                    <div class="ub-form-group">

                        <label for="full_name" class="form-label">
                            Full Name
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="full_name"
                            name="full_name"
                            placeholder="Enter your full name"
                            value="<?php
                                echo htmlspecialchars(
                                    $fname,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                            autocomplete="name"
                            required
                        >

                    </div>


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
                            autocomplete="email"
                            required
                        >

                    </div>


                    <!-- PASSWORD -->

                    <div class="ub-form-group">

                        <label for="password" class="form-label">
                            Password
                        </label>

                        <div class="ub-register-password-wrapper">

                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                placeholder="At least 8 characters"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="ub-register-password-toggle"
                                onclick="togglePassword('password', 'togglePasswordButton')"
                                id="togglePasswordButton"
                            >
                                Show
                            </button>

                        </div>

                        <small class="form-text">
                            Password must be at least 8 characters.
                        </small>

                    </div>


                    <!-- CONFIRM PASSWORD -->

                    <div class="ub-form-group mb-4">

                        <label for="confirm" class="form-label">
                            Confirm Password
                        </label>

                        <div class="ub-register-password-wrapper">

                            <input
                                type="password"
                                class="form-control"
                                id="confirm"
                                name="confirm"
                                placeholder="Re-enter your password"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="ub-register-password-toggle"
                                onclick="togglePassword('confirm', 'toggleConfirmButton')"
                                id="toggleConfirmButton"
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
                        Create Account
                    </button>

                </form>


                <!-- LOGIN LINK -->

                <div class="text-center mt-4 small text-muted">

                    Already have an account?

                    <a href="login.php" class="ub-register-login-link">
                        Sign in here
                    </a>

                </div>

            </div>


            <!-- FOOTER -->

            <div class="ub-register-footer">

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

function togglePassword(inputId, buttonId) {

    const password = document.getElementById(inputId);
    const button = document.getElementById(buttonId);

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
