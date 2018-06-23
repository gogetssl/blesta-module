<?php

namespace MgGoGetSsl\Exception;

class GoGetSSLApiException extends \Exception
{

    /**
     * GoGetSSLApiException constructor.
     *
     * @param string          $message
     * @param int|null        $code
     * @param \Exception|null $previous
     */
    public function __construct($message, $code = null, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}
