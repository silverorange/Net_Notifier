<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Client class.
 */
require_once 'Net/Notifier/Client.php';

/**
 * A client for sending notifications
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Net_Notifier_Sender extends Net_Notifier_Client
{
    // {{{ send()

    /**
     * Connects to the WebSocket server, sends a text message and disconnects
     *
     * @param string $message the UTF-8 text message to send.
     *
     * @return Net_Notifier_Sender the current object, for fluent interface.
     *
     * @throws Net_Notifier_ClientException if there is an error connecting
     *         to the notification server or sending the message.
     */
    public function send($message)
    {
        $this->connect();
        $this->connection->writeText($message);
        $this->disconnect();

        return $this;
    }

    // }}}
}

?>
