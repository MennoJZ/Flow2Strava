<?php

/**
 * Note: If you're not using a PS-R compliant autoloader you'll need to manually include Base.php and Strava.php.

 * You'll also need to remove the namespacing when instantiating.
 */

require_once 'config.php';
require 'stravaV3/Strava.php';
include 'connect.php';
include 'functions.php';


//
if (isset($_GET['state']) and $_GET['state']=='dev' and isset($_GET['code'])){
	header('Location: http://dev.flow2strava.com/?code='. $_GET['code']);
}

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

$stravalink = $strava->requestAccessLink('write',array_shift((explode(".",$_SERVER['HTTP_HOST']))),'auto');

if (isset($_GET) && isset($_GET['code'])) {
	$athlete = $strava->makeApiCall('athlete');

	if ($athlete->message == "Authorization Error" or isset($_GET['error'])){
		// echo ("<b>". $athlete->message ."</b><br>");
		// echo ("Field: " . $athlete->errors[0]->field ."<br>");
		// echo ("Code: " . $athlete->errors[0]->code ."<br>");
	} else {
		if (isset($_GET['logout_polar'])){
			try {
				$sql = "UPDATE `users` SET `flow_email` = null, `flow_password_hash` = null
				WHERE `strava_id` = '". $athlete->id ."'";
				// use exec() because no results are returned
				$conn->exec($sql);
		// 					echo "New record created successfully";
			}
			catch(PDOException $e){
				echo $sql . "<br>" . $e->getMessage();
			}  

		}	
		
		
		if (isset($_POST) and isset($_POST['flow_email']) and isset($_POST['flow_password'])){
// 						echo "Flow login!";
		
			try {
				$sql = "UPDATE `users` SET `flow_email` = '". $_POST['flow_email'] ."', `flow_password_hash` = '". mc_encrypt($_POST['flow_password'], ENCRYPTION_KEY) ."'
				WHERE `strava_id` = '". $athlete->id ."'";
				// use exec() because no results are returned
				$conn->exec($sql);
// 					echo "New record created successfully";
			}
			catch(PDOException $e){
				echo $sql . "<br>" . $e->getMessage();
			}  
		}
		
		try {
			$sql = "INSERT INTO `users` (`strava_id`, `strava_firstname`, `strava_lastname`, `strava_email`, `first_used`,  `last_used`)
			VALUES ('". $athlete->id ."', '". $athlete->firstname ."', '". $athlete->lastname ."', '". $athlete->email ."', NOW(), NOW())
			ON DUPLICATE KEY UPDATE
			  `strava_firstname` = '". $athlete->firstname ."',
			  `strava_lastname` = '". $athlete->lastname ."',
			  `strava_email` = '". $athlete->email ."',
			  `strava_code` = '". $_GET['code'] ."',
			  `strava_athlete_json` = '". json_encode($athlete) ."',
			  `last_used` = NOW()";
			// use exec() because no results are returned
			$conn->exec($sql);
// 					echo "New record created successfully";
		}
		catch(PDOException $e){
			echo $sql . "<br>" . $e->getMessage();
		}      			
		
		
		try {
			$stmt = $conn->prepare("SELECT * FROM `users` WHERE `strava_id` = '". $athlete->id ."'"); 
			$stmt->execute();

			// set the resulting array to associative
			$user = $stmt->setFetchMode(PDO::FETCH_ASSOC); 
			$user = $stmt->fetchAll();
			$user = $user[0];
		}
		catch(PDOException $e) {
			echo "Error: " . $e->getMessage();
		}							
	}				
}



?>


<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="Automatically synchronize or manually upload with one click your Polar Flow training files directly to Strava" />
    <meta name="author" content="Menno Zuidema" />
    <link rel="icon" href="images/flow2strava.ico" />

    <title>Flow2Strava</title>

    <!-- Bootstrap core CSS -->
    <link href="bootstrap-3.3.4-dist/css/bootstrap.min.css" rel="stylesheet">

	<!-- Include Font Awesome Stylesheet in Header -->
	<link href="//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="css/sticky-footer.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="bootstrap-3.3.4-dist/css/jumbotron.css" rel="stylesheet">
    <link href="css/strava.css" rel="stylesheet">
    <link href="css/flow.css" rel="stylesheet">
    <link href="css/css.css" rel="stylesheet">
    <link href="bootstrap-3.3.4-dist/css/navbar-fixed-top.css" rel="stylesheet">
    <link href="bootstrap-3.3.4-dist/css/sticky-footer.css" rel="stylesheet">
    <link href="bootstrap-3.3.4-dist/css/btn-arrow.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

  </head>

  <body>
