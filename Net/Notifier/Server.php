<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * WebSocket notification server class
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
 * @copyright 2006-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      https://github.com/silverorange/Net_Notifier
 */

/**
 * A server process for receiving and relaying notifications
 *
 * The cha-ching server interacts with two types of clients. The first type
 * of client connects to the server, sends a message and disconnects. The
 * second type of client never sends data and remains connected to the server.
 * When a client of type one sends a message to the server, the server relays
 * the message to all connected clients of type two.
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2006-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      https://github.com/silverorange/Net_Notifier
 */
class Net_Notifier_Server implements Net_Notifier_Loggable
{
    // {{{ class constants

    /**
     * How long the read buffer for client connections is.
     *
     * Note: For correct behaviour, this must be the same at the PHP stream
     * chunk size. For all PHP < 5.4 this is 8192. Other values will cause
     * PHP's internal stream buffer to be used and break stream_select()
     * semantics. See https://bugs.php.net/bug.php?id=52602
     */
    const READ_BUFFER_LENGTH = 8192;

    /**
     * How long the write buffer for client connections is.
     *
     * Note: For correct behaviour, this must be the same at the PHP stream
     * chunk size. For all PHP < 5.4 this is 8192. Other values will cause
     * PHP's internal stream buffer to be used and break stream_select()
     * semantics. See https://bugs.php.net/bug.php?id=52602
     */
    const WRITE_BUFFER_LENGTH = 8192;

    // }}}
    // {{{ protected properties

    /**
     * The socket at which this server accepts connections
     *
     * @var Net_Notifier_Socket_Server
     */
    protected $socket = null;

    /**
     * The port this server runs on
     *
     * By default this is 2000. See {@link Net_Notifier_Server::__construct()}
     * to use a different port.
     *
     * @var integer
     */
    protected $port = 2000;

    /**
     * Clients connected to this server
     *
     * This is an array of {@link Net_Notifier_WebSocket_Connection} objects.
     *
     * @var array
     */
    protected $clients = array();

    /**
     * Listening clients connected to this server
     *
     * This is an array of {@link Net_Notifier_WebSocket_Connection} objects.
     * These clients are relayed messages received by the server.
     *
     * @var array
     */
    protected $listenClients = array();

    /**
     * Whether or not this server is running
     *
     * @var boolean
     */
    protected $connected = false;

    /**
     * The logger used by this server
     *
     * @var Net_Notifier_Logger
     */
    protected $logger = null;

    /**
     * Flag to trigger clean shutdown of this server
     *
     * This flag is checked during each iteration of the main event loop.
     *
     * @var boolean
     */
    protected $moribund = false;

    // }}}
    // {{{ __construct()

    /**
     * Creates a new notification server
     *
     * @param integer $port optional. The port on which this server should
     *                      listen for incomming connections. If not specified,
     *                      port 2000 is used.
     *
     * @see Net_Notifier_Server::run()
     */
    public function __construct($port = 2000)
    {
        $this->port = (integer)$port;
    }

    // }}}
    // {{{ __destruct()

    /**
     * Tries to ensure that all sockets are closed when this server is no
     * longer referenced
     *
     * In reality, the destructor is never called.
     *
     * @return void
     */
    public function __destruct()
    {
        if ($this->connected) {
            $this->disconnect();
        }
    }

    // }}}
    // {{{ setLogger()

