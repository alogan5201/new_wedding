<?php
	require_once "./shared/classes.php";
	if (checkSource()==false) {return;}

	/* -- Get POST variables --*/
	if (isset($_POST['email'])) { $email=trim(htmlspecialchars($_POST['email'])); } else {$email='';}
	if (isset($_POST['password'])) { $password=trim(htmlspecialchars($_POST['password'])); } else {$password='';}
	if (isset($_POST['displayname'])) { $displayname=trim(htmlspecialchars($_POST['displayname'])); } else {$displayname='';}
	if (strlen(trim($email))==0) {$email=null;}
	if (strlen(trim($password))==0) {$password=null;}
	if (isset($_POST['token'])) { $token=trim(htmlspecialchars($_POST['token'])); } else {$token=null;}

	/*-- Get Settings --*/
	$delay=0;
	if (isset($GLOBALS['settings']['delay'])) {
		$delay=$GLOBALS['settings']['delay'];
	}
	$twofactors=false;
	if (isset($GLOBALS['settings']['two-factor'])) {
		if ($GLOBALS['settings']['two-factor']==1) {
			$twofactors=true;
		}
	}
	$twoFactorsCode=rand(1000, 9999);
	
	/* -- Check POST variables --*/
	if ( ($email!=null) && ($password!=null) && (strlen($email)>0) && (strlen($password)>0) ) {
		//OK, all parameters here
	} else {
		sleep($delay); //Prevent Saturation by waiting 1 seconds
		returnError(-1009,"Connection refused, Invalid Parameters");
		return; 
	}
	/* -- mode 0: Token verification  || mode 1= Login with Credential  || mode 2: Anonymous login first connection --*/

	/*-- Sql Connection --*/
	$dbCo = phpPdoConnect($database);
	if ($dbCo===false) {return; } //Error message is handled in function

	/* -- Object returned decalaration --*/
	$obj = new stdClass();

	/*-- check if user already exits --*/
	try {
		$stmt = $dbCo->prepare("select count(*) num_rows from _auth where pk>0 and auth_email=:email" );
		$stmt->bindValue('email', $email);
		$stmt->execute();
		if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
			$count=$row->num_rows;
			if ($count>0) {
				returnError(-1030,"User already exists");
				return;
			}
		}
	} catch (PDOException $e) {
		returnError(-1015,$e->getMessage());
		return;
	}

	/*-- check if user is a convertion from anonymous to registered user --*/
	$isConversion=false;
	$pk=0;
	if ($token!=null) {
		try {
			$stmt = $dbCo->prepare("select pk from _auth where pk>0 and auth_token=:token" );
			$stmt->bindValue('token', $token);
			$stmt->execute();
			if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
				$isConversion=true;
				$pk=$row->pk;
			}
		} catch (PDOException $e) {
			returnError(-1015,$e->getMessage());
			return;
		}
	}

	// --- Set SQL -- */
	$sql='';
	if ($isConversion==false) {
		$sql='insert into _auth (uid,auth_email,auth_displayname,auth_password,auth_anonymous,auth_creationtime,auth_lastsignintime,auth_language,auth_token,auth_enabled,auth_pin) values (:uid,:email,:displayname,:password,:anonymous,:creationtime,:lastsignintime,:language,:token,:enabled,:pin)';
	} else {
		$sql='update _auth set auth_email=:email,auth_displayname=:displayname,auth_password=:password,auth_anonymous=0,auth_lastsignintime=:lastsignintime,auth_pin=:pin,auth_language=:language,auth_enabled=:enabled where pk=:pk';
	}

	/* -- create credential --*/
	sleep($delay); //Prevent Saturation by waiting 3 seconds
	try {
		$stmt = $dbCo->prepare($sql);
		if ($isConversion==false) { //Insert
			$uid = uniqid('', true); //Generate unique id based on system microseconds with more_entropy
			$token = uniqid('', true); //Generate unique id based on system microseconds with more_entropy
			$stmt->bindValue('uid', $uid);
			$stmt->bindValue('anonymous', 0);
			$stmt->bindValue('creationtime', time());
			$stmt->bindValue('token', $token);
		} else { //Update
			$stmt->bindValue('pk', $pk); 
		}
		$stmt->bindValue('email', $email);
		$stmt->bindValue('displayname', $displayname);
		$options = [
			'cost' => 12,
		];
		$passwordhash = password_hash($password, PASSWORD_BCRYPT, $options);
		$stmt->bindValue('password', $passwordhash);
		$stmt->bindValue('lastsignintime', time());
		$lang = locale_accept_from_http($_SERVER['HTTP_ACCEPT_LANGUAGE']);
		$stmt->bindValue('language', $lang);
		$token = uniqid('', true); //Generate unique id based on system microseconds with more_entropy
		
		if ($twofactors == true) { 
			$stmt->bindValue('enabled', 0);
		} else {
			$stmt->bindValue('enabled', 1); //Enabled by default if 2Factors desactivated
		}
		$stmt->bindValue('pin', $twoFactorsCode);
		$stmt->execute();
	} catch (PDOException $e) {
		$err = $e->getCode();
		if ($err > 0) {
			returnError(-$err, "error s" . $e->getMessage());
		} else {
			returnError(-1016, "error #" . $e->getCode() . " " . $e->getMessage());
		}
		return;
	}
	$obj->displayName = $displayname;
	$obj->email = $email;
	$obj->uid = $uid;
	$obj->emailVerified	= 0;
	$obj->isAnonymous = 0;
	$obj->creationTime = time();
	$obj->modifyTime = "";
	$obj->lastSignInTime = time();
	$obj->language = $lang;
	$obj->phoneNumber = "";
	$obj->photoURL = "";
	$obj->enabled = ($twofactors == false);

	//-- Set statistics data -- */
	$today= gmdate('d-m-Y');
	$statid=0;
	try {
		$stmt = $dbCo->prepare("select * from _stats where serverdate=:serverdate" );
		$stmt->bindParam(':serverdate',$today);
		$stmt->execute();
		if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
			$statid=$row->id;
		}
	} catch (PDOException $e) {
		returnError(-1015,$e->getMessage());
		return;
	}

	/* -- S T A T I S T I C S --*/
	/* -- Only if connection via user/password --*/
	if ($mode==1) {
		if ($statid==0) {
			/* -- Insert in thorium_statistics --*/
			try {
				$sql = $sql;
				$stmt = $dbCo->prepare('insert into _stats (serverdate,connections) values (:serverdate,1)');
				$stmt->bindValue('serverdate',$today);
				$stmt->execute();
			} catch (PDOException $e) {
				returnError(-1016,$e->getMessage());
				return;
			}
		} else {
			/* -- Update in thorium_statistics --*/
			try {
				$sql = $sql;
				$stmt = $dbCo->prepare('update _stats set connections=connections+1 where serverdate=:serverdate');
				$stmt->bindValue('serverdate',$today);
				$stmt->execute();
			} catch (PDOException $e) {
				returnError(-1016,$e->getMessage());
				return;
			}
		}
	}
	
	/* -- OK, we return the new token --*/
	if ($twofactors==1) {
		$obj->code=1;
		$obj->message="Account successfully created but requires PIN";
	} else {
		$obj->code=0;
		$obj->message="Session was succesfully initialized";
	}
	$obj->token=$token;
	echo json_encode($obj);
	return;

?>