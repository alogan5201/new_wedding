<?php
	require_once "./shared/classes.php";
	if (checkSource()==false) {return;}

	//$o = new thorium();

	/* -- Get POST variables --*/
	if (isset($_POST['token'])) { $token=trim(htmlspecialchars($_POST['token'])); } else {$token='';}
	if (isset($_POST['pin'])) { $pin=trim(htmlspecialchars($_POST['pin'])); } else {$pin=0;}
	if (isset($_POST['email'])) { $email=trim(htmlspecialchars($_POST['email'])); } else {$email='';}
	if (isset($_POST['password'])) { $password=trim(htmlspecialchars($_POST['password'])); } else {$password='';}
	if (strlen(trim($email))==0) {$email=null;}
	if (strlen(trim($password))==0) {$password=null;}
	if (strlen(trim($token))==0) {$token=null;}
	if (strlen(trim($pin))==0) {$pin=0;}


	/*-- Get Settings --*/
	$delay=0;
	if (isset($GLOBALS['settings']['delay'])) {
		$delay=$GLOBALS['settings']['delay'];
	}
	$twofactors=0;  //1=Register only, 2=everytime
	if (isset($GLOBALS['settings']['two-factor'])) {
		$twofactors=$GLOBALS['settings']['two-factor'];
	}
	/*-- check if allow anonymous -- */
	$anonymous=false;
	if (isset($GLOBALS['settings']['anonymous'])) {
		$anonymous=$GLOBALS['settings']['anonymous'];
	}

	/*-- check if allow only admin users -- */
	$adminonly=true;
	if (isset($GLOBALS['settings']['adminonly'])) {
		$adminonly=$GLOBALS['settings']['adminonly'];
	}
	

	/* -- Check POST variables --*/
	if (($token!=null) && (strlen($token)>0) ) {$mode=0;} 
	else if ( ($email!=null) && ($password!=null) && (strlen($email)>0) && (strlen($password)>0) ) {$mode=1;}
	else if ($anonymous==true) {$mode=2;}
	else if ( ($anonymous==false) && ( ($email==null) || ($password==null) ) ) {
		sleep($delay); //Prevent Saturation by waiting 1 seconds
		returnError(-1009,"Connection refused, Invalid Parameters");
		return; 
	} else {
		sleep($delay); //Prevent Saturation by waiting 1 seconds
		returnError(-1009,"Connection refused, Invalid Parameters");
		return; 
	}

	/* -- mode 0: Token verification  || mode 1= Login with Credential  || mode 2: Anonymous login first connection --*/

	/*-- Sql Connection --*/
	$dbCo = phpPdoConnect();
	if ($dbCo===false) {return; } //Error message is handled in function

	/* -- Object returned decalaration --*/
	$obj = new stdClass();

	/*-- Mode 0: Token passed to API for verification --*/
	if ($mode == 0) {
		/*-- Check if token is a valid existing session --*/
		sleep($delay); //Prevent Saturation by waiting n seconds
		$id = 0;
		$sql = "select * from _auth where auth_token=:token";
		try {
			$stmt = $dbCo->prepare($sql);
			$stmt->bindParam(':token', $token);
			$stmt->execute();
			if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
				$id = $row->pk;
				$enabled = $row->auth_enabled;
				if ( ($anonymous == false)  && ($row->auth_anonymous==1) ) {
					returnError(-1018, "Unable to connect with anonymous account");
					return;
				}
				if (($adminonly==true) && ($row->auth_admin!=1)) {
					returnError(-1022, "You are not an Administrator");
					return;
				}
				if ($enabled != 1) {
					returnError(-1017, "User is Disabled");
					return;
				}

				/*-- Update lastSigninTime--*/
				$pk=$row->pk;
				$lastsignintime=time();
				try {
					$updt = $dbCo->prepare('update _auth set auth_lastsignintime=:lastsignintime where pk=:pk');
					$updt->bindValue('pk',$pk);
					$updt->bindValue('lastsignintime',$lastsignintime);
					$updt->execute();
				} catch (PDOException $e) {
					returnError(-1016,$e->getMessage());
					return;
				}

				$obj->code = 0;
				$obj->message = "Session verified " . $row->auth_email;
				$obj->token = $token;
				$obj->displayName = $row->auth_displayname;
				$obj->email = $row->auth_email;
				$obj->uid = $row->uid;
				$obj->emailVerified	= $row->auth_verified;
				$obj->isAnonymous = $row->auth_anonymous;
				$obj->creationTime = $row->auth_creationtime;
				$obj->modifyTime = $row->auth_modifytime;
				$obj->lastSignInTime = $lastsignintime;
				$obj->language = $row->auth_language;
				$obj->phoneNumber = $row->auth_phonenumber;
				$obj->headimage = $row->auth_headimage;
				if ($row->auth_admin==1) {
					$obj->group=2;
				} else if ($row->auth_anonymous==1) {
					$obj->group=0;
				} else {
					$obj->group=1;
				}

				if (strlen(trim( $row->auth_photourl))>0) {
					$obj->photoURL =$row->auth_photourl;
				} else {
					$obj->photoURL = '';
				}
				echo json_encode($obj);
				return;
				
			} else {
				if ($anonymous == true) {
					$mode = 2; //Swith to Anonymous first connection and continue
				} else {
					returnError(-1012, "Invalid Token or Credential");
					return;
				}
			}
		} catch (PDOException $e) {
			returnError(-1011, $e->getMessage());
			return;
		}
	}

	/* -- mode 2: Anonymous login first connection --*/
	if ($mode == 2) {
		/*--Connection without user/pw and anonymous users allowed --*/
		sleep($delay); //Prevent Saturation by waiting 3 seconds
		try {
			$token = uniqid('', true); //Generate unique id based on system microseconds with more_entropy
			$stmt = $dbCo->prepare('insert into _auth (uid,auth_displayname,auth_password,auth_anonymous,auth_creationtime,auth_lastsignintime,auth_language,auth_token,auth_enabled,auth_gdpraccept) values (:uid,:displayName,:password,:isAnonymous,:creationTime,:lastSignInTime,:language,:token,:enabled,:gdprAccept)');
			$uid = uniqid('', true)."-".rand(0,1000); //Generate unique id based on system microseconds with more_entropy
			$stmt->bindValue('uid', $uid);
			$stmt->bindValue('displayName', "Anonymous");
			$stmt->bindValue('password', "*");
			$stmt->bindValue('isAnonymous', 1);
			$stmt->bindValue('creationTime', time());
			$stmt->bindValue('lastSignInTime', time());
			$lang = locale_accept_from_http($_SERVER['HTTP_ACCEPT_LANGUAGE']);
			$stmt->bindValue('language', $lang);
			$token = uniqid('', true); //Generate unique id based on system microseconds with more_entropy
			$stmt->bindValue('token', $token);
			$stmt->bindValue('enabled', 1);
			$stmt->bindValue('gdprAccept', 1);
			$stmt->execute();
		} catch (PDOException $e) {
			returnError(-1016, "error" . $e->getMessage());
			return;
		}
		$obj->displayName = "Anonymous";
		$obj->email = "";
		$obj->uid = $uid;
		$obj->emailVerified	= 0;
		$obj->isAnonymous = 1;
		$obj->creationTime = time();
		$obj->modifyTime ="";
		$obj->lastSignInTime = time();
		$obj->language =$lang;
		$obj->phoneNumber = "";
		$obj->photoURL = "";
		$obj->headimage = "";
		$obj->group=0; //Anonymous
	}

	/*-- mode 1: Open session with login password --*/
	else if ($mode == 1) {
		/*-- Check user session --*/
		sleep($delay); //Prevent Saturation by waiting n seconds
		$id = 0;
		$enabled=false;
		$storedPIN="";
		$sql = "select * from _auth where auth_email=:email";
		try {
			$stmt = $dbCo->prepare($sql);
			$stmt->bindParam(':email', $email);
			$stmt->execute();
			if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
				if (!(password_verify($password, $row->auth_password))) {
					returnError(-1007, "Invalid Credential");
					return;
				}
				if (($adminonly==true) && ($row->auth_admin!=1)) {
					returnError(-1022, "You don't have Administrator privileges");
					return;
				}
				$enabled=$row->auth_enabled==1;
				$pk = $row->pk;
				$obj->token = $token;
				$obj->displayName=$row->auth_displayname;
				$obj->email=$row->auth_email;
				$obj->uid=$row->uid;
				$obj->emailVerified	=$row->auth_verified;
				$obj->isAnonymous=$row->auth_anonymous;
				$obj->creationTime=$row->auth_creationtime;
				$obj->modifyTime=$row->auth_modifytime;
				$obj->lastSignInTime=$row->auth_lastsignintime;
				$obj->language=$row->auth_language;
				$obj->phoneNumber=$row->auth_phonenumber;
				$storedPIN=$row->auth_pin;
				if (strlen(trim( $row->auth_photourl))>0) {
					$obj->photoURL =  $row->auth_photourl;
				} else {
					$obj->photoURL = '';
				}
				if (strlen(trim( $row->auth_headimage))>0) {
					$obj->headimage =  $row->urlpath.$row->auth_headimage;
				} else {
					$obj->headimage = '';
				}
				if ($row->auth_admin==1) {
					$obj->group=2;
				} else if ($row->auth_anonymous==1) {
					$obj->group=0;
				} else {
					$obj->group=1;
				}

			} else {
				returnError(-1012, "Invalid Token or Credential");
				return;
			}
		} catch (PDOException $e) {
			returnError(-1011, $e->getMessage());
			return;
		}

		/* -- If user found we check the PIN and regenerate and update the token and signin date --*/
		$token = uniqid('', true); //Generate unique id based on system microseconds with more_entropy
		try {
			if ($enabled==false) {
				if ( ( $twofactors>0 ) && ( $storedPIN!=$pin ) ) {
					returnError(-1019, "2 Invalid PIN Db:".$storedPIN." SENT:".$pin);
					return;
				} elseif ( ( $twofactors>0 ) && ($storedPIN==$pin ) ) {
					//OK, right PIN, we continue
				} else {
					returnError(-1021, "User is Disabled  PIN Stored:".$storedPIN."= PIN Sent:".$pin." 2factors=".$twofactors);
					return;
				}
			} else if ( ( $twofactors==2 ) && ( $storedPIN!=$pin ) ) { //PIN For every connection
				returnError(-1019, "2 Invalid PIN Db:".$storedPIN." SENT:".$pin);
				return;
			}
			//Ok, we Update User
			$sql = 'update _auth set auth_token=:token,auth_lastsignintime=:lastSignInTime,auth_pin=:pin,auth_enabled=1 where pk=:pk';
			$stmt = $dbCo->prepare($sql);
			$stmt->bindValue('token', $token);
			$stmt->bindValue('lastSignInTime', time());
			$stmt->bindValue('pk', $pk);
			if ($twofactors==2) {
				$newPIN = rand(1000, 9999);
				$stmt->bindValue('pin', $newPIN);
			}
			$stmt->execute();
		} catch (PDOException $e) {
			returnError(-1013, $e->getMessage());
			return;
		}
	}

	//Clear old Anonymous data from DB --*/
	$mydate=time()- (3000); 
	try {
		$dlt = $dbCo->prepare("delete from _auth where auth_anonymous=1 and pk>0 and pk<>:pk and auth_lastsignintime<:mydate" ); //and auth_lastsignintime<:mydate
		$dlt->bindParam(':mydate',$mydate);
		$dlt->bindParam(':pk',$pk);
		$dlt->execute();
	} catch (PDOException $e) {
		returnError(-1016, $e->getMessage());
		return;
	}

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
	$obj->code=0;
	$obj->message="Session was succesfully initialized";
	$obj->token=$token;
	echo json_encode($obj);
	return;
?>