    /**
     * Sets the logger for this server
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
     * Runs this notification server
     *
     * This notification server receives client connections and receives and
     * relays data from connected clients.
     *
     * @return void
     */
    public function run()
    {
        $this->connect();

        while (!$this->moribund) {

            $read = $this->getReadArray();

            // Suppressing warnings for stream_select() because it will raise
            // a warning if interrupted by a signal.
            $result = @stream_select(
                $read,
                $write = null,
                $except = null,
                null
            );

            if ($result === false || $result < 1) {
                continue;
            }

            // check for new connections
            if (in_array($this->socket->getRawSocket(), $read)) {
                try {
                    $newSocket = new Net_Notifier_Socket_Accept(
                        $this->socket,
                        null
                    );
                } catch (Net_Notifier_Socket_Exception $e) {
                    $this->log(
                        'Accepting client connection failed: reason: '
                        . $e->getMessage() . PHP_EOL,
                        Net_Notifier_Logger::VERBOSITY_ERRORS
                    );
                }

                $client = new Net_Notifier_WebSocket_Connection($newSocket);
                $this->clients[] = $client;
                $this->log(
                    'client connected from ' . $client->getIPAddress()
                    . PHP_EOL,
                    Net_Notifier_Logger::VERBOSITY_CLIENT
                );
            }

            // check for client data
            foreach ($this->getReadClients($read) as $client) {

                $moribund = false;

                // check if client closed connection
                $bytes = $client->getSocket()->peek(1);

                if (mb_strlen($bytes, '8bit') === 0) {
                    $this->log(
                        sprintf(
                            'client %s closed connection.' . PHP_EOL,
                            $client->getIPAddress()
                        ),
                        Net_Notifier_Logger::VERBOSITY_CLIENT
                    );

                    $moribund = true;
                }

                try {

                    if ($client->read(self::READ_BUFFER_LENGTH)) {

                        if ($client->getState() < Net_Notifier_WebSocket_Connection::STATE_CLOSING) {

                            $receivedRelayMessage = false;
                            $messages = $client->getTextMessages();
                            foreach ($messages as $message) {
                                $this->log(
                                    sprintf(
                                        'received message: "%s" from %s'
                                        . PHP_EOL,
                                        $message,
                                        $client->getIPAddress()
                                    ),
                                    Net_Notifier_Logger::VERBOSITY_MESSAGES
                                );

                                $message = json_decode($message, true);

                                if (   $message === false
                                    || !isset($message['action'])
                                ) {
                                    $this->log(
                                        '=> incorrectly formatted' . PHP_EOL,
                                        Net_Notifier_Logger::VERBOSITY_MESSAGES
                                    );

                                    $this->startCloseClient(
                                        $client,
                                        Net_Notifier_WebSocket_Connection::CLOSE_PROTOCOL_ERROR,
                                        'Incorrectly formatted message.'
                                    );

                                    break;
                                }

                                if ($message['action'] === 'shutdown') {
                                    $this->log(
                                        sprintf(
                                            'shutting down at request of %s'
                                            . PHP_EOL,
                                            $client->getIPAddress()
                                        ),
                                        Net_Notifier_Logger::VERBOSITY_MESSAGES
                                    );

                                    $this->startCloseClient(
                                        $client,
                                        Net_Notifier_WebSocket_Connection::CLOSE_NORMAL,
                                        'Received shutdown message.'
                                    );

                                    $this->moribund = true;
                                }

                                if ($message['action'] === 'listen') {
                                    $this->log(
                                        sprintf(
                                            'set %s to listen' . PHP_EOL,
                                            $client->getIPAddress()
                                        ),
                                        Net_Notifier_Logger::VERBOSITY_MESSAGES
                                    );

                                    if (!in_array($client, $this->listenClients)) {
                                        $this->listenClients[] = $client;
                                    }
                                } else {
                                    $this->relayNotification($message);
                                    $receivedRelayMessage = true;
                                }
                            }

                            if ($receivedRelayMessage) {
                                $this->startCloseClient(
                                    $client,
                                    Net_Notifier_WebSocket_Connection::CLOSE_NORMAL,
                                    'Received message for relay.'
                                );
                            }
                        }

                    } else {

                        $this->log(
                            sprintf(
                                'got a message chunk from %s' . PHP_EOL,
                                $client->getIPAddress()
                            ),
                            Net_Notifier_Logger::VERBOSITY_CLIENT
                        );

                        if ($client->getState() === Net_Notifier_WebSocket_Connection::STATE_CLOSED) {
                            $this->log(
                                sprintf(
                                    'completed close handshake from %s'
                                    . PHP_EOL,
                                    $client->getIPAddress()
                                ),
                                Net_Notifier_Logger::VERBOSITY_CLIENT
                            );
                            $moribund = true;
                        }

                    }

                } catch (Net_Notifier_WebSocket_HandshakeFailureException $e) {
                    $this->log(
                        sprintf(
                            'failed client handshake: %s' . PHP_EOL,
                            $e->getMessage()
                        ),
                        Net_Notifier_Logger::VERBOSITY_CLIENT
                    );
                }

                if ($moribund) {
                    $this->closeClient($client);
                }

            }

        }

        $this->disconnect();
    }

    // }}}
    // {{{ handleSignal()

    /**
     * Handles UNIX signals
     *
     * SIGTERM and SIGINT can be used to trigger a clean shutdown of
     * this server.
     *
     * @param integer $signal the signal received.
     *
     * @return void
     */
    public function handleSignal($signal)
    {
        switch ($signal) {
        case SIGINT:
            $this->log(
                'Received SIGINT, shutting down.' . PHP_EOL,
                Net_Notifier_Logger::VERBOSITY_ALL
            );
            $this->moribund = true;
            break;

        case SIGTERM:
            $this->log(
                'Received SIGTERM, shutting down.' . PHP_EOL,
                Net_Notifier_Logger::VERBOSITY_ALL
            );
            $this->moribund = true;
            break;
        }
    }

    // }}}
    // {{{ relayNotification()

    /**
     * Notifies all connected clients of an event
     *
     * @param string $message the message being relayed.
     *
     * @return void
     */
    protected function relayNotification($message)
    {
        $message = json_encode($message);

        foreach ($this->listenClients as $client) {
            if ($client->getState() < Net_Notifier_WebSocket_Connection::STATE_CLOSING) {

                $this->log(
                    sprintf(
                        ' ... relaying message "%s" to %s ... ',
                        $message,
                        $client->getIPAddress()
                    ),
                    Net_Notifier_Logger::VERBOSITY_CLIENT
                );

                $client->writeText($message);

                $this->log(
                    'done' . PHP_EOL,
                    Net_Notifier_Logger::VERBOSITY_CLIENT,
                    false
                );
            }
        }
    }

