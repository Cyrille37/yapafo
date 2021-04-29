<?php
/**
 * An OAuth tool for Yapafo OSM_OAuth.
 */
//
// Loading Yapafo library
//

require_once('../vendor/autoload.php');

use Cyrille37\OSM\Yapafo\OSM_Api ;
use Cyrille37\OSM\Yapafo\Auth\OAuth ;

//
// Let's go !
//
// Phase 2
$consumerKey = isset($_REQUEST['consumerKey']) ? $_REQUEST['consumerKey'] : null;
$consumerSecret = isset($_REQUEST['consumerSecret']) ? $_REQUEST['consumerSecret'] : null;

// Phase 3
$authUrl = null;
$authReqToken = isset($_REQUEST['authReqToken']) ? $_REQUEST['authReqToken'] : null;

$authReqTokenSecret = isset($_REQUEST['authReqTokenSecret']) ? $_REQUEST['authReqTokenSecret'] : null;

$authAccessToken = null;
$authAccessTokenSecret = null;

if( empty($consumerKey) )
{
	$oauth = new OAuth($consumerKey, $consumerSecret, array(
		// for DEV server
		'base_url' => OAuth::BASE_URL_DEV
	));
}
else
{
	$oauth = new OAuth($consumerKey, $consumerSecret, array(
			// for DEV server
			'base_url' => OAuth::BASE_URL_DEV
		));
	$osmApi = new OSM_Api(array(
			'url' => OSM_Api::URL_DEV_UK
		));
	$osmApi->setCredentials($oauth);


	if (empty($authReqToken) && empty($authReqTokenSecret))
	{
		$authCredentials = $oauth->requestAuthorizationUrl();
		$authUrl = $authCredentials['url'];
		$authReqToken = $authCredentials['token'];
		$authReqTokenSecret = $authCredentials['tokenSecret'];
	}
	else
	{
		$oauth->setRequestToken($authReqToken, $authReqTokenSecret);
		$authCredentials = $oauth->requestAccessToken();
		$authAccessToken = $authCredentials['token'];
		$authAccessTokenSecret = $authCredentials['tokenSecret'];
	}
}

$oauth_options = $oauth->getOptions();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<!--
		An OAuth example for Yapafo OSM_OAuth.
  -->
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8"
					lang="en"></meta>
		<title>OSM OAuth request access</title>
		<style type="text/css">
			ol>li {
				border-top-style: dashed;
				border-top-color: #aaa;
				list-style-type: upper-roman;
				padding: 1.5em;
				margin-right: 1.5em;
			}
			.allowed { color: green; }
			.forbidden {color: red; }
		</style>
	</head>
	<body>
		<h1>OSM OAuth request access</h1>

		<ol>
			<li>The first step is to create an application credentials aka "Consumer Key" and "Consumer
				Secret" at
				<a href="<?php echo $oauth_options['base_url'] ?>/user/[YOUR USERNAME]/oauth_clients">
				<?php echo $oauth_options['base_url'] ?>/user/[YOUR USERNAME]/oauth_clients</a>.
			</li>
			<li>Then fill them here:
				<form method="POST">
					<label for="consumerKey">Consumer Key:</label>
					<input name="consumerKey" type="text" value="<?php echo $consumerKey; ?>" size="64" />
					<br/>
					<label for="consumerSecret">Consumer Secret:</label>
					<input name="consumerSecret" type="text" value="<?php echo $consumerSecret; ?>" size="64" />
					<br /> <input type="submit" value="Ask authorization url" />
					<?php if (!empty($authUrl)) { ?>
						<!-- Got an Authorization result -->
						<p>
							Let's go to <a href="<?php echo $authUrl ?>" target="_blank"><?php echo $authUrl ?></a>
							to Accept the application authorization request.<br />
							Then come back here to process the next step.
						</p>
					<?php } ?>
				</form>
				<p>
					
				</p>
			</li>
			<li>
				<?php if( empty($authAccessToken) ) { ?>
					<!-- Need to ask a access token -->
					<form method="POST">
						The Access Token will be used by the application to use your account
						for her self. <br />
						<input type="hidden" name="consumerKey" value="<?php echo $consumerKey; ?>" size="64" />
						<input type="hidden" name="consumerSecret" value="<?php echo $consumerSecret; ?>" size="64" />
						<input type="hidden" name="authReqToken" value="<?php echo $authReqToken; ?>" />
						<input type="hidden" name="authReqTokenSecret" value="<?php echo $authReqTokenSecret; ?>" />
						<input type="submit" value="Get access token" />
					</form>
				<?php } else { ?>
					<!-- Got an access token -->
					<p>
						<label for="authAccessToken">Access Token:</label>
						<input name="authAccessToken" type="text" readonly="readonly"
							value="<?php echo $authAccessToken; ?>" size="64" />
						<br />
						<label for="authAccessTokenSecret">Access Token Secret:</label>
						<input name="authAccessTokenSecret" type="text" readonly="readonly"
							value="<?php echo $authAccessTokenSecret; ?>" size="64" />
					</p>
					<p>You given to this token those capabilities:</p>
					<ul>
						<li>Read user preferences: <b><?php echo ($osmApi->isAllowedTo(OSM_Api::PERMS_READ_PREFS, true) ? 'allowed' : 'forbidden'); ?></b></li>
						<li>Write user preferences: <b><?php echo ($osmApi->isAllowedTo(OSM_Api::PERMS_WRITE_PREFS) ? 'allowed' : 'forbidden'); ?></b></li>
						<li>Access write user diary: <b><?php echo ($osmApi->isAllowedTo(OSM_Api::PERMS_WRITE_DIARY) ? 'allowed' : 'forbidden'); ?></b></li>
						<li>Write api (change the map): <b><?php echo ($osmApi->isAllowedTo(OSM_Api::PERMS_WRITE_API) ? 'allowed' : 'forbidden'); ?></b></li>
						<li>Load user gpx traces: <b><?php echo ($osmApi->isAllowedTo(OSM_Api::PERMS_READ_GPX) ? 'allowed' : 'forbidden'); ?></b></li>
						<li>Upload user gpx traces: <b><?php echo ($osmApi->isAllowedTo(OSM_Api::PERMS_WRITE_GPX) ? 'allowed' : 'forbidden'); ?></b></li>
						<li>Modify user map notes: <b><?php echo ($osmApi->isAllowedTo(OSM_Api::PERMS_WRITE_NOTE) ? 'allowed' : 'forbidden'); ?></b></li>
					</ul>
					<?php
				}
				?>
			</li>
		</ol>

	</body>
</html>
