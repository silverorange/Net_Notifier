<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'Net/ChaChing/WebSocket/AbstractHandshake.php';

class Net_ChaChing_WebSocket_ClientHandshake
    extends Net_ChaChing_WebSocket_AbstractHandshake
{
    protected $host = 'localhost';

    protected $port = 3000;

    protected $resource = '/';

    protected $nonce = '';

    public function __construct(
        $host,
        $port,
        $resource,
        $nonce,
        array $protocols = array())
    {
        $this->setHost($host);
        $this->setPort($port);
        $this->setNonce($nonce);
        $this->setProtocols($protocols);
    }

    public function setHost($host)
    {
        $this->host = (string)$host;
        return $this;
    }

    public function setPort($port)
    {
        $this->port = (integer)$port;
        return $this;
    }

    public function setResource($resource)
    {
        $this->resource = (string)$resource;
        return $this;
    }

    public function setNonce($nonce)
    {
        $this->nonce = (string)$nonce;
        return $this;
    }

    public function handshake()
    {
        $version = Net_ChaChing_WebSocket_AbstractHandshake::VERSION;

        $request =
              "GET " . $this->resource . " HTTP/1.1\r\n"
            . "Host: " . $this->host . "\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Key: " . $this->nonce . "\r\n"
            . "Sec-WebSocket-Version: " . $version . "\r\n";

        if (count($this->protocols) > 0) {
            $protocols = implode(',', $this->protocols);
            $request .= "Sec-WebSocket-Protocol: " . $protocols . "\r\n";
        }

        $request .= "\r\n\r\n";

        return $request;
    }

/*


wait for response
parse response
check response


*/

    public function isAccepted($accept)
    {
    }
}

?>
