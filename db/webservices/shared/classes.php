<?php
	header("Content-Type: application/json; charset=UTF-8");
	header("Access-Control-Allow-Origin: *");
	$GLOBALS['settings'] = include('settings.php'); 
	$errorlevel=$GLOBALS['settings']['errorlevel']||0;

	/*-- E R R O R   M A N A G E M E N T --*/
	ini_set('display_errors',$errorlevel);
	ini_set('display_startup_errors',$errorlevel);
	error_reporting(E_ALL);

	/*-- DELAY --*/
	$delay=0;
	if (isset($GLOBALS['settings']['delay'])) {
		$delay=$GLOBALS['settings']['delay'];
	}

	/*-- F U N C T I O N S --*/
	function returnError($errcode,$errtext) {
		//return a json object for error management
		$o = new stdClass();
		$o->code=$errcode;
		$o->message=$errtext;
		echo json_encode($o);
		return;
	}

	function checkSource() {
		if ( ($_SERVER['REQUEST_METHOD'] == 'GET' ) ) {
			echo "Invalid access";
			return false; 
		} else {
			return true; 
		}
	}

	/*-- functions --*/
	function phpPdoConnect() {
		static $dbCo = null;
		$dbtype = $GLOBALS['settings']['driverClass'];
		if ($dbtype == "sqlite") {
			$GLOBALS['dbtype']="sqlite";
			if ($dbCo === null) {
				$dbfile = $GLOBALS['settings']['dbfile'];
				$filepath = dirname(__DIR__, 1).$dbfile;
				try {
					$dbCo = new PDO('sqlite:' . $filepath);
					$dbCo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				} catch (PDOException $e) {
					echo encodeResult(-1003, "Invalid Sqlite connection " . $e->getMessage());
					return false;
				}
			}
			return $dbCo;
		} else if ($dbtype == "mysql") {
			//DO MYSQL Connection
			$GLOBALS['dbtype'] = "mysql";
			if ($dbCo === null) {
				$dsn = 'mysql:host=' 
				. $GLOBALS['settings']['host']
				. ';dbname=' 
				. $GLOBALS['settings']['dbname'];
				$options = array(
					PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
				);
				try {
					$dbCo = new PDO($dsn, 
						$GLOBALS['settings']['user'], 
						$GLOBALS['settings']['pass'],
						$options);
					$dbCo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				} catch (PDOException $e) {
					echo encodeResult(-1003, "Invalid MySql connection " . $e->getMessage());
					return false;
				}
			}
			return $dbCo;
		} else {
			echo encodeResult(-1004, "Invalid Database Settings");
			return false;
		}
	}

	/*-- E M A I L   F U N C T I O N S --*/
	function sendmail($to,$subject,$message) {
		$senderemail = $GLOBALS['settings']['senderemail'];
		$replyto = $GLOBALS['settings']['replyto'];
		$message = wordwrap($message, 70, "\r\n");
		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-Type: text/html;charset=UTF-8;';
		$headers[] = 'FROM: '.$senderemail;
		$headers[] = 'Reply-To: '.$replyto;
		$result=mail( $to, $subject, $message,implode("\r\n", $headers) );
		if ($result==false) {return false;} else {return true;}
	}

	/*-- U T I L S --*/
	function url() {
		$url = $_SERVER['REQUEST_URI']; //returns the current URL
		$parts = explode('/',$url);
		$dir = $_SERVER['SERVER_NAME'];
		for ($i = 0; $i < count($parts) - 1; $i++) {
 			$dir .= $parts[$i] . "/";
		}
		return "http://".$dir;
	}

	function random_str($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
    	$pieces = [];
   		$max = mb_strlen($keyspace, '8bit') - 1;
    	for ($i = 0; $i < $length; ++$i) {
        	$pieces []= $keyspace[random_int(0, $max)];
		}
    	return strtolower(implode('', $pieces));
	}

	function safe_json_encode($value, $options = 0, $depth = 512, $utfErrorFlag = false)
{
	$encoded = json_encode($value, $options, $depth);
	switch (json_last_error()) {
		case JSON_ERROR_NONE:
			return $encoded;
		case JSON_ERROR_DEPTH:
			return 'Maximum stack depth exceeded';
		case JSON_ERROR_STATE_MISMATCH:
			return 'Underflow or the modes mismatch';
		case JSON_ERROR_CTRL_CHAR:
			return 'Unexpected control character found';
		case JSON_ERROR_SYNTAX:
			return 'Syntax error, malformed JSON';
		case JSON_ERROR_UTF8:
			$clean = utf8ize($value);
			if ($utfErrorFlag) {
				return 'UTF8 encoding error';
			}
			return safe_json_encode($clean, $options, $depth, true);
		default:
			return 'Unknown error';
	}
}