    // }}}
    // {{{ connect()

    /**
     * Sets up this server's listen socket
     *
     * @return void
     */
    protected function connect()
    {
        $errstr = '';
        $errno  = 0;

        $this->log('creating socket ... ', Net_Notifier_Logger::VERBOSITY_ALL);

        try {
            $this->socket = new Net_Notifier_Socket_Server(
                sprintf(
                    'tcp://0.0.0.0:%s',
                    $this->port
                ),
                null
            );
        } catch (Net_Notifier_Socket_Exception $e) {
            $this->log(
                'failed' . PHP_EOL,
                Net_Notifier_Logger::VERBOSITY_ALL,
                false
            );
            throw $e;
        }

        $this->log('done' . PHP_EOL, Net_Notifier_Logger::VERBOSITY_ALL, false);

        $this->connected = true;
    }

    // }}}
    // {{{ disconnect()

    /**
     * Closes all client sockets and the server listen socket
     *
     * @return void
     */
    protected function disconnect()
    {
        $this->log('closing sockets ... ', Net_Notifier_Logger::VERBOSITY_ALL);

        foreach ($this->clients as $client) {
            $client->startClose(
                Net_Notifier_WebSocket_Connection::CLOSE_GOING_AWAY,
                'Server shutting down.'
            );
        }

        $this->clients = array();
        $this->socket  = null;

        $this->log('done' . PHP_EOL, Net_Notifier_Logger::VERBOSITY_ALL, false);

        $this->connected = false;
    }

    // }}}
    // {{{ closeClient()

    /**
     * Closes a client socket and removes the client from the list of clients
     *
     * @param Net_Notifier_WebSocket_Connection $client the client to close.
     *
     * @return void
     */
    protected function closeClient(
        Net_Notifier_WebSocket_Connection $client
    ) {
        $this->log(
            sprintf(
                'closing client %s ... ',
                $client->getIPAddress()
            ),
            Net_Notifier_Logger::VERBOSITY_CLIENT
        );

        if ($client->getState() < Net_Notifier_WebSocket_Connection::STATE_CLOSED) {
            $client->close();
        }

        $key = array_search($client, $this->clients);
        unset($this->clients[$key]);

        $key = array_search($client, $this->listenClients);
        if ($key !== false) {
            unset($this->listenClients[$key]);
        }

        $this->log(
            'done' . PHP_EOL,
            Net_Notifier_Logger::VERBOSITY_CLIENT,
            false
        );
    }

    // }}}
    // {{{ startCloseClient()

    /**
     * Initiates the closing handshake for a client connection
     *
     * @param Net_Notifier_WebSocket_Connection $client the client to close.
     * @param integer                           $code   optional. The WebSocket close
     *                                                  close reason code. If
     *                                                  not specified,
     *                                                  {@link Net_Notifier_WebSocket_Connection::CLOSE_NORMAL}
     *                                                  is used.
     * @param string                            $reason optional. A description
     *                                                  of why the connection
     *                                                  is being closed.
     *
     * @return void
     */
    protected function startCloseClient(
        Net_Notifier_WebSocket_Connection $client,
        $code = Net_Notifier_WebSocket_Connection::CLOSE_NORMAL,
        $reason = ''
    ) {
        $this->log(
            sprintf(
                'disconnecting client from %s for reason "%s" ... ',
                $client->getIPAddress(),
                $reason
            ),
            Net_Notifier_Logger::VERBOSITY_CLIENT
        );

        $client->startClose($code, $reason);

        $this->log(
            'done' . PHP_EOL,
            Net_Notifier_Logger::VERBOSITY_CLIENT,
            false
        );
    }

    // }}}
    // {{{ getReadClients()

    /**
     * Gets an array of client connections whose sockets were read
     *
     * @param array &$read an array of sockets that were read.
     *
     * @return array an array of {@link Net_Notifier_WebSocket_Connection}
     *               objects having sockets found in the given array of read
     *               sockets.
     */
    protected function &getReadClients(&$read)
    {
        $clients = array();

        foreach ($this->clients as $client) {
            if (in_array($client->getSocket()->getRawSocket(), $read)) {
                $clients[] = $client;
            }
        }

        return $clients;
    }

    // }}}
    // {{{ getReadArray()

    /**
     * Gets an array of sockets to check for reading
     *
     * @return array an aray of sockets to check for reading.
     */
    protected function &getReadArray()
    {
        $readArray = array();
        $readArray[] = $this->socket->getRawSocket();
        foreach ($this->clients as $client) {
            if ($client->getState() < Net_Notifier_WebSocket_Connection::STATE_CLOSED) {
                $readArray[] = $client->getSocket()->getRawSocket();
            }
        }

        return $readArray;
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
