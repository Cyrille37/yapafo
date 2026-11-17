<?php
declare(strict_types=1);

namespace Cyrille37\OSM\Yapafo\Exceptions;

use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception;
use Throwable;
use Override;

class SortException extends OSM_Exception
{

  const ERR_MISSING_WAYS = 1;
  const ERR_DUPLICATE_WAYS = 2;
  const ERRORS = [self::ERR_MISSING_WAYS, self::ERR_DUPLICATE_WAYS];

  #[Override]
  public function __construct(string $message)
  {
    return parent::__construct($message);
  }
}
