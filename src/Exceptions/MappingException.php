<?php

namespace Andresilva\JsonDatamapping\Exceptions;

use Exception;

class MappingException extends Exception
{

    public function render($request)
    {
        return json_encode(['error' => true, 'message' => $this->getMessage()]);
    }
}
