# Yet Another Php Api For OpenStreetMap

**yapafo** is a library which permits to authenticate, read and write objects in the OpenStreetMap database.

## FEATURES

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

## TUTORIALS / EXAMPLES

- A web page to create OAuth Access Token
  - launch a browser at  `examples-web/OAuthRequestAccess.php`
  - like with `php -S localhost:8000` 
- Other command-line examples in `examples-console`

### examples-web

- the tool examples/OAuthRequestAccess.php is an easy way to get OAuth Access Token (and understand the protocol phases ;-)
- Overpass-API requests are shown in examples/OApiQuery.php and ApiOApiQuery.php

## BUG and Request

- https://github.com/Cyrille37/yapafo/issues

## References

- The OAuth 1.0 Protocol : http://tools.ietf.org/html/rfc5849
- OSM OAuth doc : http://wiki.openstreetmap.org/wiki/OAuth
- [PSR-3: Logger Interface](https://www.php-fig.org/psr/psr-3/)

