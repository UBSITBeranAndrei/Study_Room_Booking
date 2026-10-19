<?php
	session_start();
	require_once "dbconn.php";

	// already logged in? go straight to the dashboard
	if (isset($_SESSION['user_id'])) {
		header("location:dashboard.php");
		exit;
	}

	$error = "";
	$success = "";
	$email = "";

	// every table that has accounts: role => [table, id column]
	$accounts = [
		"administrator" => ["administrators", "administrator_id"],
		"student" => ["students", "student_id"],
		"employee" => ["employees", "employee_id"],
	];

	if (isset($_GET['registered'])) {
		$success = "Account created! You can now log in.";
	}

	if (isset($_POST['submit'])) {
			$email = trim($_POST['email']);
			$password = $_POST['password'];

			if ($email == "" || $password == "") {
				$error = "Please fill in all fields.";
			} else {
				$found = false;

				foreach ($accounts as $role => $info) {
					$table = $info[0];
					$idcol = $info[1];

					$select = "SELECT $idcol AS user_id, full_name, password_hash FROM $table WHERE email=:email";
					$prep = $pdo->prepare($select);
					$prep->execute([
						":email" => $email,
					]);
					$user = $prep->fetch();

					if ($user && password_verify($password, $user['password_hash'])) {
						session_regenerate_id(true);

						$_SESSION['user_id'] = $user['user_id'];
						$_SESSION['full_name'] = $user['full_name'];
						$_SESSION['role'] = $role;

						header("location:dashboard.php");
						exit;
					}
				}

				$error = "Invalid email or password.";
			}
		}	
?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Library Room Booking - Login</title>
</head>
<body>
	<h2>Library Discussion Room Booking</h2>
	<h3>Login</h3>

	<form method="post">
		<table>
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
			<?php if ($error != ""): ?>
			<tr>
				<td colspan="2">
					<font color="red"><?php echo $error ?></font>
				</td>
			</tr>
			<?php endif; ?>
			<?php if ($success != ""): ?>
			<tr>
				<td colspan="2">
					<font color="green"><?php echo $success ?></font>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<td>
					<button name="submit"> Login </button>
				</td>
				<td>
					<a href="register.php">Create an account</a>
				</td>
			</tr>
		</table>
	</form>

</body>
</html>
