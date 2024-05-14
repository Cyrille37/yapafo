<?php
/**
 * An OAuth2 tool for Yapafo.
 */

require_once( __DIR__.'/../vendor/autoload.php');

use Cyrille37\OSM\Yapafo\OSM_Api ;
use Cyrille37\OSM\Yapafo\Auth\OAuth ;
use Cyrille37\OSM\Yapafo\Tools\Config ;

define('COOKIE','osmoauth2');

/*
Toto no-confid OOB

app_id: YnSZLf34I1WKvZJ8ErkBer-kOteRJMf5TNUl4zdhGyE
app_secret: hVS-YfaP8Sem4BPQOTmUiYUesWCpHH62DaSM_M6Ly0o
redirect: urn:ietf:wg:oauth:2.0:oob

*/

$data = [
	'base_url' => 'https://master.apis.dev.openstreetmap.org/',
	'app_id' => null ,
	'app_secret' => null,
	//'app_redirect' => 'https://example.com',
	'app_redirect' => 'urn:ietf:wg:oauth:2.0:oob',
	'authorizationUrl' => null ,
	'accessCode' => null,
	'oauth2state' => null ,
	'accessToken' => null,
	'refreshToken' => null,
];

// Update data

if( isset($_COOKIE[constant('COOKIE')]))
{
	$data = unserialize($_COOKIE[constant('COOKIE')]);
}

$data['app_id'] = isset($_REQUEST['app_id']) ? $_REQUEST['app_id'] : $data['app_id'];
$data['app_secret'] = isset($_REQUEST['app_secret']) ? $_REQUEST['app_secret'] : $data['app_secret'];
$data['accessCode'] = isset($_REQUEST['accessCode']) ? $_REQUEST['accessCode'] : $data['accessCode'];

// Processing OAuth

$osmProvider = null ;

if( isset($data['app_id']))
{
	$osmProvider = new \JBelien\OAuth2\Client\Provider\OpenStreetMap([
		'clientId'     => $data['app_id'],
		'clientSecret' => $data['app_secret'],
		'redirectUri'  => $data['app_redirect'],
		'dev'          => true // Whether to use the OpenStreetMap test environment at https://master.apis.dev.openstreetmap.org/
	]);

	if( ! isset($data['accessCode']) )
	{

			// The authorization grant type is not supported by the authorization server
		//$data['accessToken'] = $osmProvider->getAccessToken('client_credentials');

		// Options are optional, defaults to 'read_prefs' only
		$options = ['scope' => 'read_prefs read_gpx'];
		$data['authorizationUrl'] = $osmProvider->getAuthorizationUrl($options);
		$data['oauth2state'] = $osmProvider->getState();
	}

	if( isset($data['accessCode']) && (! isset($data['accessToken'])) )
	{
		/** @var \League\OAuth2\Client\Token\AccessToken $accessToken */
		$accessToken = $osmProvider->getAccessToken(
			'authorization_code', ['code' => $data['accessCode'] ]
		);
		$data['accessToken'] = $accessToken->getToken();
		$data['refreshToken'] = $accessToken->getRefreshToken();
	}

}

setcookie(constant('COOKIE'), serialize($data), time() + 3600);

print_r($data);


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
			<li>The first step is to create an application.
				<a href="<?php echo $data['base_url'] ?>/oauth2/applications">
					<?php echo $data['base_url'] ?>/oauth2/applications
				</a>.
			</li>
			<li>Then fill them here:
				<form method="POST">
					<label for="app_id">Application ID:</label>
					<input name="app_id" type="text" value="<?php echo $data['app_id']; ?>" size="64" />
					<br/>
					<label for="app_secret">Application Secret:</label>
					<input name="app_secret" type="text" value="<?php echo $data['app_secret']; ?>" size="64" />
					<br /> <input type="submit" value="Ask authorization url" />
				</form>
				<p>
				</p>
			</li>

			<?php if( isset($data['authorizationUrl']) ) { ?>
			<li>
				Visit url <a href="<?php echo $data['authorizationUrl']?>"><?php echo $data['authorizationUrl']?></a>
				and copy here the code :
				<form method="POST">
					<label for="accessCode">Access Code:</label>
					<input id="accessCode" name="accessCode" type="text" value="<?php echo $data['accessCode']; ?>" size="64" />
					<br />
					<input type="submit" value="Ask access token" />
				</form>
			</li>
			<?php } ?>

			<li>
				<?php if( ! empty($data['accessToken']) ) { ?>


				<?php
					$resourceOwner = $osmProvider->getResourceOwner($data['accessToken']);
					echo '<pre>', print_r($resourceOwner,true) ,'</pre>';
				}
				?>
			</li>
		</ol>

	</body>
</html>
