<?php
	session_start();

	// not logged in? back to login
	if (!isset($_SESSION['user_id'])) {
		header("location:login.php");
		exit;
	}
?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Dashboard</title>
</head>
<body>
	<h2>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']) ?>!</h2>

	<table border="2px">
		<tr>
			<th>User ID</th>
			<th>Name</th>
			<?php if ($_SESSION['role'] != "administrator"): ?>
			<th>Account Type</th>
			<?php endif; ?>
			<th>Actions</th>
		</tr>
		<tr>
			<td><?php echo $_SESSION['user_id'] ?></td>
			<td><?php echo htmlspecialchars($_SESSION['full_name']) ?></td>
			<?php if ($_SESSION['role'] != "administrator"): ?>
			<td><?php echo ucfirst($_SESSION['role']) ?></td>
			<?php endif; ?>
			<td>
				<a href="logout.php">Logout</a>
			</td>
		</tr>
	</table>
</body>
</html>
