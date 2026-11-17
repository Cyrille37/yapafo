<?php
declare(strict_types=1);

namespace Cyrille37\OSM\Yapafo\Exceptions;

use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception;

class SortException extends OSM_Exception
{

  const ERR_MISSING_WAYS = 1;
  const ERR_DUPLICATE_WAYS = 2;
  const ERRORS = [self::ERR_MISSING_WAYS, self::ERR_DUPLICATE_WAYS];

  public function __construct(string $message, int $code = 0)
  {
    parent::__construct($message, $code);
  }
}
