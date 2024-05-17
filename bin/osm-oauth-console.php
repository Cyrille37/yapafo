#!/usr/bin/env php
<?php
/**
 *
 * Credits & Thanks to:
 * - Grant Horwood for styling the tty output: https://dev.to/gbhorwood/writing-command-line-scripts-in-php-part-5-styling-output-text-1bcp
 */
error_reporting(-1);

require_once(__DIR__ . '/../vendor/autoload.php');

use Cyrille37\OSM\Yapafo\OSM_Api;
use JBelien\OAuth2\Client\Provider\OpenStreetMap as OAuth2OsmProvider;
use  League\OAuth2\Client\Token\AccessToken;

define('EOL', "\n");
define('TAB', "\t");

/**
 * Escape character
 */
define('ESC', "\033");

/**
 * ANSI colours
 */
define('ANSI_BLACK', ESC . "[30m");
define('ANSI_RED', ESC . "[31m");
define('ANSI_GREEN', ESC . "[32m");
define('ANSI_YELLOW', ESC . "[33m");
define('ANSI_BLUE', ESC . "[34m");
define('ANSI_MAGENTA', ESC . "[35m");
define('ANSI_CYAN', ESC . "[36m");
define('ANSI_WHITE', ESC . "[37m");

/**
 * ANSI background colours
 */
define('ANSI_BACKGROUND_BLACK', ESC . "[40m");
define('ANSI_BACKGROUND_RED', ESC . "[41m");
define('ANSI_BACKGROUND_GREEN', ESC . "[42m");
define('ANSI_BACKGROUND_YELLOW', ESC . "[43m");
define('ANSI_BACKGROUND_BLUE', ESC . "[44m");
define('ANSI_BACKGROUND_MAGENTA', ESC . "[45m");
define('ANSI_BACKGROUND_CYAN', ESC . "[46m");
define('ANSI_BACKGROUND_WHITE', ESC . "[47m");

/**
 * ANSI styles
 */
define('ANSI_BOLD', ESC . "[1m");
define('ANSI_ITALIC', ESC . "[3m"); // limited support.
define('ANSI_UNDERLINE', ESC . "[4m");
define('ANSI_STRIKETHROUGH', ESC . "[9m");

/**
 * Clear all ANSI styling
 */
define('ANSI_CLOSE', ESC . "[0m");

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

	public function hello()
	{
		echo EOL, TAB, ANSI_BOLD, '*** OSM Access Token creation ***', ANSI_CLOSE, EOL, EOL;
		echo 'We’re going to create an ', ANSI_BOLD, 'Access Token', ANSI_CLOSE, ' to give you a special user access on the ', ANSI_BOLD, 'OpenStreetMap', ANSI_CLOSE, ' database.', EOL;
		echo 'Use ', ANSI_BOLD, 'Ctrl+C', ANSI_CLOSE, ' will stop this command.', EOL;
	}

	public function select_osm_instance()
	{
		echo EOL, 'What OSM instance do you need ?', EOL;
		echo TAB, ANSI_BOLD, '1. OSM Production', ANSI_CLOSE, ' (', OSM_Api::URL_PROD_UK, EOL;
		echo TAB, ANSI_BOLD, '2. OSM Developpement', ANSI_CLOSE, ' (', OSM_Api::URL_DEV_UK, EOL;

		$done = false;
		while (true) {
			$choice = readline('Your choice: ');
			switch (trim($choice)) {
				case '1':
					$this->base_url = OSM_Api::URL_PROD_UK;
					$done = true;
					break 2;
				case '2':
					$this->base_url = OSM_Api::URL_DEV_UK;
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
			echo TAB, ANSI_BOLD, $c, '. ', $scope['value'], ANSI_CLOSE, ' - ', $scope['desc'], ';', EOL;
		}
		echo TAB, '0. when done to close the list.', EOL;

		$scopes = ['read_prefs', 'write_prefs'];

		while (true) {
			echo ANSI_BOLD, 'Actual list', ANSI_CLOSE, ': ', implode(', ', $scopes), EOL;
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
		echo EOL, 'We need application credentials ', ANSI_BOLD, 'app_id', ANSI_CLOSE, ' and ', ANSI_BOLD, 'app_secret', ANSI_CLOSE, '.', EOL;
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

		echo EOL, 'Now you have to visit url ', $this->authorizationUrl, ' to agree permissions and get the ', ANSI_BOLD, 'Code', ANSI_CLOSE, ' mandatory to request an Access Token.', EOL;
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
		$resourceOwner = $this->osmOAuth2Provider->getResourceOwner($accessToken);

		echo EOL, 'Here is the Access Token: ', ANSI_BOLD, $accessToken->getToken(), ANSI_CLOSE, "\n";
		echo 'which authorize access as user ', ANSI_BOLD, $resourceOwner->getDisplayName(), ANSI_CLOSE, "\n";
		echo 'with permission to ', implode(', ', $this->scopes), EOL;

		return true;
	}
}
