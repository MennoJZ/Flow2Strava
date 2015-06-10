<?php

require 'stravaV3/Strava.php';
include('connect.php');
		
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

$parameters = []; //array('activity_type'=>$_POST['activity_type'], 'name'=>$_POST['stravaname'], 'description'=>$_POST['description'], 'data_type'=>'tcx', 'external_id'=>$_POST['listItemId'], 'file'=>'@'.$tcxnamewdir);	
$status = $strava->makeApiCall('uploads/' . $_REQUEST['strava_upload_id']);

// echo('uploads/' . $_REQUEST['strava_upload_id']);

if (!is_null($status->error)){
	http_response_code(400);
	?><div class="alert alert-danger" role="alert"><strong><?php echo($status->status); ?></strong> <?php echo($status->error); ?></div><?php
	if (strpos($status->error,'duplicate') !== false) {
		$status->activity_id = substr($status->error, strrpos($status->error, ' ') + 1);
		?>
		<div class="row">
			<div class="col-xs-6 col-sm-3"></div>
			<div class="col-xs-6 col-sm-3" style="text-align: center;">
				<a class="btn btn-primary btn-lg" href="https://www.strava.com/activities/<?php echo($status->activity_id); ?>/" role="button">View duplicate on Strava</a>
			</div>
			<div class="col-xs-6 col-sm-3" style="text-align: center;">
				<a class="btn btn-primary btn-lg" href="http://www.flow2strava.com/?code=<?php echo($_REQUEST['code']); ?>" role="button">Back to Flow2Strava</a>
			</div>
			<div class="col-xs-6 col-sm-3"></div>
		</div>
		<?php
	}
} elseif (is_null($status->activity_id)) {
	http_response_code(300);
	?><div class="alert alert-info" role="alert"><img src="images/loader.gif"> <?php echo($status->status); ?></div><?php
} else {
	http_response_code(200);
	?><div class="alert alert-success" role="alert"><?php echo($status->status); ?></div><?php
	
		try {
			$stmt = $conn->prepare("UPDATE `uploads` SET `strava_activity_id` = '". $status->activity_id ."' WHERE `strava_upload_id` = '". $status->id ."'"); 
			$stmt->execute();
		}
		catch(PDOException $e) {
// 			echo "Error: " . $e->getMessage();
		}
		
	?>
	<div class="row">
		<div class="col-xs-6 col-sm-3"></div>
		<div class="col-xs-6 col-sm-3" style="text-align: center;">
			<a class="btn btn-primary btn-lg" href="https://www.strava.com/activities/<?php echo($status->activity_id); ?>/" role="button">View on Strava</a>
		</div>
		<div class="col-xs-6 col-sm-3" style="text-align: center;">
			<a class="btn btn-primary btn-lg" href="http://www.flow2strava.com/?code=<?php echo($_REQUEST['code']); ?>" role="button">Back to Flow2Strava</a>
		</div>
		<div class="col-xs-6 col-sm-3"></div>
	</div>
	
	<?php
}

?>

<!-- <pre><?php print_r($status); ?></pre> -->
