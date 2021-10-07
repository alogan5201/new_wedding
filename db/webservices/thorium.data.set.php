<?php

/* -- set headers for response -- */
header("Content-Type: application/json; charset=UTF-8");
/* -- Sessions Management and Token Verifier --*/
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

/*-- Sql Connection --*/
$dbCo = phpPdoConnect();
if ($dbCo===false) {return; } //Error message is handled in function

/*-- Get Service --*/
$request='';
$table='';
$privilege_anonymous=4; //No access by default
$privilege_admin=4;//No access by default
$privilege_user=4;//No access by default
$displayerRequest=="";
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
		$displayerRequest=$row->loadrecordsql;
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
$user_enabled="0";
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

/*-- Check if update or insert (uid sent)--*/
$newRecord=true;
if (isset($_POST['uid'])) { 
	$uid=trim(htmlspecialchars($_POST['uid'])); 
	$newRecord=false;

} else {
	$uid= md5(time().'-'.mt_rand(10,1000));
	$newRecord=true;
}

if ( ($user_enabled=="0")) {
	returnError(-1011, "User is disabled");
	return false;
 }

if ($user_isanonymous == 1) { /*-- Anonymous User --*/
	if ($newRecord == true) {
		if ($privilege_anonymous > 2) {	returnError(-1011, "Insufficient privileges");return false;}
	} else {
		if ($privilege_anonymous > 1) {returnError(-1011, "Insufficient privileges");return false;}
	}	
} elseif ($user_admin == 1) { /*-- Admin --*/
	if ($newRecord == true) {
		if ($privilege_admin > 2) {returnError(-1011, "Insufficient privileges");return false;} 
	} else {
		if ($privilege_admin > 1) {returnError(-1011, "Insufficient privileges");return false;}
	}
} else { /*-- Registred User --*/
	if ($newRecord == true) {
			if ($privilege_user > 2) {returnError(-1011, "Insufficient privileges");return false;} 
	} else { 
			if ($privilege_user > 1) {returnError(-1011, "Insufficient privileges");return false;}
	}
}


//1° Convert Post Data to array
$countimages=0;
$postdata = array();
foreach ($_POST as $key => $value) {
	$name=mb_strtolower($key);
	$value=trim($value);
	if ($value=="undefined") {$value='';}
	if ( ($name!='service') && ($name!='token') && ($name!='pk') ) {
		$item = array();
		if ( ( strpos($key,'-fileinput')!=false ) && (strlen($value)>0) ) {
			$item[$key]=$value; 
			array_push($postdata,$item);
			$countimages=$countimages+1;
		} elseif ( ( strpos($key,'-fileinput')!=false ) && (strlen($value)==0) ) {
			$item[$key]=$value;
			array_push($postdata,$item); //Clear Image
			$countimages=$countimages+1;
		} else {
			$item[$key]=$value;
			array_push($postdata,$item);
		}
	}	
}

//2° get incoming files
$authorizedextensions = explode(",",$GLOBALS['settings']['extensions']);
$countfiles=$countimages+1;
foreach ($_FILES as $key => $value) {
	$sourcefilename=$_FILES[$key]['name'];
	$ext = strtolower(pathinfo($sourcefilename, PATHINFO_EXTENSION));
	if (!(in_array($ext,$authorizedextensions))) {
		returnError(-2053, $ext." is not an allowed file extension");
		return;
	}
	$targetfilename =$uid . '-' . $countfiles . '.'.$ext;
	$sk=str_replace("-fileinput", "", $key);
	$item = array();
	$item[$sk]=$targetfilename;
	array_push($postdata,$item);
	$_FILES[$key]['target']=$targetfilename;
	$countfiles=$countfiles+1;
}

//Convert Array to SQL
$i=0;
$d = date_create();
$now=date_timestamp_get($d);

$fields='';
$values='';
$updatefields='modifieddate=:now,modifiedby=:userpk';
foreach ($postdata as $key => $item) {
	foreach ($item as $field => $value) {
			$cf=str_replace("-fileinput", "", $field);
			$fields=$fields.",".$cf; 
			$values=$values.",:".$cf; 
			if ( ($field!='uid') && ($field!='pk') ) {
				$updatefields = $updatefields.",".$cf."=:".$cf;
			}
	}
}
 $fields=substr($fields,1);
 $values=substr($values,1);
 $rowCount=0;


