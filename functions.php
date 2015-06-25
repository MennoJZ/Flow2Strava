<?php

// Encrypt Function
function mc_encrypt($encrypt, $key){
    $encrypt = serialize($encrypt);
    $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC), MCRYPT_DEV_URANDOM);
    $key = @pack('H*', $key);
    $mac = hash_hmac('sha256', $encrypt, substr(bin2hex($key), -32));
    $passcrypt = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $encrypt.$mac, MCRYPT_MODE_CBC, $iv);
    $encoded = base64_encode($passcrypt).'|'.base64_encode($iv);
    return $encoded;
}

// Decrypt Function
function mc_decrypt($decrypt, $key){
    $decrypt = explode('|', $decrypt.'|');
    $decoded = base64_decode($decrypt[0]);
    $iv = base64_decode($decrypt[1]);
    if(strlen($iv)!==mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC)){ return false; }
    $key = @pack('H*', $key);
    $decrypted = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $decoded, MCRYPT_MODE_CBC, $iv));
    $mac = substr($decrypted, -64);
    $decrypted = substr($decrypted, 0, -64);
    $calcmac = hash_hmac('sha256', $decrypted, substr(bin2hex($key), -32));
    if($calcmac!==$mac){ return false; }
    $decrypted = unserialize($decrypted);
    return $decrypted;
}

function getFlowActivityArray($start_date, $end_date, $email, $password){
	$post_fields = 'returnUrl=https%3A%2F%2Fflow.polar.com%2F&email=' . urlencode($email) . '&password=' . urlencode($password);

	$ch = curl_init();
	
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
	curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/logout');

	$arr = curl_exec($ch); //logout of old session

	curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/login');
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);

	$arr = curl_exec($ch); //post credentials

	curl_setopt($ch, CURLOPT_POST, 0);
	curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/training/getCalendarEvents?start=' . $start_date . '&end=' . $end_date);

	$arr = curl_exec($ch); //get activity list
	$activity_arr = json_decode($arr);
	curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/logout');
	$arr = curl_exec($ch); //logout of old session
	
	return $activity_arr;
}

function getFlowActivityFromIconUrl($activity){
	switch ($activity->iconUrl){
		case 'https://dngo5v6w7xama.cloudfront.net/ecosystem/sport/icon/921e0b87a7a6f6071eb3b7b2d6a16db8-2014-06-16_07_01_31':
			$activity->acttype = 'Running';
			$activity->stravatype = 'Run';
			break;
		case 'https://dngo5v6w7xama.cloudfront.net/ecosystem/sport/icon/097ccbec47dca33dd5e132bdf5080b97-2014-06-16_07_01_18':
			$activity->acttype = 'Road cycling';
			$activity->stravatype = 'Ride';
			break;
		case 'https://dngo5v6w7xama.cloudfront.net/ecosystem/sport/icon/e76f3aebcfd0eaee7db7ff2370662ec2-2014-06-16_07_01_20':
			$activity->acttype = 'Mountainbiking';
			$activity->stravatype = 'Ride';
			break;
		case 'https://dngo5v6w7xama.cloudfront.net/ecosystem/sport/icon/a7a86ec1c5f78aab7f17f1605f724b80-2014-06-16_07_00_57':
			$activity->acttype = 'Cycling';
			$activity->stravatype = 'Ride';
			break;
		case 'https://dngo5v6w7xama.cloudfront.net/ecosystem/sport/icon/c906a3d48be50e9605086f4cf7bbda0c-2014-11-12_07_44_07':
			$activity->acttype = 'Pool swimming';
			$activity->stravatype = 'Swim';
			break;
		case 'https://dngo5v6w7xama.cloudfront.net/ecosystem/sport/icon/24f9191a2f1ca43b1514df1b3403e044-2014-06-16_07_01_06':
			$activity->acttype = 'Other outdoor';
			$activity->stravatype = 'Hike';
			break;
		case 'https://dngo5v6w7xama.cloudfront.net/ecosystem/sport/icon/fa3100f991b892987d551c779dc20edf-2014-06-16_07_00_40':
			$activity->acttype = 'Circuit';
			$activity->stravatype = 'Workout';
			break;
		case 'https://dngo5v6w7xama.cloudfront.net/ecosystem/sport/icon/891d2a2071fc5909fb0c909093e57de5-2014-06-16_07_00_45':
			$activity->acttype = 'Weight lifting';
			$activity->stravatype = 'Workout';
			break;
		default:
			$activity->acttype = 'Other';
			break;
	}
	return $activity;
}

