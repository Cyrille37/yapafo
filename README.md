# Yet Another Php Api For OpenStreetMap

**yapafo** is a library which permits to authenticate, read and write objects in the OpenStreetMap database.

## FEATURES

- use OAuth 2.0
- Read objects from API, XAPI and Overpass-API.
- Write objects to API.
- Authenticate with Basic or OAuth.
- The class instance and its osm objects are serializable.
- A bit of geometry stuff (is node inside/outside polygon)
- ...

## REQUIREMENTS

Requirements are covered in `composer.json`.

Php extensions:

- [SimpleXml](https://www.php.net/manual/en/book.simplexml.php)

Php libraries:

- [psr/log](https://github.com/php-fig/log)
- [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv)
- [jbelien/oauth2-openstreetmap](https://github.com/jbelien/oauth2-openstreetmap)
  - which is a provider for [league/oauth2-client](https://github.com/thephpleague/oauth2-client)

## TUTORIALS / EXAMPLES

- install dependencies
  - `composer install`
- create file `.env` and set
  - osm_api_url = https://master.apis.dev.openstreetmap.org/api/0.6
  - oauth_url = https://master.apis.dev.openstreetmap.org
  - simulation=false
  - read `.env.example` to know more about configuration

- Use the provided web page to create an OAuth Access Token
  - change directory `cd examples-web`
  - launch the php webserver `php -S localhost:8000`
  - then open a browser at  `http://localhost:8000/OAuthRequestAccess.php`

- Explore command-line examples in `examples-console`

### examples-web

- the tool examples/OAuthRequestAccess.php is an easy way to get OAuth Access Token (and understand the protocol phases ;-)
- Overpass-API requests are shown in examples/OApiQuery.php and ApiOApiQuery.php

## BUG and Request

- https://github.com/Cyrille37/yapafo/issues

## References

- https://wiki.openstreetmap.org/wiki/OAuth

- The OAuth 1.0 Protocol : http://tools.ietf.org/html/rfc5849
- OSM OAuth doc : http://wiki.openstreetmap.org/wiki/OAuth
- [PSR-3: Logger Interface](https://www.php-fig.org/psr/psr-3/)