$i = 0;
try {
	$dbCo->beginTransaction();
	if ($newRecord== true) {
		$sql = 'INSERT INTO ' . $table . "(uid,createddate,createdby," . $fields . ") VALUES(:uid,:now,:userpk," . $values . ")";
	} else {
		$sql = 'UPDATE ' . $table . ' SET  ' . $updatefields . ' where uid=:uid';
	}
	$stmt = $dbCo->prepare($sql);
	$stmt->bindValue('uid', $uid);
	$stmt->bindValue('now', $now);
	$stmt->bindValue('userpk', $user_pk);

	foreach ($postdata as $key => $item) {
		foreach ($item as $field => $value) {
			if (strtolower($value) == "true") {
				$value = 1;
			}
			if (strtolower($value) == "false") {
				$value = 0;
			}
			if (strpos($field, '-fileinput') != false) {
				$i = $i + 1;
				if (strlen($value) > 0) {
					$path = $uid . '-' . $i . '.jpg';
					$stmt->bindValue(':' . str_replace("-fileinput", "", $field), $path);
				} else {
					$stmt->bindValue(':' . str_replace("-fileinput", "", $field), ""); //Clear File Ref
				}
			} else {
				$stmt->bindValue(':' . $field, $value);
			}
		}
	}
	$stmt->execute();
	$rowCount = $stmt->rowCount(); //number of rows affected by the last SQL statement
} catch (PDOException $e) {
	$dbCo->rollBack();
	returnError(-1011, $e->getMessage() . " " . $sql);
	return;
}

if (($rowCount == 0) && ($newRecord == false)) {
	returnError(-1050, "Nothing was Updated, Record not found");
	return;
}

//Save Image 
$localpath = '../dbassets/';
$i = 0;
foreach ($postdata as $key => $item) {
	foreach ($item as $field => $value) {
		if (strpos($field, '-fileinput') != false) {
			$i = $i + 1;
			$blob = trim($value);
			if (strlen($blob) > 0) {
				list($head, $content) = explode(';', $blob);
				list($a, $realdata) = explode(',', $content);
				$img = base64_decode($realdata);
				if ( ($head=="data:image/png") ||  ($head=="data:image/jpeg") ) {
					$image = imagecreatefromstring($img);
					if ($image !== false) {
						$filename = $uid . '-' . $i . '.jpg';
						imagejpeg($image, $localpath . $filename);
						imagedestroy($image);
					} else {
						$dbCo->rollBack();
						returnError(-1011, "Invalid Image".$head);
						return;
					}
				} else { //Other types
					list($bgn, $ext) = explode('/', $head);
					$filename = $uid . '-' . $i . '.'.$ext;
					$return=file_put_contents($localpath . $filename,$blob);
					if ($return===false) {
						$dbCo->rollback();
						returnError(-1011, "ERROR WRITE TYPE ".$head." ".$localpath . $filename);
						return;
					} else {
						$dbCo->rollback();
						returnError(-1011, "OK ".$return." ".$blob);
						return;
					}
				}
			}
		}
	}
}

//2° save incoming files
$i = 0;
$localpath = '../dbassets/';
foreach ($_FILES as $key => $value) {
	$targetfilename = $_FILES[$key]['target'];
	$sk = str_replace("-fileinput", "", $key);
	if (move_uploaded_file($_FILES[$key]["tmp_name"],  $localpath .$targetfilename )) {
	} else {
		$dbCo->rollback();
		returnError(-1011, "Error " . $targetfilename );
		return;
	}
	$i = $i + 1;
}


//OK Commit
$dbCo->commit();

//Reload data
try {
	$rows=[];
	$stmt = $dbCo->prepare($displayerRequest);
	$stmt->bindParam(':uid', $uid);
	$stmt->execute();
	while ($row = $stmt->fetch(PDO::FETCH_OBJ)) { 
		unset($row->password);
		$rows[] = $row;
	}
	header("Content-Type: application/json; charset=UTF-8");
	/* -- Object returned decalaration --*/
	$obj->code = 0;
	$obj->message = "OK (" . $rowCount . " rows)";
	$obj->inserted=$newRecord;
	$obj->result = $rows;
	echo safe_json_encode($obj);
	return;
} catch (PDOException $e) {
	returnError(-1002, "Unable to get data " . $e->getMessage());
	return false;
}


?>