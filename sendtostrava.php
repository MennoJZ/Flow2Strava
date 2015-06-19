<?php

require_once 'config.php';
require 'stravaV3/Strava.php';
include_once 'functions.php';

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

if (isset($_POST) && isset($_POST['code'])) {
	// $_GET['code'] = $_POST['code'];
	$athlete = $strava->makeApiCall('athlete');

	if ($athlete->message == "Authorization Error"){
		echo ("<b>". $athlete->message ."</b><br>");
		echo ("Field: " . $athlete->errors[0]->field ."<br>");
		echo ("Code: " . $athlete->errors[0]->code ."<br>");
	} else {
		// print_r($athlete);

		// Polar flow
		include('config.php');

		try {
			include('connect.php');
			$stmt = $conn->prepare("SELECT * FROM `users` WHERE `strava_id` = '". $athlete->id ."'"); 
			$stmt->execute();

			// set the resulting array to associative
			$user = $stmt->setFetchMode(PDO::FETCH_ASSOC); 
			$user = $stmt->fetchAll();
			$user = $user[0];
			$conn = null;
		}
		catch(PDOException $e) {
			echo "Error: " . $e->getMessage();
		}

		
		
		
		$weekago = time() - 7 * 24 * 60 * 60;

		$end_date = date('d.m.Y');
		$start_date = date('d.m.Y', $weekago);

		$post_fields = 'returnUrl=https%3A%2F%2Fflow.polar.com%2F&email=' . $user['flow_email'] . '&password=' . mc_decrypt($user['flow_password_hash'], ENCRYPTION_KEY);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		//logout of old session
		curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/logout');
		$arr = curl_exec($ch);
		
		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/ajaxLogin');

		$arr = curl_exec($ch); //get login page

		curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/login');
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);

		$arr = curl_exec($ch); //post credentials
		
		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/training/getCalendarEvents?start=' . $start_date . '&end=' . $end_date);

		$arr = curl_exec($ch); //get activity list
		$activity_arr = json_decode($arr);
		// print_r($activity_arr);
		
		
		date_default_timezone_set('UTC');
		$offset = str_replace(':', '', $tz_fix_offset);
		$newtz = new DateTimezone(timezone_name_from_abbr(null, $offset * 36, false));

		$tcxurl = 'https://flow.polar.com/training/analysis/' . $_POST['listItemId'] . '/export/tcx/false';
		curl_setopt($ch, CURLOPT_URL, $tcxurl); //fetch TCX
		$tcx = curl_exec($ch);
		
		//logout of old session
		curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/logout');
		$arr = curl_exec($ch);		
		
		$filename = tempnam('/tmp/', 'polarsync');
		file_put_contents($filename, $tcx);

		$parameters = array('activity_type'=>$_POST['activity_type'], 'name'=>$_POST['stravaname'], 'description'=>$_POST['description'], 'data_type'=>'tcx', 'external_id'=>$_POST['listItemId'], 'private'=>isset($_POST['private']), 'file'=>'@'.$filename);
		$upload = $strava->makeApiCall('uploads', $parameters, 'post');
		
		
		
		try {
			include('connect.php');
			$stmt = $conn->prepare("INSERT INTO `uploads` (`strava_id`, `flow_activity_id`, `strava_upload_id`, `strava_activity_id`)
				VALUES ('". $athlete->id ."', '". $_POST['listItemId'] ."', '". $upload->id ."', '". $upload->activity_id ."')"); 
			$stmt->execute();
			$conn = null;
		}
		catch(PDOException $e) {
			echo "Error: " . $e->getMessage();
		}
		
		
		unlink($filename);
		
		
		?>
		
		
		
		
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>Uploading to Strava...</title>

    <!-- Bootstrap -->
    <link href="bootstrap-3.3.4-dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

	<!--meta http-equiv="refresh" content="3;url=http://www.flow2strava.com/?code=<?php echo($_POST['code']); ?>" /-->
	
  </head>
  <body>
  	<div class="container" style="min-height: 200px; margin-top: 100px;">
    	<div class="jumbotron">
	        <h1>Processing your file...</h1>
			<p>Strava is currently processing your file.</p>
    	</div>
    	
		<div id="stravauploadstatus" style="text-align: center;"><img src="images/loader.gif"> Loading status...</div>

	</div>
	

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="bootstrap-3.3.4-dist/js/bootstrap.min.js"></script>
	<script>    
	$( document ).ready(function() {
		intervalName = setInterval(function(){
			$("#stravauploadstatus").load("stravauploadstatus.php", { "code": "<?php echo($_POST['code']); ?>", "strava_upload_id": <?php echo($upload->id); ?> }, function( response, status, xhr ) {
				//alert( "Load was performed." );
				$('#status').html(status);
				$('#xhr').html(xhr.status + " " + xhr.statusText);
				if (xhr.status == 400){
					$("#stravauploadstatus").html(response);
					clearInterval(intervalName);
				} else if (xhr.status == 200){
					clearInterval(intervalName);
					// here show buttons
				} else {
					$("#stravauploadstatus").html(response);
				}
			});
			
		}, 1500);
	});
	</script>    
  </body>
</html>		
		
		
		
				
		
		<?php	
	}
} else {
	echo "No authentication code.";
}


// $post = array('extra_info' => '123456','file_contents'=>'@'.$file_name_with_full_path);



?>