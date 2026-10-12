<?php
	// RUN ONCE in the browser (http://localhost/library_login/seed_users.php), then DELETE this file.
	// Passwords can't be hashed inside plain SQL, so we hash it with PHP here.
	require_once "dbconn.php";

	try {
		$insert = "INSERT INTO administrators(full_name, email, password_hash) values(:name, :email, :hash)";
		$prep = $pdo->prepare($insert);
		$prep->execute([
			":name" => "Administrator",
			":email" => "admin@ub.edu.ph",
			":hash" => password_hash("password123", PASSWORD_DEFAULT),
		]);

		echo "Administrator account created. Delete this file now.";
	} catch (PDOException $e) {
		echo "Seeding failed (already ran?): " . $e->getMessage();
	}
?>
