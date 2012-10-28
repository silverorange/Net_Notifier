<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'Net/Notifier/Socket/Abstract.php';

class Net_Notifier_Socket_Server
    extends Net_Notifier_Socket_Abstract
{
    public function __construct($address, $timeout, array $sslOptions = array())
    {
        $context = stream_context_create();
        foreach ($sslOptions as $name => $value) {
            if (!stream_context_set_option($context, 'ssl', $name, $value)) {
                throw new Net_Notifier_Socket_ConnectionException(
                    "Error setting SSL context option '{$name}'"
                );
            }
        }

        set_error_handler(array($this, 'connectionWarningsHandler'));

        $this->socket = stream_socket_server(
            $address,
            $errstr,
            $errno,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );

        restore_error_handler();

        if (!$this->socket) {
            $error = ($errstr == '')
                ? implode("\n", $this->connectionWarnings)
                : $errstr;

            throw new Net_Notifier_Socket_ConnectionException(
                "Unable to start server {$address}. Error: {$error}",
                0,
                $errno
            );
        }
    }
}

?>