<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/en_US/sdk.js#xfbml=1&version=v2.3&appId=373251786199497";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>  

    
	  
	  
	  
	<nav class="navbar navbar-default navbar-fixed-top">
		<div class="container">
          <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
              <span class="sr-only">Toggle navigation</span>
              <span class="icon-bar"></span>
              <span class="icon-bar"></span>
              <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand">Flow2Strava</a>
          </div> <!--/ .navbar-header -->
          <div id="navbar" class="navbar-collapse collapse">
            <ul class="nav navbar-nav">
				<?php
				if (isset($_GET['p']) and $_GET['p']=='learnmore'){
					$learnmoreactive = 'class="active"';
				} elseif (isset($_GET['p']) and $_GET['p']=='discuss'){
					$discussactive = 'class="active"';
				} else {
					$homeactive = 'class="active"';
				}
				?>
			
              <li <?php echo($homeactive); ?>><a href="/">Home</a></li>
              <li <?php echo($learnmoreactive); ?>><a href="?p=learnmore">Learn more</a></li>
              <li <?php echo($discussactive); ?>><a href="?p=discuss">Discuss</a></li>
              <!--li class="dropdown">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">Dropdown <span class="caret"></span></a>
                <ul class="dropdown-menu" role="menu">
                  <li><a href="#">Action</a></li>
                  <li><a href="#">Another action</a></li>
                  <li><a href="#">Something else here</a></li>
                  <li class="divider"></li>
                  <li class="dropdown-header">Nav header</li>
                  <li><a href="#">Separated link</a></li>
                  <li><a href="#">One more separated link</a></li>
                </ul>
              </li-->
            </ul>
            <ul class="nav navbar-nav navbar-right">
				<?php 
				if (isset($_GET) and isset($_GET['code'])){
					$loginlink = '/';
					$logintext = 'Log out';
				} else {
					$loginlink = $stravalink;
					$logintext = 'Log in';
				}
				?>
              <li><a href="<?php echo($loginlink); ?>"><?php echo($logintext); ?> <span class="sr-only">(current)</span></a></li>
              <!--li><a href="../navbar-static-top/">Static top</a></li>
              <li><a href="../navbar-fixed-top/">Fixed top</a></li-->
            </ul>
          </div><!--/.nav-collapse -->
	    </div><!--/.container -->
	</nav>
	
	
    <!-- Main jumbotron for a primary marketing message or call to action -->
    <?php if (!isset($_GET['code']) and !isset($_GET['p']) and !isset($_GET['error'])) {
		try {
			$stmt = $conn->prepare("SELECT count(*) as `count` FROM `users`"); 
			$stmt->execute();

			// set the resulting array to associative
			$users = $stmt->setFetchMode(PDO::FETCH_ASSOC); 
			$users = $stmt->fetchAll();
			$users = $users[0];
		}
		catch(PDOException $e) {
			echo "Error: " . $e->getMessage();
		}
		
		try {
			$stmt = $conn->prepare("SELECT count(*) as `count` FROM `uploads` WHERE `strava_activity_id` IS NOT NULL");
			$stmt->execute();

			// set the resulting array to associative
			$uploads = $stmt->setFetchMode(PDO::FETCH_ASSOC); 
			$uploads = $stmt->fetchAll();
			$uploads = $uploads[0];
		}
		catch(PDOException $e) {
			echo "Error: " . $e->getMessage();
		}
		
		
    ?>
	<div class="container">
		<div class="jumbotron">
		
			<h1>What? Flow2Strava?</h1>
			<p>Having a fantastic Polar <abbr title="V800" id="popover-v800">V800</abbr>, <abbr title="M400" id="popover-m400">M400</abbr>, Loop, <abbr title="A300" id="popover-a300">A300</abbr>, <abbr title="V650" id="popover-v650">V650</abbr> or <abbr title="M450" id="popover-m450">M450</abbr> and using the <a href="https://flow.polar.com" data-toggle="tooltip" data-placement="bottom" title="flow.polar.com">Flow webservice</a>? Tired of exporting TCX files to your computer and uploading them to Strava?</p>
			<p>[Update] "<i>Polar <a href="http://www.dcrainmaker.com/2015/06/polars-cycling-computer.html#strava-and-other-polar-flow-updates">announced</a> that in October they’ll introduce the ability to have your activities automatically sync to Strava from Polar Flow.</i>" Because October still lasts <?php echo(round(abs(strtotime("2015/10/01") - time())/86400)); ?> days and October might be late this year, you can use this tool until then!</p>
			<p>Already <kbd><?php echo($users['count']); ?></kbd> athletes uploaded <kbd><?php echo($uploads['count']); ?></kbd> activities from Polar Flow to Strava using this tool.</p>
			<p><a class="btn btn-primary btn-lg" href="?p=learnmore" role="button">Learn more &raquo;</a></p>
		</div>
	</div> <!-- /container -->
	<?php } ?>
	
	

	
      <?php 			
		if (isset($_GET) && isset($_GET['code']) and $athlete->message != "Authorization Error" and !isset($_GET['error'])) {
			$athlete = $strava->makeApiCall('athlete');
			?>
				<div class="container">
				  <!-- Example row of columns -->
				  <div class="row">
				
				<div class="col-md-4" style="">
				<?php if ($user['flow_email']!=null and $user['flow_password_hash']!=null){
					// Polar flow
					$end_date = date('d.m.Y');
					$start_date = date('d.m.Y', time() - 1 * 24 * 60 * 60);

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
				
					if ($activity_arr->message == 'You are not signed in. You are redirected to the sign-in page in 5 seconds.'){
						?>
						<div class="alert alert-danger" role="alert">
						  <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
						  <span class="sr-only">Error:</span>
						  Invalid email or password for Polar Flow.
						</div>
						
						  <center><form class="form-signin" style="max-width: 330px;" name="flowlogin" action="<?php echo(basename($_SERVER['PHP_SELF']) . "?" . $_SERVER['QUERY_STRING']); ?>" method="post">
							<h2 class="form-signin-heading">Sign in to Polar Flow</h2>
							<label for="inputEmail" class="sr-only">Email address</label>
							<div class="input-group">
								<span class="input-group-addon glyphicon glyphicon-envelope" id="basic-addon1"></span>
								<input type="email" id="inputEmail" name="flow_email" class="form-control" placeholder="Email address" required value="<?php echo($user['strava_email']); ?>">
							</div>
							<label for="inputPassword" class="sr-only">Password</label>
							<div class="input-group">
								<span class="input-group-addon glyphicon glyphicon-asterisk" id="basic-addon1"></span>
								<input type="password" id="inputPassword" name="flow_password" class="form-control" placeholder="Password" required value="<?php echo(mc_decrypt($user['flow_password_hash'], ENCRYPTION_KEY)); ?>">
							</div>
							<br>
							<div class='clear'></div>
							<button class="btn btn-lg btn-primary btn-block" type="submit" name="flowlogin">Sign in</button>
						  </form></center>
						
						<?php
					} else {
						?>
						<div class="flow" style="width: 300px; margin-left: 10%;">
						  <div id="flowathlete" class="flowcard">
							<h3 class="flow-card-header primary">Polar Flow</h3>
							<div class="flow-athlete-identity content" style="min-height: 150px;">
								<img src="images/profile-image-flow.png" style="border: 1px solid #000;" width="60" height="60">
								<?php echo($user['flow_email']); ?>
								<br><br><center><a href="<?php echo(basename($_SERVER['PHP_SELF']) . "?" . $_SERVER['QUERY_STRING']); ?>&logout_polar" class="btn btn-default" role="button"><span class="glyphicon glyphicon-log-out"></span> Log out from Polar Flow</a></center>
							</div>
						  </div>
						</div>

						<?php
					} ?> 
				
				<?php } else { ?>
				  <center><form class="form-signin" style="max-width: 330px;" name="flowlogin" action="<?php echo(basename($_SERVER['PHP_SELF']) . "?" . $_SERVER['QUERY_STRING']); ?>" method="post">
					<h2 class="form-signin-heading">Sign in to Polar Flow</h2>
					<label for="inputEmail" class="sr-only">Email address</label>
					<div class="input-group">
						<span class="input-group-addon glyphicon glyphicon-envelope" id="basic-addon1"></span>
						<input type="email" id="inputEmail" name="flow_email" class="form-control" placeholder="Email address" required value="<?php echo($user['strava_email']); ?>">
					</div>
					<label for="inputPassword" class="sr-only">Password</label>
					<div class="input-group">
						<span class="input-group-addon glyphicon glyphicon-asterisk" id="basic-addon1"></span>
						<input type="password" id="inputPassword" name="flow_password" class="form-control" placeholder="Password" required autofocus>
					</div>
					<br>
					<div class='clear'></div>
					<button class="btn btn-lg btn-primary btn-block" type="submit" name="flowlogin">Sign in</button>
				  </form></center>		
				
				<?php } ?>
				</div>
				
				<div class="col-md-4" style="min-height: 100px; display:block; text-align:center; vertical-align: middle; margin-top: 50px;">
				  <button type="button" class="btn btn-primary btn-arrow-right" disabled>Flow2Strava</button>
			   	</div>

				
				<div class="col-md-4" style="">
					<div class="strava" style="width: 300px; margin-left: 10%;">
					  <div id="athlete" class="card">
						<h3 class="card-header primary">Strava</h3>
						<div class="athlete-identity content" style="min-height: 150px;">
						  <img src="<?php echo($athlete->profile_medium); ?>" class="avatar"/>
						  <div class="basic-info">
							<a href="http://www.strava.com/athletes/<?php echo($athlete->id); ?>" class="name">
							  <?php echo($athlete->firstname . ' ' . $athlete->lastname); ?>
							</a>
							<span class="address">
							  <?php echo($athlete->city); ?>, <?php echo($athlete->state); ?>, <?php echo($athlete->country); ?>
							</span>
						  </div>
						  <br><br>
							<center>
							<a href="<?php echo(basename($_SERVER['PHP_SELF'])); ?>" class="btn btn-default" role="button"><span class="glyphicon glyphicon-log-out"></span> Log out from Strava</a>
							</center>
						</div>
					  </div>
					</div>
				</div>
		
				
				
			  </div>
			  <br><hr>
		<?php 
		} else if ($athlete->message == "Authorization Error" or isset($_GET['error'])){
		?>
		<div class="container">
		
		  <div class="jumbotron">
			<h1>Whoops...!</h1> 
			<p>Strava authorization error</p>
			<?php if (isset($_GET) and isset($_GET['error'])){
				?><pre><?php echo($_GET['error']); ?></pre><?php
			}
			if (isset($athlete) and isset($athlete->errors)){
				?><pre><?php echo($athlete->errors[0]->field); ?> (<?php echo($athlete->errors[0]->code); ?>)</pre><?php
			} ?>
			
		  </div>
		
		  <hr class="featurette-divider">
		  
		  <center><a href='<?php echo($stravalink); ?>'><img src='images/LogInWithStrava@2x.png'></a></center>		
		
		
		</div> <!--/ .container -->
		<?php
		}
		?>