function utf8ize($d)
{
	if (is_array($d)) {
		foreach ($d as $k => $v) {
			unset($d[$k]);
			$d[utf8ize($k)] = utf8ize($v);
		}
	} else if (is_object($d)) {
		$objVars = get_object_vars($d);
		foreach ($objVars as $key => $value) {
			$d->$key = utf8ize($value);
		}
	} else if (is_string($d)) {
		return iconv('UTF-8', 'UTF-8//IGNORE', utf8_encode($d));
	}
	return $d;
}



	class img {
		var $image;
		var $image_type;
	
		function load($filename) {
			 $image_info = getimagesize($filename);
		  $this->image_type = $image_info[2];
			if( $this->image_type == IMAGETYPE_JPEG ) {
				$this->image = imagecreatefromjpeg($filename);
			}
			elseif( $this->image_type == IMAGETYPE_GIF ) {
				$this->image = imagecreatefromgif($filename);
			}
			elseif( $this->image_type == IMAGETYPE_PNG ) {
				$this->image = imagecreatefrompng($filename);
		  }
		  /*
		  $exif = exif_read_data($filename);
		  if(!empty($exif['Orientation'])) {
			 switch($exif['Orientation']) {
				case 8:
				$this->image = imagerotate($this->image ,90,0);
				case 3:
				$this->image = imagerotate($this->image ,180,0);
				case 6:
				$this->image =  imagerotate($this->image ,-90,0);
			}
		  }*/
		}
	
	   function save($filename, $image_type=IMAGETYPE_JPEG, $compression=75, $permissions=null) {
		  if( $image_type == IMAGETYPE_JPEG ) {
			 imagejpeg($this->image,$filename,$compression);
		  } elseif( $image_type == IMAGETYPE_GIF ) {
			 imagegif($this->image,$filename);
		  } elseif( $image_type == IMAGETYPE_PNG ) {
			 imagepng($this->image,$filename);
		  }
		  if( $permissions != null) {
			 chmod($filename,$permissions);
		  }
	
	
		  return file_exists($filename);
	   }
	
	   function getFormat() {
			if( $this->image_type == IMAGETYPE_JPEG ) {
			return 'jpg';
		 } elseif( $this->image_type == IMAGETYPE_GIF ) {
			return 'gif';
		  } elseif( $this->image_type == IMAGETYPE_PNG ) {
			 return 'png';
		  }
		  else {
				return "unknown";
		  }
	   }
	
	   function output($image_type=IMAGETYPE_JPEG) {
		  if( $image_type == IMAGETYPE_JPEG ) {
			 imagejpeg($this->image);
		  } elseif( $image_type == IMAGETYPE_GIF ) {
			 imagegif($this->image);
		  } elseif( $image_type == IMAGETYPE_PNG ) {
			 imagepng($this->image);
		  }
	   }
	
	   function getWidth() {
		  return imagesx($this->image);
	   }
	
	   function getHeight() {
		  return imagesy($this->image);
	   }
	
	   function resizeToHeight($height) {
		  $ratio = $height / $this->getHeight();
		  $width = $this->getWidth() * $ratio;
		  $this->resize($width,$height);
	   }
	
	   function resizeToWidth($width) {
		  $ratio = $width / $this->getWidth();
		  $height = $this->getheight() * $ratio;
		  $this->resize($width,$height);
	   }
	
	
	   function scale($scale) {
		  $width = $this->getWidth() * $scale/100;
		  $height = $this->getheight() * $scale/100;
		  $this->resize($width,$height);
	   }
	
	   function resize($width,$height) {
		  $new_image = imagecreatetruecolor($width, $height);
		  imagecopyresampled($new_image, $this->image, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());
		  $this->image = $new_image;
	   }
	}



	function blobToFile($blob,$name) {

		$root='../dbassets/';
		$filename="";
		list($head, $content) = explode(';',$blob);
		list($a, $realdata) = explode(',', $content);
		$img = base64_decode($realdata);
		$image = imagecreatefromstring($img);
		$imagpath="";
		if ($image !== false) {
			if (($head == "data:image/jpeg") || ($head == "data:image/jpeg")) {
				$filename=$name.'.jpg';
				$imagpath= $root.$filename;
				imagejpeg($image,$imagpath);
			}
			if ($head == "data:image/png") {
				$filename=$name.'.png';
				$imagpath=$root.$filename;
				imagejpeg($image, $imagpath);
			}
			imagedestroy($image);
			return "./db/dbassets/".$filename;
		} else {
			return false;
		}
	}
