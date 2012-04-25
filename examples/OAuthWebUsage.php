<?php
/**
 * An OAuth example for Yapafo OSM_OAuth.
 */

$OSM_Api_filename = __DIR__ . '/../lib/OSM/Api.php';
if (!file_exists($OSM_Api_filename))
{
	echo 'ERROR, Could not find OSM/Api.php ("' . $OSM_Api_filename . '")' . "\n";
}
require_once($OSM_Api_filename);

session_start();

include(__DIR__ . '/secrets.php');
$consumer_key = $AUTH_OAUTH_CONSUMER_KEY_DEV;
$consumer_secret = $AUTH_OAUTH_CONSUMER_SECRET_DEV;
$requestTokenUrl = OSM_Auth_OAuth::REQUEST_TOKEN_URL_DEV;
$accessTokenUrl = OSM_Auth_OAuth::ACCESS_TOKEN_URL_DEV;
$authorizeUrl = OSM_Auth_OAuth::AUTHORIZE_TOKEN_URL_DEV;

$api_url = OSM_Api::URL_DEV_UK;

$applicationName = str_replace('.php', '', basename(__FILE__));

_wl('Running ' . $applicationName);

// osm api handler is instantiated if necessary
if (!isset($_SESSION['api']))
{
	_wl('Create API instance');
	$api = new OSM_Api(array(
			'appName' => $applicationName, 'url' => $api_url
		));
	$_SESSION['api'] = $api;
}

//OSM_ZLog::configure(array('handler'=>'error_log','level' => OSM_ZLog::LEVEL_DEBUG));

// Have you already got an OAuth object ?
if (!isset($_SESSION['oauth']))
{
	_wl('Create OAUTH instance');
	$_SESSION['oauth'] = new OSM_Auth_OAuth($consumer_key, $consumer_secret,
			array(
				'requestTokenUrl' => $requestTokenUrl,
				'accessTokenUrl' => $accessTokenUrl,
				'authorizeUrl' => $authorizeUrl
			)
	);
	$_SESSION['api']->setCredentials($_SESSION['oauth']);
}

// If a callback url has been set for consumer application
if (isset($_REQUEST["oauth_token"]))
{
	_wl('User coming back via callback url.');
	//$credentials = $_SESSION['oauth']->requestAccessToken();
	//$_SESSION['oauth']->setToken( $credentials["token"], $credentials["tokenSecret"] );

	$creds = $_SESSION['oauth']->getRequestToken();
	if ($creds['token'] == $_REQUEST["oauth_token"])
	{
		$_SESSION['oauth']->requestAccessToken();
	}
	else
	{
		echo '<p>ERROR, oauth token does not match !</p>'."\n";
	}
}

if (isset($_REQUEST['go']))
{
	if (!$_SESSION['oauth']->hasAccessToken())
	{
		try
		{
			// try to get a access token
			$_SESSION['oauth']->requestAccessToken();
		}
		catch (OSM_HttpException $ex)
		{
			_wl('Could not get access. http:' . $ex->getHttpCode());
			// if it fails, 
			if ($ex->getHttpCode() == '401')
			{
				_wl('Request access authorization');
				$req = $_SESSION['oauth']->requestAuthorizationUrl();
				//$_SESSION['oauth']->setToken( $req["token"], $req["tokenSecret"] );
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
			<li>API: <?php echo (isset($_SESSION['api']) ? 'true' : 'false') ?></li>
			<li>OAUTH: <?php echo (isset($_SESSION['oauth']) ? 'true' : 'false') ?></li>
			<li>Access token: <?php echo ($_SESSION['oauth']->hasAccessToken() ? 'true' : 'false') ?></li>
		</ul>

		<?php
		if (isset($_SESSION['oauth']) && $_SESSION['oauth']->hasAccessToken())
		{
			?>
			<p>
				The application is autorized.<br/>
				Here are user's details:
			</p>
				<?php
				
				try{
				$ud = $_SESSION['api']->getUserDetails();
				?>
		<ul>
			<li>Username: <?php echo $ud->getName()?></li>
			<li>Description: <?php echo $ud->getDescription()?></li>
			<li>Terms: <?php $terms = $ud->getTerms(); echo ($terms['pd']===true?'true':'false').'/'.($terms['agreed']===true?'true':'false'); ?></li>
		</ul>
			<pre>
				<?php
				$ud = $ud->getDetails();
				echo print_r($ud, true);
				?>
			</pre>
			<?php
				}
				catch(OSM_HttpException $ex)
				{
					?>
					<p>We've got Access Token but access failed: <?php $ex->getMessage(); ?>
					<?php
				}
		}
		else
		{
			?>
			<p>
				Click <a href="http://localhost/Cartographie/OSM/yapafo/examples/OAuthWebUsage.php?go=1">start<a/> to launch the autorization processus.
			</p>
<?php } ?>

	</body>
</html>
