<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * WebSocket basic notification listener class
 *
 * PHP version 5
 *
 * LICENSE:
 *
 * This library is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation; either version 2.1 of the
 * License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2012-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      https://github.com/silverorange/Net_Notifier
 */

/**
 * Client class.
 */
require_once 'Net/Notifier/Client.php';

/**
 * Logger class for logging messages and debug output for this client.
 */
require_once 'Net/Notifier/Logger.php';

/**
 * Loggable interface.
 */
require_once 'Net/Notifier/Loggable.php';

/**
 * A simple notification listener client
 *
 * May be extended to provide additional functionality.
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      https://github.com/silverorange/Net_Notifier
 */
class Net_Notifier_Listener
    extends Net_Notifier_Client
    implements Net_Notifier_Loggable
{
    // {{{ protected properties

    /**
     * The logger used by this client
     *
     * @var Net_Notifier_Logger
     */
    protected $logger = null;

    // }}}
    // {{{ setLogger()

    /**
     * Sets the logger for this client
     *
     * Loggers receive status messages and debug output and can store or
     * display received messages.
     *
     * @param Net_Notifier_Logger|null $logger the logger to set for this
     *                                         server, or null to unset the
     *                                         logger.
     *
     * @return Net_Notifier_Loggable the current object, for fluent interface.
     */
    public function setLogger(Net_Notifier_Logger $logger = null)
    {
        $this->logger = $logger;
        return $this;
    }

    // }}}
    // {{{ run()

    /**
     * Runs this listen client
     *
     * This client listens for messages relayed from the server.
     *
     * @return void
     */
    public function run()
    {
        $this->connect();

        // tell server we want to listen
        $this->connection->writeText('{ "action" : "listen" }');

        while (true) {

            $read = array($this->connection->getSocket()->getRawSocket());

            $result = stream_select(
                $read,
                $write = null,
                $except = null,
                null
            );

            if ($result < 1) {
                continue;
            }

            $moribund = false;

            // check if server closed connection
            $bytes = $this->connection->getSocket()->peek(1);

            if (mb_strlen($bytes, '8bit') === 0) {
                $this->log(
                    'server closed connection.' . PHP_EOL,
                    Net_Notifier_Logger::VERBOSITY_CLIENT
                );

                $moribund = true;
            }

            try {

                if ($this->connection->read(Net_Notifier_Client::READ_BUFFER_LENGTH)) {

                    if ($this->connection->getState() < Net_Notifier_WebSocket_Connection::STATE_CLOSING) {

                        $messages = $this->connection->getTextMessages();
                        foreach ($messages as $message) {
                            if (mb_strlen($message, '8bit') > 0) {
                                $this->log(
                                    sprintf(
                                        'received message: "%s"' . PHP_EOL,
                                        $message
                                    ),
                                    Net_Notifier_Logger::VERBOSITY_MESSAGES
                                );
                                $this->handleMessage($message);
                            }
                        }
                    }

                } else {

                    $this->log(
                        'got a message chunk from server' . PHP_EOL,
                        Net_Notifier_Logger::VERBOSITY_CLIENT
                    );

                }

            } catch (Net_Notifier_WebSocket_HandshakeFailureException $e) {
                $this->log(
                    sprintf(
                        'failed server handshake: %s' . PHP_EOL,
                        $e->getMessage()
                    ),
                    Net_Notifier_Logger::VERBOSITY_CLIENT
                );
            }

            if ($moribund) {
                $this->connection->close();
                break;
            }

        }

        if ($this->connection->getState() < Net_Notifier_WebSocket_Connection::STATE_CLOSING) {
            $this->disconnect();
        }
    }

    // }}}
    // {{{ handleMessage()

    /**
     * Handles messages received by this listener
     *
     * Subclasses can and should override this method to handle messages.
     *
     * @param string $message the received message
     *
     * @return void
     */
    protected function handleMessage($message)
    {
    }

    // }}}
    // {{{ log()

    /**
     * Logs a message with the specified priority
     *
     * @param string  $message   the message to log.
     * @param integer $priority  an optional verbosity level to display at. By
     *                           default, this is
     *                           {@link Net_Notifier_Logger::VERBOSITY_MESSAGES}.
     * @param boolean $timestamp optional. Whether or not to include a
     *                           timestamp with the logged message. If not
     *                           specified, a timetamp is included.
     *
     * @return Net_Notifier_Server the current object, for fluent interface.
     */
    protected function log(
        $message,
        $priority = Net_Notifier_Logger::VERBOSITY_MESSAGES,
        $timestamp = true
    ) {
        if ($this->logger instanceof Net_Notifier_Logger) {
            $this->logger->log($message, $priority, $timestamp);
        }

        return $this;
    }

    // }}}
}

?>
