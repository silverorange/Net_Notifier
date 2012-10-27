<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'Net/ChaChing/WebSocket/SocketAbstract.php';

class Net_ChaChing_WebSocket_SocketAccept
    extends Net_ChaChing_WebSocket_SocketAbstract
{
    public function __construct(
        Net_ChaChing_WebSocket_SocketServer $serverSocket,
        $timeout
    ) {
        set_error_handler(array($this, 'connectionWarningsHandler'));

        $this->socket = stream_socket_accept(
            $serverSocket->getRawSocket(),
            $timeout
        );

        restore_error_handler();

        if (!$this->socket) {
            $error = ($errstr == '')
                ? implode("\n", $this->connectionWarnings)
                : $errstr;

            throw new Net_ChaChing_WebSocket_ConnectionException(
                "Unable to accept client connection. Error: {$error}",
                0,
                $errno
            );
        }
    }
}

?>
