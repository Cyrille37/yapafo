#!/usr/bin/env php
<?php
/**
 *
 * Credits & Thanks to:
 * - Grant Horwood for styling the tty output: https://dev.to/gbhorwood/writing-command-line-scripts-in-php-part-5-styling-output-text-1bcp
 */
error_reporting(-1);

/**
 * As of Composer 2.2, a new $_composer_autoload_path global variable
 * https://getcomposer.org/doc/articles/vendor-binaries.md#finding-the-composer-autoloader-from-a-binary
 */
require_once( $_composer_autoload_path ?? __DIR__ . '/../vendor/autoload.php' );

use Cyrille37\OSM\Yapafo\OSM_Api;
use Cyrille37\OSM\Yapafo\Tools\Ansi;
use JBelien\OAuth2\Client\Provider\OpenStreetMap as OAuth2OsmProvider;
use  League\OAuth2\Client\Token\AccessToken;

define('EOL', Ansi::EOL);
define('TAB', Ansi::TAB);

$console = new OAuthConsole();
$console->hello();
if (!$console->select_osm_instance()) {
	echo 'Abandon.', EOL;
	exit;
}
if (!$console->select_app_scopes()) {
	echo 'Abandon.', EOL;
	exit;
}
if (!$console->get_app_creds()) {
	echo 'Abandon.', EOL;
	exit;
}
if (!$console->get_auth_code()) {
	echo 'Abandon.', EOL;
	exit;
}
$console->get_access_token();


class OAuthConsole
{
	protected $base_url;
	protected $osmOAuth2Provider;
	protected $scopes;
	protected $app_id;
	protected $app_secret;
	protected $authorizationUrl;
	protected $oauth2state;
	protected $accessCode;

	const URL_UK_PROD = 'https://www.openstreetmap.org' ;
    const URL_UK_DEV = 'https://master.apis.dev.openstreetmap.org' ;

	public function hello()
	{
		echo EOL, TAB, Ansi::BOLD, '*** OSM Access Token creation ***', Ansi::CLOSE, EOL, EOL;
		echo 'We’re going to create an ', Ansi::BOLD, 'Access Token', Ansi::CLOSE, ' to give you a special user access on the ', Ansi::BOLD, 'OpenStreetMap', Ansi::CLOSE, ' database.', EOL;
		echo 'Use ', Ansi::BOLD, 'Ctrl+C', Ansi::CLOSE, ' will stop this command.', EOL;
	}

	public function select_osm_instance()
	{
		echo EOL, 'What OSM instance do you need ?', EOL;
		echo TAB, Ansi::BOLD, '1. OSM Production', Ansi::CLOSE, ' (', self::URL_UK_PROD.')', EOL;
		echo TAB, Ansi::BOLD, '2. OSM Developpement', Ansi::CLOSE, ' (', self::URL_UK_DEV.')', EOL;

		$done = false;
		while (true) {
			$choice = readline('Your choice: ');
			switch (trim($choice)) {
				case '1':
					$this->base_url = self::URL_UK_PROD;
					$done = true;
					break 2;
				case '2':
					$this->base_url = self::URL_UK_DEV;
					$done = true;
					break 2;
			}
		}
		return $done;
	}

	public function select_app_scopes()
	{
		echo EOL, 'What permissions (scopes) do you need ? ', EOL;
		echo 'If the application already exists, be sure to select at least less or same permissions as the application`s scopes.', EOL;
		foreach (OSM_Api::SCOPES4HUMANS as $c => $scope) {
			echo TAB, Ansi::BOLD, $c, '. ', $scope['value'], Ansi::CLOSE, ' - ', $scope['desc'], ';', EOL;
		}
		echo TAB, '0. when done to close the list.', EOL;

		$scopes = ['read_prefs', 'write_prefs'];

		while (true) {
			echo Ansi::BOLD, 'Actual list', Ansi::CLOSE, ': ', implode(', ', $scopes), EOL;
			$choice = trim(readline('Your choice: '));
			if ($choice == '0') {
				if (empty($scopes)) {
					echo 'Abort, empty permissions set', EOL;
					return false;
				}
				$this->scopes = $scopes;
				return true;
			} else if (isset(OSM_Api::SCOPES4HUMANS[$choice])) {
				$v = OSM_Api::SCOPES4HUMANS[$choice]['value'];
				$k = array_search($v, $scopes);
				if ($k !== false)
					unset($scopes[$k]);
				else
					$scopes[] = $v;
			}
		}
		return false;
	}

	public function get_app_creds()
	{
		echo EOL, 'We need application credentials ', Ansi::BOLD, 'app_id', Ansi::CLOSE, ' and ', Ansi::BOLD, 'app_secret', Ansi::CLOSE, '.', EOL;
		echo 'If you don\'t have them, create a new application at url ', $this->base_url, '/oauth2/applications', EOL;
		echo 'and be sure to select at least the permissions you have previously chosen.', EOL;

		// readline does not manage ANSI codes

		$app_id = trim(readline('Application ID: '));
		if (empty($app_id))
			return false;
		$this->app_id = $app_id;

		$app_secret = trim(readline('Application Secret: '));
		if (empty($app_secret))
			return false;
		$this->app_secret = $app_secret;

		$this->osmOAuth2Provider = new OAuth2OsmProvider([
			'clientId'     => $this->app_id,
			'clientSecret' => $this->app_secret,
			'redirectUri'  => OSM_Api::OAUTH2_NO_REDIRECT_URL,
			'osm_base_url' => $this->base_url,
		]);

		return true;
	}

	public function get_auth_code()
	{
		$options = ['scope' => implode(' ', $this->scopes)];
		$this->authorizationUrl = $this->osmOAuth2Provider->getAuthorizationUrl($options);
		$this->oauth2state = $this->osmOAuth2Provider->getState();

		echo EOL, 'Now you have to visit url ', $this->authorizationUrl, ' to agree permissions and get the ', Ansi::BOLD, 'Code', Ansi::CLOSE, ' mandatory to request an Access Token.', EOL;
		$accessCode = trim(readline('The Code: '));
		if (empty($accessCode))
			return false;

		$this->accessCode = $accessCode;

		return true;
	}

	public function get_access_token()
	{
		/** @var \League\OAuth2\Client\Token\AccessToken $accessToken */
		$accessToken = $this->osmOAuth2Provider->getAccessToken(
			'authorization_code',
			['code' => $this->accessCode]
		);

		echo EOL, 'Here is the Access Token: ', Ansi::BOLD, $accessToken->getToken(), Ansi::CLOSE, "\n";

		try
		{
			// Don't fail if could not access "/api/0.6/user/details.json"
			// like "api06.dev.openstreetmap.org" is access authentification for this endpoint
			$resourceOwner = $this->osmOAuth2Provider->getResourceOwner($accessToken);
			echo 'which authorize access as user ', Ansi::BOLD, $resourceOwner->getDisplayName(), Ansi::CLOSE, "\n";
			echo 'with permission to ', implode(', ', $this->scopes), EOL;
		}
		catch( \Exception $ex )
		{
			echo 'The instance do not permit to retrieve user details.',EOL;
		}

		return true;
	}
}