function sendToStrava($strava_code, $email, $password, $listItemId, $title, $description, $type){
	/**
	  * The constructor expects an array of your app's Access Token, Sectret Token, Client ID, the Redirect URL, and cache directory.
	  * See http://strava.github.io/api/ for more detail.
	  */
	$strava = new Strava(array(
		'secretToken' => STRAVA_SECRET_TOKEN,
		'clientID' => STRAVA_CLIENT_ID,
		'redirectUri' => 'http://www.flow2strava.com',
		'cacheDir' => 'cache', // Must be writable by web server
		'cacheTtl' => 10,  // Number of seconds until cache expires (900 = 15 minutes)
	));
	
	if (isset($strava_code) && $strava_code!=null) {
		$_REQUEST['code'] = $strava_code;
		
		$athlete = $strava->makeApiCall('athlete');

		if ($athlete->message == "Authorization Error"){
			echo ("<b>". $athlete->message ."</b><br>");
			echo ("Field: " . $athlete->errors[0]->field ."<br>");
			echo ("Code: " . $athlete->errors[0]->code ."<br>");
		} else {
			$weekago = time() - 7 * 24 * 60 * 60;

			$end_date = date('d.m.Y');
			$start_date = date('d.m.Y', $weekago);
		
			$post_fields = 'returnUrl=https%3A%2F%2Fflow.polar.com%2F&email=' . urlencode($email) . '&password=' . urlencode($password);

			$ch = curl_init();
			
			curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			
			curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/logout');

			$arr = curl_exec($ch); //logout of old session

			curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/login');
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);

			$arr = curl_exec($ch); //post credentials

			curl_setopt($ch, CURLOPT_POST, 0);
			curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/training/getCalendarEvents?start=' . $start_date . '&end=' . $end_date);

			$arr = curl_exec($ch); //get activity list
			$activity_arr = json_decode($arr);
						
			
			date_default_timezone_set('UTC');
			$offset = str_replace(':', '', $tz_fix_offset);
			$newtz = new DateTimezone(timezone_name_from_abbr(null, $offset * 36, false));

			$tcxzipurl = 'https://flow.polar.com/training/analysis/' . $listItemId . '/export/tcx';
			curl_setopt($ch, CURLOPT_URL, $tcxzipurl); //fetch TCX
			$tcxzip = curl_exec($ch);
			
			//logout of old session
			curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/logout');
			$arr = curl_exec($ch);		
			
		// 	echo 'done<br>';
			$zipfilename = tempnam('/tmp/', 'polarsync');
			file_put_contents($zipfilename, $tcxzip);

			$zip = new ZipArchive();
			if (!$zip->open($zipfilename)){
				die('Something wrong with Polar TCX file.');
			}

		// 	for ($i = 0; $i < $zip->numFiles; $i++) {
			$tcxname = @$zip->getNameIndex(0);
			if (empty($tcxname)){
				$tcxname = rtrim(base64_encode(md5(microtime())),"=") . ".tcx";
			}

			$sporttype = strtr(substr($tcxname, 25), '_', '-');

			$t = date_create_from_format('Y-m-d\TH-i-s.uP', substr($tcxname, 0, 24));

			$tcx = @$zip->getFromIndex(0);
			$tcxnamewdir = $local_file_dir . $tcxname;

			file_put_contents($tcxnamewdir, $tcx); //save file locally
			echo PHP_EOL;

			unlink($zipfilename);


			/**
			  * The constructor expects an array of your app's Access Token, Sectret Token, Client ID, the Redirect URL, and cache directory.
			  * See http://strava.github.io/api/ for more detail.
			  */
			$strava = new Strava(array(
				'secretToken' => STRAVA_SECRET_TOKEN,
				'clientID' => STRAVA_CLIENT_ID,
				'redirectUri' => 'http://www.flow2strava.com',
				'cacheDir' => 'cache', // Must be writable by web server
				'cacheTtl' => 10,  // Number of seconds until cache expires (900 = 15 minutes)
			));
			
			$parameters = array('activity_type'=>$type, 'name'=>$title, 'description'=>$description, 'data_type'=>'tcx', 'external_id'=>$listItemId, 'file'=>'@'.$tcxnamewdir);	
			$upload = $strava->makeApiCall('uploads', $parameters, 'post');
			
			
			
			try {
				include('connect.php');
				$stmt = $conn->prepare("INSERT INTO `uploads` (`strava_id`, `flow_activity_id`, `strava_upload_id`, `strava_activity_id`)
					VALUES ('". $athlete->id ."', '". $listItemId ."', '". $upload->id ."', '". $upload->activity_id ."')"); 
				$stmt->execute();
				$conn = null;
			}
			catch(PDOException $e) {
				echo "Error: " . $e->getMessage();
			}
			
			return $upload;
		}
	}
}

?>