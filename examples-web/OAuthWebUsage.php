<?php
/**
 * 
 */

require_once('../vendor/autoload.php');

use Cyrille37\OSM\Yapafo\OSM_Api ;
use Cyrille37\OSM\Yapafo\Auth\OAuth ;
use Cyrille37\OSM\Yapafo\Exceptions\HttpException;
use Cyrille37\OSM\Yapafo\Tools\Config ;

session_start();

$consumer_key = Config::get('osm_api_consumer_key');
$consumer_secret = Config::get('osm_api_consumer_secret');

//
// Create or retreive the OSM_Api instance
//
if (!isset($_SESSION['api']))
{
	$api = new OSM_Api([
			'url' => Config::get('osm_api_url')
		]);
	$api->setCredentials(
		new OAuth( $consumer_key, $consumer_secret, [
			'base_url' => Config::get('oauth_url')
		])
	);
	$_SESSION['api'] = $api;
	$oauth = $api->getCredentials();
}
else
{
	$api = $_SESSION['api'];
	$oauth = $api->getCredentials();
}

/// Logout
if (isset($_REQUEST['deleteAccess']))
{
	$oauth->deleteAccessAuthorization();
}

// If a callback url has been set for consumer application,
// the user will come back here after authorization acceptation.
// The osm site will callback us with the parameter "oauth_token"
if (isset($_REQUEST['oauth_token']))
{
	_wl('User coming back via callback url.');

	// Check that the callback is for us.
	$creds = $oauth->getRequestToken();
	if ($creds['token'] == $_REQUEST['oauth_token'])
	{
		$oauth->requestAccessToken(
			isset($_REQUEST['oauth_verifier'])
			?$_REQUEST['oauth_verifier']
			:null
			);
	}
	else
	{
		echo '<p>ERROR, oauth token does not match !</p>' . "\n";
	}
}

if (isset($_REQUEST['go']))
{
	if (!$oauth->hasAccessToken())
	{
		try
		{
			// try to get a access token
			$oauth->requestAccessToken();
		}
		catch (HttpException $ex)
		{
			_wl('Could not get access. http:' . $ex->getHttpCode());
			// if it fails, 
			if ($ex->getHttpCode() == '401')
			{
				_wl('Request access authorization');
				$api->clearCachedAuthPermissions();
				$req = $oauth->requestAuthorizationUrl();
				//$oauth->setToken( $req["token"], $req["tokenSecret"] );
				header("Location:" . $req["url"]);
				exit();
			}
		}
	}
}

function _wl($s) {
	global $applicationName, $_wlCounter;
	if (!isset($_wlCounter))
		$_wlCounter = 0;
	$_wlCounter++;
	error_log($applicationName . ' : ' . $s);
	header('X-Yapafo-' . $applicationName . '-' . $_wlCounter . ': ' . $s);
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<!--
		An OAuth example for Yapafo OSM_OAuth.
  -->
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8" lang="en" />
		<title>OSM OAuth request access</title>
		<style type="text/css">
		</style>
	</head>
	<body>
		<h1>OSM OAuth usage example</h1>

		<ul>
			<li>has Access token: <?php echo ($oauth->hasAccessToken() ? 'true' : 'false') ?></li>
		</ul>

		<?php
		if (isset($oauth) && $oauth->hasAccessToken())
		{
			?>
			<p>
				The application is autorized.<br/>
				Here are user's details:
			</p>
			<?php
			try
			{
				$ud = $api->getUserDetails();
				?>
				<ul>
					<li>Username: <?php echo $ud->getName() ?></li>
					<li>Description: <?php echo $ud->getDescription() ?></li>
					<li>Terms: <?php
		$terms = $ud->getTerms();
		echo ($terms['pd'] === true ? 'true' : 'false') . '/' . ($terms['agreed'] === true ? 'true' : 'false');
				?></li>
				</ul>
				<p>Permissions accepted by the user are:</p>
				<ul>
					<li>Allowed to read user preferences: <?php echo ($api->isAllowedToReadPrefs() ? '<i>allowed</i>' : '<b>forbidden</b>'); ?></li>
					<li>Allowed to write user preferences: <?php echo ($api->isAllowedToWritePrefs() ? '<i>allowed</i>' : '<b>forbidden</b>'); ?></li>
					<li>Allowed to access (read/write) user diary: <?php echo ($api->isAllowedToWriteDiary() ? '<i>allowed</i>' : '<b>forbidden</b>'); ?></li>
					<li>Allowed to write api (change the map): <?php echo ($api->isAllowedToWriteApi() ? '<i>allowed</i>' : '<b>forbidden</b>'); ?></li>
					<li>Allowed to load user gpx traces: <?php echo ($api->isAllowedToReadGpx() ? '<i>allowed</i>' : '<b>forbidden</b>'); ?></li>
					<li>Allowed to upload user gpx traces: <?php echo ($api->isAllowedToWriteGpx() ? '<i>allowed</i>' : '<b>forbidden</b>'); ?></li>
				</ul>
				<?php
			}
			catch (OSM_HttpException $ex)
			{
				?>
				<p>
					We've got Access Token but access failed: <span style="color: red;"><?php echo $ex->getMessage(); ?></span>
				</p>
				<?php
			}
			?>
			<p>
				You can revoke this authorization: <a href="<?php echo $_SERVER['PHP_SELF']; ?>?deleteAccess=1">delete access<a/>.<br/>
				Or just reload this page to see that your authorization persists: <a href="<?php echo $_SERVER['PHP_SELF']; ?>">refresh<a/>.
			</p>

			<?php
		}
		else
		{
			?>
			<p>
				Click <a href="<?php echo $_SERVER['PHP_SELF']; ?>?go=1">start<a/> to launch the autorization processus.
			</p>
			<?php
		}
		?>

	</body>
</html>
