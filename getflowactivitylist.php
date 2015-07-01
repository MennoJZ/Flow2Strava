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
				if (!isset($_REQUEST['week'])){
					$_REQUEST['week'] = 0;
				}

				$end_date = date('d.m.Y', time() + ($_REQUEST['week']*7*24*60*60));
				$start_date = date('d.m.Y',  time() - 7 * 24 * 60 * 60 + ($_REQUEST['week']*7*24*60*60));

				$activity_arr = getFlowActivityArray($start_date, $end_date, $user['flow_email'], mc_decrypt($user['flow_password_hash'], ENCRYPTION_KEY));
				
				if (is_array($activity_arr)){
					if (!empty($activity_arr)){
						foreach ($activity_arr as $activity) {
							$activity = getFlowActivityFromIconUrl($activity);
						
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
// 									$params = array('after' => $activity->start-(3*60*60+10), 'per_page' => 1);
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
