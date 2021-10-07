<?php
	require_once "./shared/classes.php";
	if (checkSource()==false) {return;}
	/* -- POST VARIABLES --*/
	if (isset($_POST['uid'])) { $uid=trim(htmlspecialchars($_POST['uid'])); } else {
		returnError(-1009,"Invalid Parameters");
		return; 
	}
	if (isset($_POST['token'])) { $token=trim(htmlspecialchars($_POST['token'])); } else {
		returnError(-1009,"Invalid Parameters" .$uid);
		return; 
	}

	/*-- D B  C O N N E C T I O N --*/
	$dbCo = phpPdoConnect();
	if ($dbCo===false) {return;} //Error message is handled in function

	/* -- R E S P O N S E   O B J E C T --*/
	$obj = new stdClass();

	/* --  D A T A --*/
	$postdata = array();
	foreach ($_POST as $key => $value) {
		$name=mb_strtolower($key);
		if ( ($name!='uid') && ($name!='token') && ($name!='password') && ($name!='enabled') && ($name!='id')
		&& ($name!='emailverified')&& ($name!='isAnonymous')&& ($name!='pin')&& ($name!='gdprAccept')&& ($name!='id')&& ($name!='id') ) {
			$item = array();
			if ( ($key=='headimage') && (strlen($value)>0) ) {
				$item[$key]=$uid.'-header.jpg';
			} elseif ( ($key=='photourl') && (strlen($value)>0) ) {
					$item[$key]=$uid.'.png';
			} else {
				$item[$key]=$value;
			}
			array_push($postdata,$item);
		}	
	}
	$i=0;
	$fields="auth_modifytime=:modifyTime,auth_language=:language,";
	foreach ($postdata as $key => $item) {
		foreach ($item as $field => $value) {
				$fields = $fields .'auth_'.$field."=:".$field.",";
		}
	}
	$fields=substr($fields,0,strlen($fields)-1);

	sleep($delay); //Prevent Saturation by waiting 3 seconds


	/* -- S E N D   D A T A --*/
	$sql = "update _auth SET ".$fields." where auth_token=:token and uid=:uid";
	try {
		$dbCo->beginTransaction();
		$stmt = $dbCo->prepare($sql);
		$stmt->bindParam(':token', $token);
		$stmt->bindParam(':uid', $uid);
		$stmt->bindValue('modifyTime', time());
		$lang = locale_accept_from_http($_SERVER['HTTP_ACCEPT_LANGUAGE']);
		$stmt->bindValue('language', $lang);
		foreach ($postdata as $key => $item) {
			foreach ($item as $field => $value) {
				if ( ($field!='avatar-input')  ) {
					$stmt->bindValue(':' . str_replace("-fileinput", "", $field), $value);
				}
			}
		}
		$stmt->execute();
	} catch (PDOException $e) {
		$dbCo->rollBack();
		returnError(-1011, $e->getMessage());
		return;
	}

	/*-- Save Avatar and Header Image --*/
	if ((isset($_POST['headimage'])) || (isset($_POST['photourl']))) {
		$localpath = '../../dbassets/';
		$localpath="../dbassets/";
		$photoURL=$localpath.$uid.'.png';
		$headimage=$localpath.$uid.'-header.jpg';
		/*-- Head Image -- */
		if (isset($_POST['headimage'])) {
			$blob = trim($_POST['headimage']);
			if (strlen($blob) > 0) {
				list($head, $content) = explode(';', $blob);
				list($a, $realdata) = explode(',', $content);
				$img = base64_decode($realdata);
				$image = imagecreatefromstring($img);
				if ($image !== false) {
					imagejpeg($image,$headimage);
					imagedestroy($image);
					$obj->headimage=$headimage;
				} else {
					$dbCo->rollBack();
					returnError(-1011, "Invalid Image");
					return;
				}
			}
		}
		/*-- Profile Image --*/
		if (isset($_POST['photourl'])) {
			$blob = trim($_POST['photourl']);
			if (strlen($blob) > 0) {
				list($head, $content) = explode(';', $blob);
				list($a, $realdata) = explode(',', $content);
				$img = base64_decode($realdata);
				$image = imagecreatefromstring($img);
				if ($image !== false) {
					imagepng($image, $photoURL);
					imagedestroy($image);
					$obj->photoURL=$photoURL;
				} else {
					$dbCo->rollBack();
					returnError(-1011, "Invalid Image");
					return;
				}
			}
		}
	}

	/* -- OK, Commit Transaction --*/
	$dbCo->commit();

	/*-- Reload User --*/
	$sql = "select * from _auth  where auth_token=:token and uid=:uid";
	try {
		$stmt = $dbCo->prepare($sql);
		$stmt->bindParam(':token', $token);
		$stmt->bindParam(':uid', $uid);
		$stmt->execute();
		if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
			$obj->displayName = $row->auth_displayname;
			$obj->email = $row->auth_email;
			$obj->uid = $row->auth_uid;
			$obj->emailVerified	= $row->auth_verified;
			$obj->isAnonymous = $row->auth_anonymous;
			$obj->creationTime = $row->auth_creationtime;
			$obj->modifyTime = $row->auth_modifytime;
			$obj->lastSignInTime = $row->auth_lastsignintime;
			$obj->language = $row->auth_language;
			$obj->phoneNumber = $row->auth_phonenumber;
			$obj->headimage =$row->auth_headimage;
			$obj->photoURL = $row->auth_photourl;
		} else {
			returnError(-1024, $e->getMessage());
			return;
		}
	} catch (PDOException $e) {
		returnError(-1023, $e->getMessage());
		return;
	}
	
	/* -- OK, we return the code 0 --*/
	$obj->code=0;
	$obj->message="User was was succesfully updated";
	$obj->token=$token;
	echo json_encode($obj);
	return;
?>

