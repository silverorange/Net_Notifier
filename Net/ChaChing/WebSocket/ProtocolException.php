<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'Net/ChaChing/Exception.php';

class Net_ChaChing_WebSocket_ProtocolException
    extends Exception
    implements Net_ChaChing_Exception
{

    protected $supportedProtocols = array();

    protected $requestedProtocols = array();

    public function __construct(
        $message,
        $code = 0,
        array $supportedProtocols = array(),
        array $requestedProtocols = array()
    ) {
        parent::__construct($message, $code);
        $this->supportedProtocols = $supportedProtocols;
        $this->requestedProtocols = $requestedProtocols;
    }

    public function getSupportedProtocols()
    {
        return $this->supportedProtocols;
    }

    public function getRequestedProtocols()
    {
        return $this->getRequestedProtocols;
    }
}

?>