<?php if(isset($_GET['p']) and $_GET['p']=='learnmore'){ ?>

		<div class="container">

      <div class="row featurette">
        <div class="col-md-7">
          <h2 class="featurette-heading">Log in to Strava <span class="text-muted">It'll blow your mind.</span></h2>
          <p class="lead">Log in to Strava first. Strava uses OAuth2 as an authentication protocol. It allows external applications like Flow2Strava to request authorization to a user’s private data without requiring their Strava username and password. It allows users to grant and <a href="https://www.strava.com/settings/apps" target="_new">revoke API access</a> on a per-application basis and keeps users’ authentication details safe.</p>
        </div>
        <div class="col-md-5">
          <img class="featurette-image img-responsive center-block" src="images/strava.png" alt="Strava" width="250">
        </div>
      </div>

      <hr class="featurette-divider">

      <div class="row featurette">
        <div class="col-md-7 col-md-push-5">
          <h2 class="featurette-heading">Then, log in to Polar Flow <span class="text-muted">See for yourself.</span></h2>
          <p class="lead">Enter your username and password to retrieve your last week of Polar Flow training files. Polar does not allow me to use their <a href="http://developer.polar.com/wiki/AccessLink" target="_new">AccessLink API</a>. Therefore I used a workaround made by <a href="https://github.com/grzeg1/polarsync" target="_new">grzeg1</a>. Your username and password are salted and encrypted before saving, but I cannot do otherwise than send them unencrypted via POST request to Polar to authenticate. Your username and password are removed as soon as you logout from Polar Flow in this application.</p>
        </div>
        <div class="col-md-5 col-md-pull-7">
          <img class="featurette-image img-responsive center-block" src="images/polarflow.png" alt="Polar Flow" width="250">
        </div>
      </div>

      <hr class="featurette-divider">

      
      <div class="row featurette">
        <div class="col-md-7">
          <h2 class="featurette-heading">Choose your training to upload to Strava <span class="text-muted">Checkmate.</span></h2>
          <p class="lead">Click on the upload button and enter a title for your activity. Only one click left and your training will be shared on Strava!</p>
        </div>
        <div class="col-md-5">
          <img class="featurette-image img-responsive center-block" src="images/flow2strava.png" width="500" alt="And now the magic happens...">
        </div>
      </div>      
      
      <hr class="featurette-divider">
	  
	  <center><a href='<?php echo($stravalink); ?>'><img src='images/LogInWithStrava@2x.png'></a></center>
	  
	  </div> <!-- /.container -->

<?php
} elseif (isset($_GET['p']) and $_GET['p']=='discuss'){
?>
	<div class="container">
	<?php if (!isset($_GET['code'])){ ?>
		<div class="fb-comments" data-href="http://www.flow2strava.com" data-numposts="5" data-colorscheme="light" data-width="100%" data-order-by="reverse_time"></div>	
	<?php } ?>
	</div> <!-- / container -->

<?php
} else {

	 ?>

	<?php
	try {
		// Authenticated - Strava will redirect the user to the Redicrt URL along with a 'code' _GET variable upon success
		if (isset($_GET) && isset($_GET['code'])) {
			// Send resource request key to the makeApiCall method. A JSON object will be returned.
			// What you do at this point is up to you :)	
			if ($user['flow_email']==null or $user['flow_password_hash']==null){
			} else {
				?>				
				<table width="100%" class="table table-striped table-hover">
					<thead>
						<tr>
							<th></th>
							<th>Type</th>
							<th>Date</th>
							<th>Distance</th>
							<th>Duration</th>
							<th>Url</th>
							<th>TCX file</th>
							<th>On Strava?</th>
						</tr>
					</thead>
					<tbody id="flowactivities">				
						<tr><td colspan="7">Loading...</td></tr>
					</tbody>
				</table>
				
				
				<nav>
				  <ul class="pager">
					<li class="previous"><a href="#"><span aria-hidden="true">&larr;</span> Older</a></li>
					<li style="display: none;" id="currentweek">0</li>
					<li class="next disabled"><a href="#">Newer <span aria-hidden="true">&rarr;</span></a></li>
				  </ul>
				</nav>					
				<?php	
			}
		// Error
		} else if (isset($_GET) && isset($_GET['error'])) {
			// echo '<strong>Error:</strong> '. $_GET['error'] .'<br />';
			// $stravalink = $strava->requestAccessLink('write','','auto');
			// echo "<center><a href='". $stravalink ."'><img src='images/LogInWithStrava@2x.png'></a></center>";
		// Not Authenticated - Will redirect visitor to Strava for approval
		} else {
	//         $strava->requestAccess('write','','auto');
			$stravalink = $strava->requestAccessLink('write',array_shift((explode(".",$_SERVER['HTTP_HOST']))),'auto');
			echo "<center><a href='". $stravalink ."'><img src='images/LogInWithStrava@2x.png'></a></center>";
		}
	} catch (Exception $e) {
		echo $e->getMessage();
	}

	?>
		</div> <!--/ .container -->
		<hr>
		
		
		<div class="modal fade" id="toStravaModal" tabindex="-1" role="dialog" aria-labelledby="toStravaModalLabel" aria-hidden="true">
		  <div class="modal-dialog">
			<div class="modal-content">
				<form role="form" action="sendtostrava.php" method="post" id="tostravaform">
			  <div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="toStravaModalLabel">Upload to Strava</h4>
			  </div>
			  <div class="modal-body">
				  
				  <div class="form-group">
					<label for="listItemId" class="control-label">Flow ID:</label>
					<input type="text" class="form-control listitemid" id="listItemId" name="listItemId" readonly>
				  </div>
				  <div class="form-group">
					<label for="stravaname" class="control-label">Title:</label>
					<input type="text" class="form-control stravaname" id="stravaname" name="stravaname" onclick="this.focus();this.select()">
				  </div>
				  <div class="form-group">
					<label for="description" class="control-label">Description:</label>
					<textarea class="form-control description" id="description" name="description"></textarea>
				  </div>
				  <div class="form-group">
					<label for="activity-type" class="control-label">Activity type:</label>
					<select class="form-control stravatype" id="activity_type" name="activity_type">
						<option>Ride</option>
						<option>Run</option>
						<option>Swim</option>
						<option>Workout</option>
						<option>Hike</option>
						<option>Walk</option>
						<option>Nordicski</option>
						<option>Alpineski</option>
						<option>Backcountryski</option>
						<option>Iceskate</option>
						<option>Inlineskate</option>
						<option>Kitesurf</option>
						<option>Rollerski</option>
						<option>Windsurf</option>
						<option>Snowboard</option>
						<option>Snowshoe</option>
					</select>
				  </div>          
				
				
				  <div class="container loading" style="width: 100%; height: 300px; display: none;">
						<div class="windows8">
							<div class="wBall" id="wBall_1">
								<div class="wInnerBall">
								</div>
							</div>
							<div class="wBall" id="wBall_2">
								<div class="wInnerBall">
								</div>
							</div>
							<div class="wBall" id="wBall_3">
								<div class="wInnerBall">
								</div>
							</div>
							<div class="wBall" id="wBall_4">
								<div class="wInnerBall">
								</div>
							</div>
							<div class="wBall" id="wBall_5">
								<div class="wInnerBall">
								</div>
							</div>
						</div>	  
				  </div>
				</div> <!-- /modal body -->

				
				
				<div class="modal-footer">
				  <button type="button" class="btn btn-default" data-dismiss="modal" id="cancel">Cancel</button>
				  <input type="hidden" name="code" value="<?php echo($_GET['code']); ?>">
				  <button type="submit" class="btn btn-primary" id="tostravabutton"><span class="glyphicon glyphicon-upload" aria-hidden="true"></span> Upload to strava</button>
				</div>	  
				
			  </form>
			</div> <!-- /modal content -->
		  </div>
		</div>		
<?php
}
?>
		
		
      <footer class="footer">
	  <div class="container">
		<div class="row">
			<div class="col-md-3">
        		<p>&copy; <a href="http://www.mennozuidema.nl" target="_new">Menno Zuidema</a> 2015</p>
        	</div>
			<div class="col-md-6">
				<!--a style="display:inline-block;background-color:#FC4C02;color:#fff;padding:5px 10px 5px 30px;font-size:11px;font-family:Helvetica, Arial, sans-serif;white-space:nowrap;text-decoration:none;background-repeat:no-repeat;background-position:10px center;border-radius:3px;background-image:url('http://badges.strava.com/logo-strava-echelon.png')" href='http://strava.com/athletes/5772733/badge' target="_clean">
				Follow me at 
				<img src='http://badges.strava.com/logo-strava.png' alt='Strava' style='margin-left:2px;vertical-align:text-bottom' height=13 width=51 />
				</a-->
			</div>
			<div class="col-md-3">
                <a href="https://www.facebook.com/mennozuidema"><i id="social" class="fa fa-facebook-square fa-3x social-fb"></i></a>
	            <a href="https://twitter.com/MennoZuidema"><i id="social" class="fa fa-twitter-square fa-3x social-tw"></i></a>
        	</div>
		</div>
	  </div> <!-- /container -->   
      </footer>
    


    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
    <script src="bootstrap-3.3.4-dist/js/bootstrap.min.js"></script>
    <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
    <script src="bootstrap-3.3.4-dist/assets/js/ie10-viewport-bug-workaround.js"></script>
        
    <script type="text/javascript">
    $(function () {
	  $('[data-toggle="popover"]').popover()
	})
	
	
	$('#popover-v800').popover({placement: 'bottom', content: '<img src="images/v800.png" width="200">', html: true});
	$('#popover-m400').popover({placement: 'bottom', content: '<img src="images/m400.png" width="200">', html: true});
	$('#popover-a300').popover({placement: 'bottom', content: '<img src="images/a300.png" width="200">', html: true});
	$('#popover-v650').popover({placement: 'bottom', content: '<img src="images/v650.png" width="200">', html: true});
	$('#popover-m450').popover({placement: 'bottom', content: '<img src="images/m450.png" width="200">', html: true});
	
	$(function () {
		$('[data-toggle="tooltip"]').tooltip()
	})
	
	
	$('#toStravaModal').on('show.bs.modal', function (event) {
		var button = $(event.relatedTarget) // Button that triggered the modal
		var listitemid = button.data('listitemid') // Extract info from data-* attributes
		var stravaname = button.data('stravaname') // Extract info from data-* attributes
		var stravatype = button.data('stravatype') // Extract info from data-* attributes
		// If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
		// Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
		var modal = $(this)
		//   modal.find('.modal-title').text('New message to ' + recipient)
		modal.find('.modal-body .form-group .listitemid').val(listitemid);
		modal.find('.modal-body .form-group .stravaname').val(stravaname);
		modal.find('.modal-body .form-group .stravatype').val(stravatype);
		modal.find('.modal-body .form-group .description').val("Uploaded using flow2strava.com");
		$('#stravaname').focus();
	
	})	
    </script>
    
    <script type="text/javascript">
	$('#tostravaform').submit(function(){
 		var $inputs = $( ":input" );
		$inputs.attr('readonly', 'readonly');

		$('.form-group').hide();
		var $button = $('#tostravabutton', this);
		$button.html('Uploading...');
		$button.attr('disabled', 'disabled');
		
		
		var $button = $('#cancel', this);
		$button.attr('disabled', 'disabled');
		
		$('.loading').show();
// 		$button.attr('value', $button.attr('value') + "...");
		
	});  
	</script>  
	
	<script type="text/javascript">
	$( "#flowactivities" ).load( "getflowactivitylist.php?code=<?php echo($_GET['code']); ?>", function() {
	  // alert( "Load was performed." );
	});
	
	$( ".previous" ).click(function() {
		$( "#flowactivities" ).html("<tr><td colspan=7>Loading...</td></tr>");
		$('#currentweek').text( parseInt( $('#currentweek').text(),10 ) - 1 );
		if (parseInt($("#currentweek").text, 10) >= 0){
			$(".next").addClass("disabled");
		} else {
			$(".next").removeClass("disabled");
		}
		$( "#flowactivities" ).load( "getflowactivitylist.php?code=<?php echo($_GET['code']); ?>&week=" + $("#currentweek").text() + "", function() {
			// done
		});
	});
	
	$( ".next" ).click(function() {
		$( "#flowactivities" ).html("<tr><td colspan=7>Loading...</td></tr>");
		$('#currentweek').text( parseInt( $('#currentweek').text(),10 ) + 1 );
		if (parseInt($("#currentweek").text, 10) >= 0){
			$(".next").addClass("disabled");
		} else {
			$(".next").removeClass("disabled");
		}
		$( "#flowactivities" ).load( "getflowactivitylist.php?code=<?php echo($_GET['code']); ?>&week=" + $("#currentweek").text() + "", function() {
			// done
		});
	});	
	</script>
    
  </body>
</html>

<?php
?>