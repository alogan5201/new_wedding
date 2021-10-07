<?php
	require_once "./shared/classes.php";
	if (checkSource()==false) {return;}

	if ( (isset($_POST['password'])) && (isset($_POST['token'])) && (isset($_POST['email']))  ) { 
		$password=trim(htmlspecialchars($_POST['password'])); 
		$token=trim(htmlspecialchars($_POST['token'])); 
		$email=trim(htmlspecialchars($_POST['email'])); 
	} else {
		returnError(-1002, "Invalid data");
		return; 
	}

	/*-- Sql Connection --*/
	$dbCo = phpPdoConnect();
	if ($dbCo===false) {
		returnError(-1002, "Unable to connect");
		return; 
	} 
	/* -- Object returned decalaration --*/
	$sql = "select * from _auth where auth_email=:email and auth_token=:token";
	try {
		$stmt = $dbCo->prepare($sql);
		$stmt->bindParam(':email', $email);
		$stmt->bindParam(':token', $token);
		$stmt->execute();
		if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
			$pk = $row->pk;
			if ($row->auth_enabled != 1) {
				returnError(-1002, "User disabled");
				return;
			}
			/* -- Update password --*/
			try {
				$sql = 'update _auth set auth_password=:password where pk=:pk';
				$stmt2 = $dbCo->prepare($sql);
				$options = [
					'cost' => 12,
				];
				$passwordhash = password_hash($password, PASSWORD_BCRYPT, $options);
				$stmt2->bindValue(':password', $passwordhash);
				$stmt2->bindValue('pk', $pk);
				$stmt2->execute();
			} catch (PDOException $e) {
				returnError(-1002,$e->getMessage());
				return;
			}
		} else {
			returnError(-1002, "Invalid Token or Credential</h3>");
			return;
		}
	} catch (PDOException $e) {
		returnError(-1002,$e->getMessage());
		return;
	}
	
	$obj = new stdClass();

	/* -- Object returned decalaration --*/
	$obj->code = 0;
	$obj->message = "Password Changed Sucessfully";
	echo safe_json_encode($obj);
	return;


?>

