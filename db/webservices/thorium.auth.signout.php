<?php
	require_once "./shared/classes.php";
	if (checkSource()==false) {return;}
	/* -- Get POST variables --*/
	if (isset($_POST['token'])) { $token=trim(htmlspecialchars($_POST['token'])); } else {$token='';}
	if (strlen(trim($token))==0) {$token=null;}

	/*-- Sql Connection --*/
	$dbCo = phpPdoConnect();
	if ($dbCo===false) {return; } //Error message is handled in function

	try {
		$dbCo->beginTransaction();
		$sql = 'update _auth set auth_token=null where auth_token=:token';
		$stmt = $dbCo->prepare($sql);
		$stmt->bindValue('token', $token);
		$stmt->execute();
		$dbCo->commit();
	} catch (PDOException $e) {
		$dbCo->rollBack();
		returnError(-1013, $e->getMessage());
		return;
	}

	$obj = new stdClass();
	session_destroy();
    session_unset();
	$obj->code=0;
	$obj->message="Session Closed";
	$obj->token='';
	echo json_encode($obj);
	return;
?>