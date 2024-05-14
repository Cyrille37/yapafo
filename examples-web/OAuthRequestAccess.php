<?php
/**
 * The OAuth2 tool for Yapafo, to generate a access token.
 *
 * I did not see the effect of the "confidential" flag for Application.
 *
 * Access scopes:
 *	- read_prefs: Lire les préférences de l’utilisateur
 *	- write_prefs: Modifier les préférences de l’utilisateur
 *	- write_diary: Créer des entrées de journal, des commentaires et se faire des amis
 *	- write_api: Modifier la carte
 *	- read_gpx: Lire les traces GPX privées
 *	- write_gpx: Mettre à jour les traces GPX
 *	- write_notes: Modifier les notes
 *	- write_redactions: Caviarder les données cartographiques
 *	- openid: Se connecter avec OpenStreetMap
 *
 */

require_once( __DIR__.'/../vendor/autoload.php');

use JBelien\OAuth2\Client\Provider\OpenStreetMap ;
use  League\OAuth2\Client\Token\AccessToken ;

// To store data locally
define('COOKIE','osmoauth2');

/*

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
	'scopes' => ['read_prefs','read_gpx'] ,
];

// Retrieve data from cookie

if( isset($_COOKIE[constant('COOKIE')]))
{
	$data = unserialize($_COOKIE[constant('COOKIE')]);
}

// Some tools, like reseting stuff

if( isset($_REQUEST['action']) )
{
	switch( $_REQUEST['action'] )
	{
		case 'clear_auth':
			$data['accessCode'] = null ;
			$data['oauth2state'] = null ;
			$data['accessToken'] = null ;
			$data['refreshToken'] = null ;
			$data['scopes'] = ['read_prefs','read_gpx'];
			break ;
	}
}

// Update data from request

$data['app_id'] = isset($_REQUEST['app_id']) ? $_REQUEST['app_id'] : $data['app_id'];
$data['app_secret'] = isset($_REQUEST['app_secret']) ? $_REQUEST['app_secret'] : $data['app_secret'];
$data['accessCode'] = isset($_REQUEST['accessCode']) ? $_REQUEST['accessCode'] : $data['accessCode'];
$data['scopes'] = isset($_REQUEST['scopes']) ? $_REQUEST['scopes'] : $data['scopes'];

// Processing OAuth

$osmProvider = null ;

if( isset($data['app_id']))
{
	$osmProvider = new OpenStreetMap([
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
		$options = ['scope' => implode(' ',$data['scopes']) ];
		$data['authorizationUrl'] = $osmProvider->getAuthorizationUrl($options);
		$data['oauth2state'] = $osmProvider->getState();
	}

	if( isset($data['accessCode']) && (! isset($data['accessToken'])) )
	{
		/** @var \League\OAuth2\Client\Token\AccessToken $accessToken */
		$accessToken = $osmProvider->getAccessToken(
			'authorization_code', ['code' => $data['accessCode'] ]
		);
		//$data['accessToken'] = $accessToken->getToken();
		//$data['refreshToken'] = $accessToken->getRefreshToken();
		$data['accessToken'] = serialize($accessToken->jsonSerialize());
	}

}

// Save data back in cookie

