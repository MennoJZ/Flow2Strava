<?php

require_once 'config.php';
require 'stravaV3/Strava.php';
include 'connect.php';
include 'functions.php';

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

if (!file_exists('cache')) {
    mkdir('cache', 0777, true);
}


try {
	$stmt = $conn->prepare("SELECT * FROM `users` WHERE `autosync` = TRUE"); 
	$stmt->execute();

	// set the resulting array to associative
	$users = $stmt->setFetchMode(PDO::FETCH_ASSOC); 
	$users = $stmt->fetchAll();
	foreach ($users as $user){
		// print_r($user);
		$end_date = date('d.m.Y');
		$start_date = date('d.m.Y',  time() - 3 * 24 * 60 * 60);

		$activity_arr = getFlowActivityArray($start_date, $end_date, $user['flow_email'], mc_decrypt($user['flow_password_hash'], ENCRYPTION_KEY));
		
		if (is_array($activity_arr) and !empty($activity_arr)){
			foreach ($activity_arr as $activity) {
				$activity = getFlowActivityFromIconUrl($activity);
				
				// check if user wants this file to be synced
				if ($user['autosync_acttypes']!=null and in_array($activity->acttype, explode(',', $user['autosync_acttypes']))){
					// set Strava auth code
					$_REQUEST['code'] = $user['strava_code'];
					
					$params = array('after' => $activity->start-(3*60*60+10), 'per_page' => 1); // check 5min before starttime
					$activities = $strava->makeApiCall('athlete/activities', $params);
			
					if (is_array($activities) and abs( strtotime($activities[0]->start_date_local) - strtotime($activity->datetime) ) < (5*60)) { // start within 5 minutes
						echo ('I will not upload this file, it already exists!<br>\n');
					} else {
						echo ('I will upload this file!<br>\n');

						$result = sendToStrava($user['strava_code'], $user['flow_email'], mc_decrypt($user['flow_password_hash'], ENCRYPTION_KEY), $activity->listItemId, "Just another training", "Uploaded using www.flow2strava.com", $activity->stravatype, 1);
						
						print_r($result); echo("<br>\n");
					}
				}
			}
		} else {
			echo("No activities to sync.<br>");
		}
	}
}
catch(PDOException $e) {
	echo "Error: " . $e->getMessage();
}							


?>