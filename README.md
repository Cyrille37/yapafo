# Yet Another Php Api For OpenStreetMap

**yapafo** is a library which permits to authenticate, read and write objects in the OpenStreetMap database.

## FEATURES

- Read (query) objects from **API**, **XAPI** and **Overpass**.
- Write (create, update, delete) objects on **API**.
- Authenticate with Basic or **OAuth 2**.
- Class for osm objects are **serializable**.
- A bit of **geometry** stuff are available locally (on query result)
  - is node inside/outside polygon
  - get gravity center for relation or way
- ...

## TUTORIALS / EXAMPLES

- install dependencies
  - `composer install`

By default:
  - API operations are done on Osm developement instance at `master.apis.dev.openstreetmap.org` ;
    - Write operations are carried out in "simulation" mode, so that no queries are made.
  - Overpass queries are done on `overpass-api.de` ;
  - XAPI  queries are done on `overpass-api.de`.

To overide those default behaviors, create file `.env` and set
  - `simulation=false` to really write on API
  - `osm_api_url = https://api.openstreetmap.org/api/0.6` to query and update on production OSM instance

Read `.env.example` to know more about configuration.

### Generate an Access Token

The command-line `oauth/oauth-console.php` will drive you on getting an OAuth2 Access Token, launch it and read the instructions.
the command consists of several stages:
1. choose the OSM server instance
2. choose the permissions you need
3. enter Application ID and Secret, with help to create one if needed
4. get an authorization code
5. finally, get the precious Access Token you need to access some services as an authenticated user.

To facilitate the procedure for obtaining the token, a Web page is **currently under construction** at `oatuh/OAuthRequestAccess.php`

### examples-console

Look at the code to learn more abour Yapafo.

### examples-web

Work in progress

## BUG and Request

- https://github.com/Cyrille37/yapafo/issues

## REQUIREMENTS

Requirements are covered by `composer.json`.

Php >=7.4 et <8.3.

Php extensions:

- [SimpleXml](https://www.php.net/manual/en/book.simplexml.php)

Php libraries:

- [psr/log](https://github.com/php-fig/log)
- [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv)
- [jbelien/oauth2-openstreetmap](https://github.com/jbelien/oauth2-openstreetmap)
  - which is a provider for [league/oauth2-client](https://github.com/thephpleague/oauth2-client)

## References

- The OAuth 2 Protocol : https://oauth.net/2/ and https://datatracker.ietf.org/doc/html/rfc6749
- OSM OAuth doc : http://wiki.openstreetmap.org/wiki/OAuth
- [PSR-3: Logger Interface](https://www.php-fig.org/psr/psr-3/)