setcookie(constant('COOKIE'), serialize($data), time() + 3600);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<!--
		The OAuth2 tool for Yapafo, to generate a access token.
  -->
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8"
					lang="en"></meta>
		<title>OSM OAuth2 access request</title>
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
		<h1>OSM OAuth2 access request</h1>

		<ol>

			<li>
				<h3>Application</h3>
				<p>At first we have to create an application ;
				<br/>
				Because OAuth2 need a redirect and we do not want to instanciate a https endpoint, you must set the "redirect url" to "urn:ietf:wg:oauth:2.0:oob" ;
				<br/>
				Create the app here:
				<a href="<?php echo $data['base_url'] ?>/oauth2/applications" target="osm_auth"><?php echo $data['base_url'] ?>/oauth2/applications</a> ;
				</p>
				<p>
				And copy the "client id" and "client secret" to paste them below :
				<form method="POST">
					<label for="app_id">Application ID:</label>
					<input id="app_id" name="app_id" type="text" value="<?php echo $data['app_id']; ?>" size="64" />
					<br/>
					<label for="app_secret">Application Secret:</label>
					<input id="app_secret" name="app_secret" type="text" value="<?php echo $data['app_secret']; ?>" size="64" />
					<br />
					Select scope(s):
					<ul style="list-style-type: none;">
						<li><input type="checkbox" name="scopes[]" value="read_prefs" <?php echo in_array('read_prefs',$data['scopes']) ? 'checked' : '' ?> /> read_prefs: Lire les préférences de l’utilisateur</li>
						<li><input type="checkbox" name="scopes[]" value="write_prefs" <?php echo in_array('write_prefs',$data['scopes']) ? 'checked' : '' ?> /> write_prefs: Modifier les préférences de l’utilisateur</li>
						<li><input type="checkbox" name="scopes[]" value="write_diary" <?php echo in_array('write_diary',$data['scopes']) ? 'checked' : '' ?> /> write_diary: Créer des entrées de journal, des commentaires et se faire des amis</li>
						<li><input type="checkbox" name="scopes[]" value="write_api" <?php echo in_array('write_api',$data['scopes']) ? 'checked' : '' ?> /> write_api: Modifier la carte</li>
						<li><input type="checkbox" name="scopes[]" value="read_gpx" <?php echo in_array('read_gpx',$data['scopes']) ? 'checked' : '' ?> /> read_gpx: Lire les traces GPX privées</li>
						<li><input type="checkbox" name="scopes[]" value="write_gpx" <?php echo in_array('write_gpx',$data['scopes']) ? 'checked' : '' ?> /> write_gpx: Mettre à jour les traces GPX</li>
						<li><input type="checkbox" name="scopes[]" value="write_notes" <?php echo in_array('write_notes',$data['scopes']) ? 'checked' : '' ?> /> write_notes: Modifier les notes</li>
						<li><input type="checkbox" name="scopes[]" value="write_redactions" <?php echo in_array('write_redactions',$data['scopes']) ? 'checked' : '' ?> /> write_redactions: Caviarder les données cartographiques</li>
						<li><input type="checkbox" name="scopes[]" value="openid" <?php echo in_array('openid',$data['scopes']) ? 'checked' : '' ?> /> openid: Se connecter à OpenStreetMap</li>
					</ul>
					<br/>
					<input type="submit" value="Ask authorization url" />
				</form>
				</p>
			</li>

			<?php if( isset($data['authorizationUrl']) ) { ?>
			<li>
				<h3>Authorization</h3>
				<p>Then we have to ask a "code" to obtain an "access token" ;
					<br/>
					If asked scope(s) does not match with application's scopes, a error message will be displayed (<em>in french: Le scope demandé n'est pas valide, est inconnu, ou est mal formé</em>) ;
					<br/>
					Then visit the authorization url: <a href="<?php echo $data['authorizationUrl']?>"  target="osm_auth"><?php echo $data['authorizationUrl']?></a> ;
				</p>
				And copy the displayed code to paste it below :
				<form method="POST">
					<label for="accessCode">Access Code:</label>
					<input id="accessCode" name="accessCode" type="text" value="<?php echo $data['accessCode']; ?>" size="64" />
					<br />
					<input type="submit" value="Ask access token" />
				</form>
				<form method="POST">
					<input type="hidden" name="action" value="clear_auth" />
					<input type="submit" value="Redo this step" />
				</form>
			</li>
			<?php } ?>

			<?php if( ! empty($data['accessToken']) ) { ?>
			<li>
				<h3>Access</h3>
				<?php
					/** @var \League\OAuth2\Client\Token\AccessToken $accessToken */
					$accessToken = new AccessToken(unserialize($data['accessToken']));
				?>
				<ul>
					<li>Access token : <?php echo $accessToken->getToken(); ?></li>
					<li>Refresh token : <?php echo $accessToken->getRefreshToken(); ?></li>
					<li>Expires time : <?php echo $accessToken->getExpires(); ?> <?php echo (date($accessToken->getExpires())); ?></li>
				</ul>
				<?php
					echo '<pre>', print_r($accessToken,true) ,'</pre>';
				?>
				<h3>Resource owner</h3>
				<?php
					$resourceOwner = $osmProvider->getResourceOwner($accessToken);
					echo '<pre>', print_r($resourceOwner,true) ,'</pre>';
				?>
			</li>
			<?php } ?>

		</ol>

	</body>
</html>
