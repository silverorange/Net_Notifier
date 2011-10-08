<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Cha-ching server class
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
 * @package   ChaChing
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2006-2011 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

/**
 * Client connection class.
 */
require_once 'Net/ChaChing/WebSocket/ClientConnection.php';

/**
 * A server process for sending and receiving cha-ching notifications
 *
 * The cha-ching server interacts with two types of clients. The first type
 * of client connects to the server, sends a message and disconnects. The
 * second type of client never sends data and remains connected to the server.
 * When a client of type one sends a message to the server, the server relays
 * the message to all connected clients of type two.
 *
 * @category  Net
 * @package   ChaChing
 * @copyright 2006-2011 silverorange
 * @author    Michael Gauthier <mike@silverorange.com>
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Net_ChaChing_WebSocket_Server
{
    // {{{ class constants

    /**
     * How many connections the server will queue for processing.
     */
    const CONNECTION_QUEUE_LENGTH = 20;

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
     * @var resource
     */
    protected $socket;

    /**
     * The port this server runs on
     *
     * By default this is 2000. See
     * {@link Net_ChaChingS_Socket_Server::__construct()} touse a different
     * port.
     *
     * @var integer
     */
    protected $port;

    /**
     * The level of verbosity to use
     *
     * @var integer
     *
     * @see Net_ChaChing_Socket_Server::setVerbosity()
     * @see Net_ChaChing_Socket_Server::VERBOSITY_NONE
     * @see Net_ChaChing_Socket_Server::VERBOSITY_ERRORS
     * @see Net_ChaChing_Socket_Server::VERBOSITY_MESSAGES
     * @see Net_ChaChing_Socket_Server::VERBOSITY_CLIENT
     * @see Net_ChaChing_Socket_Server::VERBOSITY_ALL
     */
    protected $verbosity = 1;

    /**
     * Clients connected to this server
     *
     * This is an array of {@link Net_ChaChing_Socket_ClientConnection} objects.
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
     * Creates a new cha-ching server
     *
     * @param integer $port the port on which this server should listen for
     *                      incomming connections.
     *
     * @see Net_ChaChing_Socket_Server::run()
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
     * @see Net_ChaChing_Socket_Server::VERBOSITY_NONE
     * @see Net_ChaChing_Socket_Server::VERBOSITY_ERRORS
     * @see Net_ChaChing_Socket_Server::VERBOSITY_MESSAGES
     * @see Net_ChaChing_Socket_Server::VERBOSITY_CLIENT
     * @see Net_ChaChing_Socket_Server::VERBOSITY_ALL
     */
    public function setVerbosity($verbosity)
    {
        $this->verbosity = (integer)$verbosity;
    }

    // }}}
    // {{{ run()

    /**
     * Runs this cha-ching server
     *
     * The cha-ching server receives client connections and sends and receives
     * data to and from connected clients.
     *
     * @return void
     */
    public function run()
    {
        $this->connect();

        while (true) {

            $read  = $this->getReadArray();

            $result = socket_select(
                $read,
                $write = null,
                $except = null,
                null
            );

            if ($result < 1) {
                continue;
            }

            // check for new connections
            if (in_array($this->socket, $read)) {
                if (($newSocket = socket_accept($this->socket)) < 0) {
                    $this->output(
                        "socket_accept() failed: reason: " .
                        socket_strerror(socket_last_error()) . "\n",
                        self::VERBOSITY_ERRORS
                    );
                    exit(1);
                }

                $client = new Net_ChaChing_WebSocket_ClientConnection(
                    $newSocket
                );
                $this->clients[] = $client;
                $this->output(
                    "client connected from " . $client->getIpAddress() . "\n",
                    self::VERBOSITY_CLIENT
                );
            }

            // check for client data
            foreach ($this->getReadClients($read) as $client) {
                if ($client->read(self::READ_BUFFER_LENGTH)) {

                    $this->disconnectClient($client, 'Received message.');

                    $messages = $client->getMessages();
                    foreach ($messages as $message) {
                        if ($message === 'shutdown') {
                            $this->output(
                                "received shutdown request\n",
                                self::VERBOSITY_MESSAGES
                            );
                            break 2;
                        }

                        if (mb_strlen($message, '8bit') > 0) {
                            $this->output(
                                "received message: '" . $message . "'\n",
                                self::VERBOSITY_MESSAGES
                            );
                            $this->dispatchEvent($message);
                        }
                    }

                } else {
                    $this->output(
                        "got a message chunk\n",
                        self::VERBOSITY_CLIENT
                    );
                }
            }

        }

        $this->disconnect();
    }

    // }}}
    // {{{ dispatchEvent()

    /**
     * Notifies all connected clients of an event
     *
     * @param string $message the JSON encoded object containing the event
     *                        information. The object is a key-value hash
     *                        containing 'id' and 'value'.
     *
     * @return void
     */
    protected function dispatchEvent($message)
    {
        foreach ($this->clients as $client) {

            $this->output(
                "=> writing message '" . $message . "' to " .
                $client->getIpAddress() . " ... ",
                self::VERBOSITY_CLIENT
            );

            $client->write($message);

            $this->output("done\n", self::VERBOSITY_CLIENT);
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
        $this->output("creating socket ... ", self::VERBOSITY_ALL);
        if (false === ($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
            $this->output(
                "socket_create() failed: reason: " .
                socket_strerror(socket_last_error()) . "\n",
                self::VERBOSITY_ERRORS
            );
            exit(1);
        }
        $this->output("done\n", self::VERBOSITY_ALL);

        $this->output("setting socket as reusable ... ", self::VERBOSITY_ALL);
        if (!socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1)) {
            $this->output(
                "socket_set_option() failed: reason: " .
                socket_strerror(socket_last_error($sock)) . "\n",
                self::VERBOSITY_ERRORS
            );
            exit(1);
        }
        $this->output("done\n", self::VERBOSITY_ALL);

        $this->output("binding socket on port " . $this->port . " ... ",
            self::VERBOSITY_ALL);

        if (!socket_bind($sock, 0, $this->port)) {
            $this->output(
                "socket_bind() failed: reason: " .
                socket_strerror(socket_last_error($sock)) . "\n",
                self::VERBOSITY_ERRORS
            );

            exit(1);
        }
        $this->output("done\n", self::VERBOSITY_ALL);

        $this->output("setting socket to listen ... ", self::VERBOSITY_ALL);
        if (!socket_listen($sock, self::CONNECTION_QUEUE_LENGTH)) {
            $this->output(
                "socket_listen() failed: reason: " .
                socket_strerror(socket_last_error($sock)) . "\n",
                self::VERBOSITY_ERRORS
            );
            exit(1);
        }
        $this->output("done\n", self::VERBOSITY_ALL);

        $this->socket = $sock;
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
            socket_close($client->getSocket());
        }

        $this->clients = array();
        socket_close($this->socket);

        $this->output("done\n", self::VERBOSITY_ALL);

        $this->connected = false;
    }

    // }}}
    // {{{ disconnectClient()

    /**
     * Closes a client socket and removes the client from the list of clients
     *
     * @param Net_ChaChing_WebSocket_ClientConnection $client the client to
     *                                                        disconnect.
     * @param string                                  $reason a text message
     *                                                        explaining why
     *                                                        the client was
     *                                                        disconnected.
     *
     * @return void
     */
    protected function disconnectClient(
        Net_ChaChing_WebSocket_ClientConnection $client,
        $reason
    ) {
        $this->output(
            "disconnecting client from " . $client->getIpAddress() .
            " for reason '" . $reason . "' ... ",
            self::VERBOSITY_CLIENT
        );

        $client->close($reason);
        $key = array_search($client, $this->clients);
        unset($this->clients[$key]);

        $this->output("done\n", self::VERBOSITY_CLIENT);
    }

    // }}}
    // {{{ getReadClients()

    /**
     * Gets an array of client connections whose sockets were read
     *
     * @param array $read an array of sockets that were read.
     *
     * @return array an array of {@link Net_ChaChing_Socket_ClientConnection}
     *               objects having sockets found in the given array of read
     *               sockets.
     */
    protected function &getReadClients(&$read)
    {
        $clients = array();

        foreach ($this->clients as $client) {
            if (in_array($client->getSocket(), $read)) {
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
        $readArray[] = $this->socket;
        foreach ($this->clients as $client) {
            $readArray[] = $client->getSocket();
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
     *
     * @return void
     */
    protected function output($string, $verbosity = 1)
    {
        if ($verbosity <= $this->verbosity) {
            echo $string;
        }
    }

    // }}}
}

?>
