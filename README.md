# yapafo - Yet Another Php Api For Ospenstreetmap

This library permits to read and write objects in the OpenStreetMap database.

## FEATURES

- Read objects from API and Overpass-API.
- Write objects to API.
- Authenticate with Basic or OAuth.
- The class instance and its osm objects is serializable.
- A bit of geometry stuff (id node inside/outside polygon)
- ...

## REQUIREMENTS

Php extensions:
- [SimpleXml](https://www.php.net/manual/en/book.simplexml.php)

## TUTORIALS / EXAMPLES

- look at tests/test_OSM_Api_with-network-write.php to see how to Read data, Authenticate and Save changes.

### examples-web

- the tool examples/OAuthRequestAccess.php is an easy way to get OAuth Access Token (and understand the protocol phases ;-)
- Overpass-API requests are shown in examples/OApiQuery.php and ApiOApiQuery.php

## BUG and Request

- https://github.com/Cyrille37/yapafo/issues

## References

- The OAuth 1.0 Protocol : http://tools.ietf.org/html/rfc5849
- OSM OAuth doc : http://wiki.openstreetmap.org/wiki/OAuth

- [PSR-3: Logger Interface](https://www.php-fig.org/psr/psr-3/)
- [PSR-11: Container interface](https://www.php-fig.org/psr/psr-11/)
