<?php
require_once "./shared/classes.php";
if (checkSource()==false) {return;}
/* -- Object returned decalaration --*/
$obj = new stdClass();


/* -- S E R V I C E -- */
if (isset($_POST['service'])) { 
	$service=trim(htmlspecialchars($_POST['service'])); 
} else {
	returnError(-1002, "Invalid Service");
	return false;
}

/* -- T O K E N -- */
if (isset($_POST['token'])) { 
	$token=trim(htmlspecialchars($_POST['token'])); 
} else {
	returnError(-1002, "Invalid Token");
	return false;
}

/* -- PK -- */
if (isset($_POST['pk'])) { 
	$pk=trim(htmlspecialchars($_POST['pk'])); 
} else {
	returnError(-1002, "Invalid PK");
	return false;
}

/* -- uid -- */
if (isset($_POST['uid'])) { 
	$uid=trim(htmlspecialchars($_POST['uid'])); 
} else {
	returnError(-1002, "Invalid uid");
	return false;
}

/*-- Sql Connection --*/
$dbCo = phpPdoConnect();
if ($dbCo===false) {return; } //Error message is handled in function

/*-- Get Service --*/
$request='';
$table='';
$privilege_anonymous=4; //No access by default
$privilege_admin=4;//No access by default
$privilege_user=4;//No access by default
try {
	$sql="Select * from _services where service=:service";
	$stmt = $dbCo->prepare($sql);
	$stmt->bindParam(':service', $service);
	$stmt->execute();
	if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
		$table=$row->tablename;
		$privilege_anonymous=$row->privilege_anonymous;
		$privilege_admin=$row->privilege_admin;
		$privilege_user=$row->privilege_user;
	} else {
		returnError(-1002, "Service ".$service." does not exist");
		return false;
	}
} catch (PDOException $e) {
	returnError(-1002, "Unable to load Service ".$service.': '.$e->getMessage());
	return false;
}
if (strlen($table)==0) {
	returnError(-1002, "Unable to load Service , no table name defined for service ".$service);
	return false;
}

//get user from token and check rights
$user_pk="0";
$user_uid="";
$user_isanonymous="0";
$user_admin="0";
try {
	$sql="Select * from _auth where auth_token=:token";
	$stmt = $dbCo->prepare($sql);
	$stmt->bindParam(':token', $token);
	$stmt->execute();
	if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
		$user_pk=$row->pk;
		$user_uid=$row->uid;
		$user_isanonymous=$row->auth_anonymous;
		$user_admin=$row->auth_admin;
		$user_enabled=$row->auth_enabled;
	} else {
		returnError(-1010, "Invalid user token");
		return false;
	}
} catch (PDOException $e) {
	returnError(-1002, "Unable to load user from token: ".$e->getMessage());
	return false;
}

/*-- Disabled User --*/
if ( ($user_enabled=="0")) {
	returnError(-1011, "User is disabled");
	return false;
}

/*-- Access Rights --*/
if ($user_isanonymous == 1) { /*-- Anonymous User --*/
	if ($privilege_anonymous >=1) {	returnError(-1011, "Insufficient privileges");return false;}
} elseif ($user_admin == 1) { /*-- Admin --*/
	if ($privilege_admin>=1) {returnError(-1011, "Insufficient privileges");return false;} 
} else { /*-- Registered User --*/
	if ($privilege_user>=1) {returnError(-1011, "Insufficient privileges");return false;} 
}


// -- Delete --*/
 try {
	 $dbCo->beginTransaction();
	 $sql = 'delete from ' . $table . ' where uid=:uid and pk=:pk';
	 $stmt = $dbCo->prepare($sql);
	 $stmt->bindValue('uid', $uid);
	 $stmt->bindValue('pk', $pk);
	 $stmt->execute();
	 $rowCount = $stmt->rowCount(); //number of rows affected by the last SQL statement
 } catch (PDOException $e) {
	 $dbCo->rollBack();
	 returnError(-1011, $e->getMessage());
	 return;
 }

 if (($rowCount == 0) && ($newRecord == false)) {
	returnError(-1050, "Nothing was Deleted, Record not found ". $uid." ".$pk);
	return;
}


	//OK Commit
	$dbCo->commit();

	header("Content-Type: application/json; charset=UTF-8");
	/* -- Object returned decalaration --*/
	$obj->code = 0;
	$obj->message = "OK (" . $rowCount . " rows)";
	$obj->deleted=$rowCount;
	echo safe_json_encode($obj);
	return;

?>