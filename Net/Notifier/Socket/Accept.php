<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'Net/Notifier/Socket/Abstract.php';
require_once 'Net/Notifier/Socket/Server.php';

class Net_Notifier_Socket_Accept
    extends Net_Notifier_Socket_Abstract
{
    public function __construct(
        Net_Notifier_Socket_Server $serverSocket,
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

            throw new Net_Notifier_Socket_ConnectionException(
                "Unable to accept client connection. Error: {$error}",
                0,
                $errno
            );
        }
    }
}

?>
