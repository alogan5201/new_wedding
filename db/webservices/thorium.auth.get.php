<?php
	require_once "./shared/classes.php";
	if (checkSource()==false) {return;}
	
	/* -- Get POST variables --*/
	if (isset($_POST['token'])) { $token=trim(htmlspecialchars($_POST['token'])); } else {$token='';}
	if (isset($_POST['email'])) { $email=trim(htmlspecialchars($_POST['email'])); } else {$email='';}
	if ((strlen(trim($email))==0) || (strlen(trim($token))==0)) {
		returnError(-1009,"Invalid Parameters");
		return; 
	}

	/*-- Sql Connection --*/
	$dbCo = phpPdoConnect($database);
	if ($dbCo===false) {return; } //Error message is handled in function

	/* -- Object returned decalaration --*/
	$obj = new stdClass();
	/*-- Check if token is a valid existing user --*/
	$id = 0;
	$sql = "select * from _auth where auth_token=:token and auth_email=:email";
	try {
		$stmt = $dbCo->prepare($sql);
		$stmt->bindParam(':token', $token);
		$stmt->bindParam(':email', $email);
		$stmt->execute();
		if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
			$id = $row->id;
			$enabled = $row->auth_enabled;
			if (($anonymous == false)  && ($row->auth_anonymous == 1)) {
				returnError(-1018, "Unable to load anonymous account");
				return;
			}
			if ($enabled != 1) {
				returnError(-1017, "User is Disabled");
				return;
			}
			$obj->code = 0;
			$obj->message = "Success";
			$obj->token = $token;
			$obj->displayName = $row->auth_displayname;
			$obj->email = $row->auth_email;
			$obj->uid = $row->uid;
			$obj->emailVerified	= $row->auth_verified;
			$obj->isAnonymous = $row->auth_anonymous;
			$obj->creationTime = $row->auth_creationtime;
			$obj->modifyTime = $row->auth_modifytime;
			$obj->lastSignInTime = $row->auth_lastsignintime;
			$obj->language = $row->auth_language;
			$obj->shortdesc = $row->auth_shortdesc;
			$obj->bio = $row->auth_bio;
			$obj->firstname = $row->auth_firstname;
			$obj->lastname = $row->auth_lastname;
			$obj->gender = $row->auth_gender;
			$obj->address = $row->auth_address;
			$obj->city = $row->auth_city;
			$obj->country = $row->auth_country;
			$obj->zip = $row->auth_zip;
			$obj->recoveryemail = $row->auth_recoveryemail;
			$obj->birthday = $row->auth_birthday;
			$obj->state = $row->auth_state;
			$obj->phoneNumber = $row->auth_phonenumber;
	
			if (strlen(trim($row->auth_photourl))>0) {
				$obj->photoURL =  $row->auth_photourl;
			} else {
				$obj->photoURL = '';
			}

			if (strlen(trim($row->auth_headimage))>0) {
				$obj->headimage = $row->auth_headimage;
			} else {
				$obj->headimage = '';
			}

		} else {
			returnError(-1012, "Invalid Token or User");
			return;
		}
	} catch (PDOException $e) {
		returnError(-1011, $e->getMessage());
		return;
	}

	/* -- OK, we return the new token --*/
	$obj->code=0;
	$obj->message="User profile succesfully returned";
	$obj->token=$token;
	echo json_encode($obj);
	return;
?>

