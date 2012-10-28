<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Notifier WebSocket client class
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
 * @copyright 2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

/**
 * Notifier WebSocket protocol definition.
 */
require_once 'Net/Notifier/WebSocket.php';

/**
 * Socket wrapper class.
 */
require_once 'Net/Notifier/Socket/Client.php';

/**
 * WebSocket connection class.
 */
require_once 'Net/Notifier/WebSocket/Connection.php';

/**
 * Client exception class.
 */
require_once 'Net/Notifier/ClientException.php';

/**
 * Base class for notification clients
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class Net_Notifier_Client
{
    // {{{ class constants

    /**
     * How long the read buffer for connections is.
     *
     * If this is too short, multiple read calls will be made on client
     * connections to receive messages.
     */
    const READ_BUFFER_LENGTH = 2048;

    // }}}
    // {{{ protected properties

    /**
     * Server connection port
     *
     * @var integer
     *
     * @see Net_Notifier_Client::parseAddress()
     * @see Net_Notifier_Client::setPort()
     */
    protected $port = 3000;

    /**
     * Server host name or IP address
     *
     * @var string
     *
     * @see Net_Notifier_Client::parseAddress()
     * @see Net_Notifier_Client::setHost()
     */
    protected $host = 'localhost';

    /**
     * WebSocket resource name
     *
     * @var string
     *
     * @see Net_Notifier_Client::parseAddress()
     * @see Net_Notifier_Client::setResource()
     */
    protected $resource = '/';

    /**
     * Client connection timeout in milliseconds
     *
     * @var integer
     */
    protected $timeout = 200;

    /**
     * The connection to the WebSocket server
     *
     * @var Net_Notifier_WebSocket_Connection
     */
    protected $connection = null;

    /**
     * The socket connection of this client
     *
     * @var Net_Notifier_Socket_Client
     */
    protected $socket = null;

    // }}}
    // {{{ __construct()

    /**
     * Creates a new client for sending notifications
     *
     * @param string  $address the WebSocket address of the notification server.
     *                         Must be in the form
     *                         ws://host-or-ip:port/resource.
     * @param integer $timeout optional. Client connection timeout in
     *                         milliseconds. If not specified, the connection
     *                         timeout is 200 milliseconds.
     */
    public function __construct(
        $address,
        $timeout = 2020
    ) {
        $this->parseAddress($address);
        $this->setTimeout($timeout);
    }

    // }}}
    // {{{ __destruct()

    /**
     * Disconnects this client upon object destruction if it is connected
     *
     * @return void
     */
    public function __destruct()
    {
        if ($this->connection instanceof Net_Notifier_WebSocket_Connection) {
            $this->disconnect();
        }
    }

    // }}}
    // {{{ parseAddress()

    /**
     * Parses a WebSocket address and sets the constituent parts for this
     * client
     *
     * A WebSocket address looks like 'ws://hostname:port/resource-name'. If
     * present, the host, port and resource of this client are set from the
     * parsed values.
     *
     * @param string $address the address to parse.
     *
     * @return Net_Notifier_Client the current object, for fluent interface.
     *
     * @throws Net_Notifier_ClientException if the specified address is not a
     *         properly formatted WebSocket address.
     */
    public function parseAddress($address)
    {
        $exp = '!^wss?://([\w-.]+?)(?::(\d+))?(/.*)?$!';
        $matches = array();
        if (!preg_match($exp, $address, $matches)) {
            throw new Net_Notifier_ClientException(
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

        return $this;
    }

    // }}}
    // {{{ setHost()

    /**
     * Sets the server host name or IP address for this client
     *
     * @param string $host the server host name or IP address for this client.
     *
     * @return Net_Notifier_Client the current object, for fluent interface.
     */
    public function setHost($host)
    {
        $this->host = (string)$host;
        return $this;
    }

    // }}}
    // {{{ setPort()

    /**
     * Sets the connection port for this client
     *
     * @param integer $port the connection port for this client.
     *
     * @return Net_Notifier_Client the current object, for fluent interface.
     */
    public function setPort($port)
    {
        $this->port = (integer)$port;
        return $this;
    }

    // }}}
    // {{{ setResource()

    /**
     * Sets the WebSocket resource for this client
     *
     * @param string $resource the WebSocket resource name for this client.
     *
     * @return Net_Notifier_Client the current object, for fluent interface.
     */
    public function setResource($resource)
    {
        $this->resource = (string)$resource;
        return $this;
    }

    // }}}
    // {{{ setTimeout()

    /**
     * Sets this client's connection timeout in milliseconds
     *
     * @param integer $timeout this client's connection timeout in milliseconds.
     *
     * @return Net_Notifier_Client the current object, for fluent interface.
     */
    public function setTimeout($timeout)
    {
        $this->timeout = (integer)$timeout;
        return $this;
    }

    // }}}
    // {{{ connect()

    /**
     * Connects this client to the cha-ching server
     *
     * A socket connection is opened and the WebSocket handshake is initiated.
     *
     * @return void
     *
     * @throws Net_Notifier_ClientException if there is an error connecting to
     *         the notification server.
     */
    protected function connect()
    {
        $this->socket = new Net_Notifier_Socket_Client(
            sprintf(
                'tcp://%s:%s',
                $this->host,
                $this->port
            ),
            $this->timeout / 1000
        );

        $this->connection = new Net_Notifier_WebSocket_Connection(
            $this->socket
        );

        $this->connection->startHandshake(
            $this->host,
            $this->port,
            $this->resource,
            array(Net_Notifier_WebSocket::PROTOCOL)
        );

        // read handshake response
        $state = $this->connection->getState();
        while ($state < Net_Notifier_WebSocket_Connection::STATE_OPEN) {

            $read = array($this->socket->getRawSocket());

            $result = stream_select(
                $read,
                $write = null,
                $except = null,
                null
            );

            if ($result === 1) {
                $this->connection->read(self::READ_BUFFER_LENGTH);
            }

            $state = $this->connection->getState();
        }
    }

    // }}}
    // {{{ disconnect()

    /**
     * Disconnects this client from the server
     *
     * @return void
     */
    protected function disconnect()
    {
        // Initiate connection close. The WebSockets RFC recomends against
        // clients initiating the close handshake but we want to ensure the
        // connection is closed as soon as possible.
        $this->connection->startClose(
            Net_Notifier_WebSocket_Connection::CLOSE_GOING_AWAY,
            'Client sent message.'
        );

        // read server close frame
        $state = $this->connection->getState();

        $sec  = intval($this->timeout / 1000);
        $usec = ($this->timeout % 1000) * 1000;

        while ($state < Net_Notifier_WebSocket_Connection::STATE_CLOSED) {
            $read = array($this->socket->getRawSocket());

            $result = stream_select(
                $read,
                $write = null,
                $except = null,
                $sec,
                $usec
            );

            if ($result === 1) {
                $this->connection->read(self::READ_BUFFER_LENGTH);
            } else {
                // read timed out, just close the connection
                $this->connection->close();
            }

            $state = $this->connection->getState();
        }

        $this->connection = null;
    }

    // }}}
}

?>
