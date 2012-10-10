<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'Net/ChaChing/WebSocket/Connection.php';
require_once 'Net/ChaChing/WebSocket/ClientException.php';
require_once 'Net/ChaChing/WebSocket/UTF8EncodingException.php';

class Net_ChaChing_WebSocket_Client
{
    protected $port = 3000;

    protected $host = 'localhost';

    protected $resource = '/';

    protected $protocols = array();

    protected $timeout = 100;

    /**
     * @var Net_ChaChing_WebSocket_Connection
     */
    protected $connection = null;

    protected $oldErrorHandler = false;

    public function __construct(
        $address,
        array $protocols = array(),
        $timeout = 1
    ) {
        $this->parseAddress($address);
        $this->setProtocols($protocols);
        $this->setTimeout($timeout);
    }

    public function parseAddress($address)
    {
        $exp = '!^ws://([\w-.]+?)(?::(\d+))?(/.*)?$!';
        $matches = array();
        if (!preg_match($exp, $address, $matches)) {
            throw new Net_ChaChing_WebSocket_ClientException(
                sprintf(
                      'Invalid WebSocket address: %s. Should be in the form '
                    . 'ws://host[:port][/resource]',
                    $address
                )
            );
        }

        if (isset($matches[1])) {
            $this->setHost($matches[1]);
        }

        if (isset($matches[2])) {
            $this->setPort($matches[2]);
        }

        if (isset($matches[3])) {
            $this->setResource($matches[3]);
        }
    }

    public function __destruct()
    {
        if ($this->connection instanceof Net_ChaChing_WebSocket_Connection) {
            $this->disconnect();
        }
    }

    public function sendText($message)
    {
        $this->setErrorHandler();
        $this->connect();
        $this->connection->writeText($message);
        $this->disconnect();
        $this->restoreErrorHandler();
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

    public function setProtocols(array $protocols)
    {
        $this->protocols = $protocols;
        return $this;
    }

    public function setTimeout($timeout)
    {
        $this->timeout = (integer)$timeout;
        return $this;
    }

    public function handleError($errno, $errstr, $errfile, $errline)
    {
        if ($errno === E_USER_ERROR) {
            if (   $this->oldErrorHandler !== false
                && $this->oldErrorHandler !== null
            ) {
                call_user_func(
                    $this->oldErrorHandler,
                    $errno,
                    $errstr,
                    $errfile,
                    $errline
                );
            } else {
                exit(1);
            }
        }

        return false;
    }

    protected function connect()
    {
        $errorno = 0;
        $errstr  = '';

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            throw new Net_ChaChing_WebSocket_Client_Exception(
                sprintf(
                    'Unable to create client TCP socket: %s',
                    socket_strerror(socket_last_error())
                )
            );
        }

        $result = socket_set_option(
            $socket,
            SOL_SOCKET,
            SO_RCVTIMEO,
            array('sec' => $this->timeout, 'usec' => 0)
        );

        if (!$result) {
            throw new Net_ChaChing_WebSocket_Client_Exception(
                sprintf(
                    'Unable to set socket timeout: %s',
                    socket_strerror(socket_last_error())
                )
            );
        }

        $result = socket_connect(
            $socket,
            $this->host,
            $this->port
        );

        if (!$result) {
            throw new Net_ChaChing_WebSocket_Client_Exception(
                sprintf(
                    'Unable to connect client TCP socket: %s',
                    socket_strerror(socket_last_error())
                )
            );
        }

        $this->connection = new Net_ChaChing_WebSocket_Connection($socket);

        $this->connection->startHandshake(
            $this->host,
            $this->port,
            $this->resource,
            $this->protocols
        );

        $this->connection->read(2048);
    }

    protected function disconnect()
    {
/*        $this->connection->startClose(
            Net_ChaChing_WebSocket_Connection::CLOSE_SHUTDOWN,
            'Client sent message.'
        );
*/
        $this->connection->read(2048);
//:        $this->connection->shutdown();
        $this->connection = null;
    }

    protected function setErrorHandler()
    {
        if ($this->oldErrorHandler !== false) {
            $this->restoreErrorHandler();
        }

        $this->oldErrorHandler = set_error_handler(
            array($this, 'handleError')
        );

        return $this;
    }

    protected function restoreErrorHandler()
    {
        if ($this->oldErrorHandler !== false) {
            restore_error_handler();
            $this->oldErrorHandler = false;
        }

        return $this;
    }
}

?>
