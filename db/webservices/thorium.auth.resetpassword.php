<?php
	require_once "./shared/classes.php";
	if (checkSource()==false) {return;}
	/* -- Get POST variables --*/
	if (isset($_POST['email'])) { $email=trim(htmlspecialchars($_POST['email'])); } else {$email='';}
	if (strlen(trim($email))==0) {$email=null;}

	/* -- Check POST variables --*/
	if ($email==null) {
		sleep($delay); //Prevent Saturation by waiting 1 seconds
		returnError(-1009,"Operation refused, Invalid email");
		return; 
	} 
	/*-- Sql Connection --*/
	$dbCo = phpPdoConnect();
	if ($dbCo===false) {return; } //Error message is handled in function
	/* -- Object returned decalaration --*/
	$obj = new stdClass();
	sleep($delay);
	$id = 0;
	$sql = "select * from _auth where auth_email=:email";
	try {
		$stmt = $dbCo->prepare($sql);
		$stmt->bindParam(':email', $email);
		$stmt->execute();
		if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
			$pk = $row->pk;
			if ($row->auth_enabled != 1) {
				returnError(-1017, "User is Disabled");
				return;
			}
			$username = $row->auth_displayname;
			$token = $row->auth_token;
			if (strlen($token)==0) {
				$token = uniqid('', true);
				try {
					$sql = 'update _auth set auth_token=:token where pk=:pk';
					$stmt = $dbCo->prepare($sql);
					$stmt->bindValue('token', $token);
					$stmt->bindValue('pk', $pk);
					$stmt->execute();
				} catch (PDOException $e) {
					returnError(-1013, $e->getMessage());
					return;
				}
			}

			$key=uniqid();
			$appname=$GLOBALS['settings']['appname'];	
			$emailContents=$GLOBALS['settings']['resetpassword']; 
			$emailContents=str_replace("{{username}}",$username,$emailContents); 
			$path=str_replace('thorium.auth.','',$_SERVER['PHP_SELF']);
			if ( ($_SERVER['SERVER_PORT']!=80) && ($_SERVER['SERVER_PORT']!=443)) {
				$port=":".$_SERVER['SERVER_PORT'];
			}
			$link="http://".$_SERVER['SERVER_NAME'].$port.$path."?token=".$token."&email=".$email;
			$emailContents=str_replace("{{resetlink}}",$link,$emailContents); 
			if (sendmail($email,$appname,$emailContents)==true) {
				$obj->code = 0;
				$obj->message = "email sent to " . $row->auth_email;
				echo json_encode($obj);
				return;
			} else {
				returnError(-1099, "Unable to send mail");
				return;
			}			
		} else {
			returnError(-1012, "Invalid Token or Credential");
			return;
		}
	} catch (PDOException $e) {
		returnError(-1011, $e->getMessage());
		return;
	}
	
?>