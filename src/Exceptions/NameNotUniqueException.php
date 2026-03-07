<?php

namespace IODigital\ABlockLaravel\Exceptions;

use Illuminate\Http\Response;
use Exception;

class NameNotUniqueException extends Exception
{
    protected $code = Response::HTTP_BAD_REQUEST;
    protected $message = 'Name is not unique for this entity for this owner';
}
