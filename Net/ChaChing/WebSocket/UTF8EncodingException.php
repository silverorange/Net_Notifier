<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'Net/ChaChing/Exception.php';

class Net_ChaChing_WebSocket_UTF8EncodingException
    extends Exception
    implements Net_ChaChing_Exception
{
    protected $binaryData = '';

    public function __construct($message, $code = 0, $binaryData = '')
    {
        parent::__construct($message, $code);
        $this->binaryData = $binaryData;
    }

    public function getBinaryData()
    {
        return $this->binaryData;
    }
}

?>
