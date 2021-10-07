<?php
	/* -- set headers for response -- */
	header("Content-Type: application/json; charset=UTF-8");
	require_once "./shared/classes.php";
	if (checkSource()==false) {return;}

	/* -- S E R V I C E -- */
	if (isset($_POST['service'])) { $service=trim(htmlspecialchars($_POST['service'])); } 
	else if (isset($_GET['service'])) { $service=trim(htmlspecialchars($_GET['service'])); } else {
		returnError(-1002, "Service Name is Required");
		return false;
	}

	/* -- T O K E N -- */
	if (isset($_POST['token'])) { 
		$token=trim(htmlspecialchars($_POST['token'])); 
	} else {
		returnError(-1002, "Invalid Token");
		return false;
	}

	/*-- fulltext --*/
	$search="";
	if (isset($_POST['search'])) { $search=trim(htmlspecialchars($_POST['search'])); } 

	/*-- record uid --*/
	$uid="";
	if (isset($_POST['uid'])) { $uid=trim(htmlspecialchars($_POST['uid'])); } 

	/*-- Additional Criterians --*/
	$operator1;
	$field1;
	$value1;
	if (isset($_POST['field1'])) { $field1=trim(htmlspecialchars($_POST['field1'])); } 
	if (isset($_POST['operator1'])) { $operator1=trim($_POST['operator1']); } 
	if (isset($_POST['value1'])) { $value1=trim(htmlspecialchars($_POST['value1'])); } 
	$operator2;
	$field2;
	$value2;
	if (isset($_POST['field2'])) { $field2=trim(htmlspecialchars($_POST['field2'])); } 
	if (isset($_POST['operator2'])) { $operator2=trim($_POST['operator2']); } 
	if (isset($_POST['value2'])) { $value2=trim(htmlspecialchars($_POST['value2'])); } 

	/* -- User data filter --*/
	$userdata=0;
	if (isset($_POST['userdata'])) { $userdata=trim(htmlspecialchars($_POST['userdata'])); } 

	/*-- parentpk --*/
	$parentpk="";
	if (isset($_POST['parentpk'])) { $parentpk=trim(htmlspecialchars($_POST['parentpk'])); } 
	/*-- foreignkey --*/
	$foreignkey="";
	if (isset($_POST['foreignkey'])) { $foreignkey=trim(htmlspecialchars($_POST['foreignkey'])); } 

	/*-- extra parentpk --*/
	$extraparentpk="";
	if (isset($_POST['extraparentpk'])) { $extraparentpk=trim(htmlspecialchars($_POST['extraparentpk'])); } 
	/*-- extra foreignkey --*/
	$extraforeignkey="";
	if (isset($_POST['extraforeignkey'])) { $extraforeignkey=trim(htmlspecialchars($_POST['extraforeignkey'])); } 
	

	/*-- sort --*/
	$orderby='';
	$sort='asc';
	if (isset($_POST['orderby'])) { $orderby=trim(htmlspecialchars($_POST['orderby'])); } 
	if (isset($_POST['sort'])) { $sort=trim(htmlspecialchars($_POST['sort'])); } 
	/*-- maxrows --*/
	$maxrows=0;
	if (isset($_POST['maxrows'])) { $maxrows=trim(htmlspecialchars($_POST['maxrows'])); } 
	$offset=0;
	if (isset($_POST['offset'])) { $offset=trim(htmlspecialchars($_POST['offset'])); } 

	/*-- Sql Connection --*/
	$dbCo = phpPdoConnect();
	if ($dbCo===false) {return; } //Error message is handled in function

	/*-- Get Service --*/
	$repeaterRequest='';
	$displayerRequest='';
	$customSQL='';
	$privilege_anonymous=4; //No access by default
	$privilege_admin=4;//No access by default
	$privilege_user=4;//No access by default
	$customService=0;
	try {
		$sql="Select * from _services where service=:service";
		$stmt = $dbCo->prepare($sql);
		$stmt->bindParam(':service', $service);
		$stmt->execute();
		if ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
			$repeaterRequest=$row->request;
			$displayerRequest=$row->loadrecordsql;
			$customSQL=$row->custom_sql;
			$privilege_anonymous=$row->privilege_anonymous;
			$privilege_admin=$row->privilege_admin;
			$privilege_user=$row->privilege_user;
			$customService=$row->custom;
		} else {
			returnError(-1002, "Service ".$service." does not exist");
			return false;
		}
	} catch (PDOException $e) {
		returnError(-1002, "Unable to load Service ".$service.': '.$e->getMessage());
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
			returnError(-1010, "Invalid user token ".$token);
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
		if ($privilege_anonymous ==4) {	returnError(-1011, "Insufficient privileges");return false;}
	} elseif ($user_admin == 1) { /*-- Admin --*/
		if ($privilege_admin==4) {returnError(-1011, "Insufficient privileges");return false;} 
	} else { /*-- Registred USer --*/
		if ($privilege_user==4) {returnError(-1011, "Insufficient privileges");return false;} 
	}

	//* -- SQL Request Construction -- */
	if ($customService != 1) { //Custom Services have their own SQL
		/*-- Additional criterians --*/
		if ((strlen($operator1) > 0) && (strlen($field1) > 0) && (strlen($value1) > 0)) {
			if ($value1=="{today}") {
				$h1=strtotime('today 00:00:00');
				$h2=strtotime('today 23:59:59');
				if ( ($operator1=="=") or ($operator1=="contains") ) {
					$repeaterRequest = $repeaterRequest ." and ".$field1." BETWEEN ".$h1." and ".$h2;
				} else if (($operator1==">") or ($operator1==">=") ) {
					$repeaterRequest = $repeaterRequest ." and ".$field1. $operator1.$h2." ";
				} else if (($operator1=="<") or ($operator1=="<=") ) {
					$repeaterRequest = $repeaterRequest ." and ".$field1. $operator1.$h1." ";
				}
			} else {
				$repeaterRequest = $repeaterRequest . " and " . $field1 . $operator1 . "'" . $value1 . "' ";
			}	
		}
		if ((strlen($operator2) > 0) && (strlen($field2) > 0) && (strlen($value2) > 0)) {
			if ($value2=="{today}") {
				$h1=strtotime('today 00:00:00');
				$h2=strtotime('today 23:59:59');
				if ( ($operator2=="=") or ($operator2=="contains") ) {
					$repeaterRequest = $repeaterRequest ." and ".$field2." BETWEEN ".$h1." and ".$h2;
				} else if (($operator2==">") or ($operator2==">=") ) {
					$repeaterRequest = $repeaterRequest ." and ".$field2. $operator2.$h2." ";
				} else if (($operator2=="<") or ($operator2=="<=") ) {
					$repeaterRequest = $repeaterRequest ." and ".$field2. $operator2.$h1." ";
				} 
			} else {
				$repeaterRequest = $repeaterRequest . " and " . $field2 . $operator2 . "'" . $value2 . "' ";
			}	
		}
		/*-- User owner data only --*/
		if ($userdata == 1) {
			$repeaterRequest = $repeaterRequest . " and b.pk=:userpk ";
		}

		//* -- Parent UID --*/
		if ((strlen($parentpk) > 0) && (strlen($foreignkey) > 0)) {
			$repeaterRequest = $repeaterRequest . " and a." . $foreignkey . "=:parentpk ";
		}  else	if ($service=="_objects") {
			$repeaterRequest = $repeaterRequest . " and a.fk_objects is null ";
		}
		
		/*-- Extra Foreign KEy (additional filter) -- */
		if ((strlen($extraparentpk) > 0) && (strlen($extraforeignkey) > 0)) {
			$repeaterRequest = $repeaterRequest . " and a." . $extraforeignkey . "=:extraparentpk ";
		}


		//Sorted by
		if (strlen($orderby) > 0) {
			$repeaterRequest = $repeaterRequest . " order by " . $orderby . " " . $sort;
		} else {
			$repeaterRequest = $repeaterRequest . " order by a.pk " . $sort;
		}
		//MaxRows (Limit)
		if ($maxrows > 0) {
			$repeaterRequest = $repeaterRequest . " limit " . $maxrows . " offset " . $offset;
		}
	} 
	
	/*-- execute service -- */
	try {
		if ($customService != 1) { 
			if (strlen($uid)>0) {
				$stmt = $dbCo->prepare($displayerRequest);
				$stmt->bindParam(':uid', $uid);
			} else {
				$stmt = $dbCo->prepare($repeaterRequest);
				if ( (strlen($parentpk)>0) && (strlen($foreignkey)>0) ) {
					$stmt->bindParam(':parentpk', $parentpk);
				}
				if ( (strlen($extraparentpk)>0) && (strlen($extraforeignkey)>0) ) {
					$stmt->bindParam(':extraparentpk', $extraparentpk);
				}
			}
		} else { //Custom SQL
			$stmt = $dbCo->prepare($customSQL);
		}

		if ($userdata==1) {
			$stmt->bindParam(':userpk',$user_pk);
		}
		$countrows=0;
		$stmt->execute();
		while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
			unset($row->auth_password);
			$rows[] = $row;
			$countrows=$countrows+1;
		}
		header("Content-Type: application/json; charset=UTF-8");
		/* -- Object returned decalaration --*/
		$obj = new stdClass();
		$obj->code=0;
		$obj->message="Returned Results";
		$obj->result=$rows;
		$obj->count=$countrows;
		echo safe_json_encode($obj);
		
		return;
	} catch (PDOException $e) {
		returnError(-1002, "Unable to get Service ".$e->getMessage()." sql:".' '.$repeaterRequest);
		return false;
	}

?>