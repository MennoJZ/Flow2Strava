<?php

require_once('config.php');
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

if (isset($_REQUEST) && isset($_REQUEST['code'])) {
	$athlete = $strava->makeApiCall('athlete');

	if ($athlete->message != "Authorization Error") {
		try {
			$stmt = $conn->prepare("SELECT * FROM `users` WHERE `strava_id` = '". $athlete->id ."'"); 
			$stmt->execute();

			// set the resulting array to associative
			$user = $stmt->setFetchMode(PDO::FETCH_ASSOC); 
			$user = $stmt->fetchAll();
			$user = $user[0];
			
			
			if ($user['flow_email']!=null and $user['flow_password_hash']!=null){
				// Polar flow
				include('config.php');
				
				if (!isset($_REQUEST['week'])){
					$_REQUEST['week'] = 0;
				}

				$end_date = date('d.m.Y', time() + ($_REQUEST['week']*7*24*60*60));
				$start_date = date('d.m.Y',  time() - 7 * 24 * 60 * 60 + ($_REQUEST['week']*7*24*60*60));

				$post_fields = 'returnUrl=https%3A%2F%2Fflow.polar.com%2F&email=' . urlencode($user['flow_email']) . '&password=' . urlencode(mc_decrypt($user['flow_password_hash'], ENCRYPTION_KEY));

				$ch = curl_init();
				
				curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
				curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
				curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36');
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				
				curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/logout');

				$arr = curl_exec($ch); //logout of old session

				// curl_setopt($ch, CURLOPT_POST, 0);
				// curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/ajaxLogin');

				// $arr = curl_exec($ch); //get login page

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
				
				if (is_array($activity_arr)){
					if (!empty($activity_arr)){
						foreach ($activity_arr as $activity) {
						
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
						
						
						
						
							if ($activity->type == 'EXERCISE') { //don't care about other data
								// echo $activity->url . '... <br>';
								echo '<tr>';

								$date = substr($activity->datetime, 0, 10);
								$time = substr($activity->datetime, 11, 8);
								$datetime = strtotime($date . " " . $time);
								$dateformatted = date("l j M Y; H:i:s", strtotime($date . " " . $time));
								// $dateformatted = date("l j M Y; H:i:s", strtotime($activity->datetime));
								?>
								<td>
									<img src='<?php echo($activity->iconUrl); ?>' style='background: #d10027;' width='40' height='40' align='left' hspace="10">
									<a href="https://flow.polar.com<?php echo($activity->url); ?>" target="_new"><?php echo($activity->acttype); ?></a><br>
									<?php echo($dateformatted); ?><br>
									<?php echo(round($activity->distance/100)/10); ?> km - <?php echo(round($activity->duration/100/60/60)/10); ?> hours
								</td>
						
								<?php					
									$params = array('after' => $datetime-(5*60+10), 'per_page' => 1);
									$activities = $strava->makeApiCall('athlete/activities', $params);
								?>
								
								<td class="text-center">
									<?php
										if (is_array($activities) and abs( strtotime($activities[0]->start_date_local) - strtotime($activity->datetime) ) < (5*60)) { // start within 5 minutes
											?><span class="glyphicon glyphicon-ok" aria-hidden="true"></span><?php
										} else {
											?>
											<button type="button" class="btn btn-primary btn-arrow-right" data-toggle="modal" data-target="#toStravaModal" data-listitemid="<?php echo($activity->listItemId); ?>" data-stravaname="<?php echo($activity->datetime); ?>" data-stravatype="<?php echo($activity->stravatype); ?>">
											  <span class="glyphicon glyphicon-upload" aria-hidden="true"></span> Upload to Strava
											</button>					
											<?php
										}									
									?>
								</td>
								
								<td class="text-right">
								<?php
									if (is_array($activities) and abs( strtotime($activities[0]->start_date_local) - strtotime($activity->datetime) ) < (5*60)) { // start within 5 minutes
										?><div data-toggle='popover' data-placement='bottom'><a href='https://www.strava.com/activities/<?php echo($activities[0]->id); ?>' target='_new'><?php echo($activities[0]->name); ?>
										<?php
										if ($activities[0]->private){
											?> <span class="glyphicon glyphicon-lock"></span><?php
										}
										?>
										<br>
										<img src='https://maps.googleapis.com/maps/api/staticmap?size=160x100&path=weight:3%7Ccolor:red%7Cenc:<?php echo($activities[0]->map->summary_polyline); ?>' width='160' height='100'></a></div><?php
									}
								?>
								</td>
								<?php
							}					
						}
					} else {
						?><tr><td colspan="4">No activities found on Polar Flow.</td></tr><?php
					}
				} else {
					?><tr><td colspan="4">No activities found on Polar Flow.</td></tr><?php
				}
				
				if ($activity_arr->message != 'You are not signed in. You are redirected to the sign-in page in 5 seconds.'){
				} else { // not logged in
					die ("Error: " . $activity_arr->message);
				}
			}
		} catch(PDOException $e) { // try to retrieve $user from database
			die ("Error: " . $e->getMessage());
		}		
	}
} else {
	die ("Error: no code given.");
}
?>
