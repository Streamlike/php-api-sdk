<?php

namespace Streamlike\Api\Exception;

use Exception as BaseException;

class InvalidInputException extends Exception
{
    /**
     * @var array
     */
    private $errors;

    public function __construct($message, array $errors = [], BaseException $previous = null)
    {
        parent::__construct($message, 400, $previous);

        $this->errors = $errors;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
