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
 * @copyright 2006-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

/**
 * Client connection class.
 */
require_once 'Net/Notifier/WebSocket/Connection.php';

/**
 * Socket class definition for listening for incomming connections.
 */
require_once 'Net/Notifier/Socket/Server.php';

/**
 * Socket class definition for accepting client connections.
 */
require_once 'Net/Notifier/Socket/Accept.php';

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
 * @copyright 2006-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Net_Notifier_Server
{
    // {{{ class constants

    /**
     * How long the read buffer for client connections is.
     *
     * If this is too short, multiple read calls will be made on client
     * connections to receive messages.
     */
    const READ_BUFFER_LENGTH = 2048;

    /**
     * How long the write buffer for client connections is.
     */
    const WRITE_BUFFER_LENGTH = 2048;

    /**
     * Verbosity level for showing nothing.
     */
    const VERBOSITY_NONE = 0;

    /**
     * Verbosity level for showing fatal errors.
     */
    const VERBOSITY_ERRORS = 1;

    /**
     * Verbosity level for showing relayed messages.
     */
    const VERBOSITY_MESSAGES = 2;

    /**
     * Verbosity level for showing all client activity.
     */
    const VERBOSITY_CLIENT = 3;

    /**
     * Verbosity level for showing all activity.
     */
    const VERBOSITY_ALL = 4;

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
     * The level of verbosity to use
     *
     * @var integer
     *
     * @see Net_Notifier_Server::setVerbosity()
     * @see Net_Notifier_Server::VERBOSITY_NONE
     * @see Net_Notifier_Server::VERBOSITY_ERRORS
     * @see Net_Notifier_Server::VERBOSITY_MESSAGES
     * @see Net_Notifier_Server::VERBOSITY_CLIENT
     * @see Net_Notifier_Server::VERBOSITY_ALL
     */
    protected $verbosity = 1;

    /**
     * Clients connected to this server
     *
     * This is an array of {@link Net_Notifier_WebSocket_Connection} objects.
     *
     * @var array
     */
    protected $clients = array();

    /**
     * Whether or not this server is running
     *
     * @var boolean
     */
    protected $connected = false;

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
    // {{{ setVerbosity()

    /**
     * Sets the level of verbosity to use
     *
     * @param integer $verbosity the level of verbosity to use.
     *
     * @return void
     *
     * @see Net_Notifier_Server::VERBOSITY_NONE
     * @see Net_Notifier_Server::VERBOSITY_ERRORS
     * @see Net_Notifier_Server::VERBOSITY_MESSAGES
     * @see Net_Notifier_Server::VERBOSITY_CLIENT
     * @see Net_Notifier_Server::VERBOSITY_ALL
     */
    public function setVerbosity($verbosity)
    {
        $this->verbosity = (integer)$verbosity;
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

        while (true) {

            $read = $this->getReadArray();

            $result = stream_select(
                $read,
                $write = null,
                $except = null,
                null
            );

            if ($result < 1) {
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
                    $this->output(
                        "Accepting client connection failed: reason: " .
                        $e->getMessage() . "\n",
                        self::VERBOSITY_ERRORS
                    );
                    exit(1);
                }

                $client = new Net_Notifier_WebSocket_Connection($newSocket);
                $this->clients[] = $client;
                $this->output(
                    "client connected from " . $client->getIpAddress() . "\n",
                    self::VERBOSITY_CLIENT
                );
            }

            // check for client data
            foreach ($this->getReadClients($read) as $client) {

                $moribund = false;

                // check if client closed connection
                $bytes = $client->getSocket()->peek(32);

                if (mb_strlen($bytes, '8bit') === 0) {
                    $this->output(
                        "client " . $client->getIpAddress() . " closed "
                        . "connection.\n",
                        self::VERBOSITY_CLIENT
                    );

                    $moribund = true;
                }

                try {

                    if ($client->read(self::READ_BUFFER_LENGTH)) {

                        if ($client->getState() < Net_Notifier_WebSocket_Connection::STATE_CLOSING) {
                            $this->startCloseClient(
                                $client,
                                Net_Notifier_WebSocket_Connection::CLOSE_NORMAL,
                                'Received message.'
                            );

                            $messages = $client->getTextMessages();
                            foreach ($messages as $message) {
                                if ($message === 'shutdown') {
                                    $this->output(
                                        "received shutdown request\n",
                                        self::VERBOSITY_MESSAGES
                                    );
                                    break 3;
                                }

                                if (mb_strlen($message, '8bit') > 0) {
                                    $this->output(
                                        sprintf(
                                            "received message: '%s'\n",
                                            $message
                                        ),
                                        self::VERBOSITY_MESSAGES
                                    );
                                    $this->relayNotification($message);
                                }
                            }
                        }

                    } else {

                        $this->output(
                            sprintf(
                                "got a message chunk from %s\n",
                                $client->getIpAddress()
                            ),
                            self::VERBOSITY_CLIENT
                        );

                    }

                } catch (Net_Notifier_WebSocket_HandshakeFailureException $e) {
                    $this->output(
                        sprintf(
                            "failed client handshake: %s\n",
                            $e->getMessage()
                        ),
                        self::VERBOSITY_CLIENT
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
        foreach ($this->clients as $client) {
            if ($client->getState() < Net_Notifier_WebSocket_Connection::STATE_CLOSING) {

                $this->output(
                    "=> writing message '" . $message . "' to " .
                    $client->getIpAddress() . " ... ",
                    self::VERBOSITY_CLIENT
                );

                $client->writeText($message);

                $this->output("done\n", self::VERBOSITY_CLIENT, false);

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

        $this->output("creating socket ... ", self::VERBOSITY_ALL);

        try {
            $this->socket = new Net_Notifier_Socket_Server(
                sprintf(
                    'tcp://0.0.0.0:%s',
                    $this->port
                ),
                null
            );
        } catch (Net_Notifier_Socket_Exception $e) {
            $this->output(
                "failed\nreason: " . $e->getMessage() . "\n",
                self::VERBOSITY_ERRORS,
                false
            );
            exit(1);
        }

        $this->output("done\n", self::VERBOSITY_ALL, false);

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
        $this->output("closing sockets ... ", self::VERBOSITY_ALL);

        foreach ($this->clients as $client) {
            $client->startClose(
                Net_Notifier_WebSocket_Connection::CLOSE_GOING_AWAY,
                'Server shutting down.'
            );
        }

        $this->clients = array();
        fclose($this->socket);

        $this->output("done\n", self::VERBOSITY_ALL, false);

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
        $this->output(
            "Closing client " . $client->getIpAddress() . " ... ",
            self::VERBOSITY_CLIENT
        );

        $client->close();
        $key = array_search($client, $this->clients);
        unset($this->clients[$key]);

        $this->output("done\n", self::VERBOSITY_CLIENT, false);
    }

    // }}}
    // {{ startCloseClient()

    protected function startCloseClient(
        Net_Notifier_WebSocket_Connection $client,
        $code = Net_Notifier_WebSocket_Connection::CLOSE_NORMAL,
        $reason = ''
    ) {
        $this->output(
            "disconnecting client from " . $client->getIpAddress() .
            " for reason '" . $reason . "' ... ",
            self::VERBOSITY_CLIENT
        );

        $client->startClose($code, $reason);

        $this->output("done\n", self::VERBOSITY_CLIENT, false);
    }

    // }}
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
    // {{{ output()

    /**
     * Displays a debug string based on the verbosity level
     *
     * @param string  $string    the string to display.
     * @param integer $verbosity an optional verbosity level to display at. By
     *                           default, this is 1.
     * @param boolean $timestamp optional. Whether or not to include a
     *                           timestamp with the output.
     *
     * @return void
     */
    protected function output($string, $verbosity = 1, $timestamp = true)
    {
        if ($verbosity <= $this->verbosity) {
            if ($timestamp) {
                echo '[' . date('Y-m-d H:i:s') . '] ' . $string;
            } else {
                echo $string;
            }
        }
    }

    // }}}
}

?>
