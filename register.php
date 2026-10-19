<?php
	session_start();
	require_once "dbconn.php";

	$error = "";
	$role = "";
	$idno = "";
	$fname = "";
	$email = "";

	// only students and employees can create an account
	// role => [table, id number column]
	$roles = [
		"student" => ["students", "student_no"],
		"employee" => ["employees", "employee_no"],
	];

	if (isset($_POST['submit'])) {
			$role = isset($_POST['role']) ? $_POST['role'] : "";
			$idno = trim($_POST['idno']);
			$fname = trim($_POST['full_name']);
			$email = trim($_POST['email']);
			$password = $_POST['password'];
			$confirm = $_POST['confirm'];

			if (!isset($roles[$role])) {
				$error = "Please choose if you are a student or an employee.";
			} elseif ($idno == "" || $fname == "" || $email == "" || $password == "" || $confirm == "") {
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

				// is the email already used by any account?
				$check = "SELECT email FROM students WHERE email=:e1
						UNION SELECT email FROM employees WHERE email=:e2
						UNION SELECT email FROM administrators WHERE email=:e3";
				$prep = $pdo->prepare($check);
				$prep->execute([
					":e1" => $email,
					":e2" => $email,
					":e3" => $email,
				]);
				$emailTaken = $prep->fetch();

				// is the student/employee number already used in that table?
				$check = "SELECT $idcol FROM $table WHERE $idcol=:idno";
				$prep = $pdo->prepare($check);
				$prep->execute([
					":idno" => $idno,
				]);
				$idTaken = $prep->fetch();

				if ($emailTaken) {
					$error = "That email is already registered.";
				} elseif ($idTaken) {
					$error = "That " . ($role == "student" ? "student" : "employee") . " number is already registered.";
				} else {
					$insert = "INSERT INTO $table($idcol, full_name, email, password_hash) values(:idno, :fname, :email, :hash)";
					$prep = $pdo->prepare($insert);
					$prep->execute([
						":idno" => $idno,
						":fname" => $fname,
						":email" => $email,
						":hash" => password_hash($password, PASSWORD_DEFAULT),
					]);

					header("location:login.php?registered=1");
					exit;
				}
			}
		}	
?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Library Room Booking - Create Account</title>
</head>
<body>
	<h2>Library Discussion Room Booking</h2>
	<h3>Create an Account</h3>

	<form method="post">
		<table>
			<tr>
				<td> I am a</td>
				<td>
					<select name="role">
						<option value="">-- Select --</option>
						<option value="student" <?php if ($role == "student") echo "selected" ?>>Student</option>
						<option value="employee" <?php if ($role == "employee") echo "selected" ?>>Employee</option>
					</select>
				</td>
			</tr>
			<tr>
				<td> Student / Employee No.</td>
				<td>
					<input type="text" name="idno" value="<?php echo htmlspecialchars($idno) ?>">
				</td>
			</tr>
			<tr>
				<td> Full Name</td>
				<td>
					<input type="text" name="full_name" value="<?php echo htmlspecialchars($fname) ?>">
				</td>
			</tr>
			<tr>
				<td> Email</td>
				<td>
					<input type="text" name="email" value="<?php echo htmlspecialchars($email) ?>">
				</td>
			</tr>
			<tr>
				<td> Password</td>
				<td>
					<input type="password" name="password">
				</td>
			</tr>
			<tr>
				<td> Confirm Password</td>
				<td>
					<input type="password" name="confirm">
				</td>
			</tr>
			<?php if ($error != ""): ?>
			<tr>
				<td colspan="2">
					<font color="red"><?php echo $error ?></font>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<td>
					<button name="submit"> Create Account </button>
				</td>
				<td>
					<a href="login.php">Back to login</a>
				</td>
			</tr>
		</table>
	</form>

</body>
</html>
