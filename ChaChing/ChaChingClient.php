<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Cha-ching pinging client
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
 * @copyright 2006-2010 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

/**
 * Pinging client for the silverorange cha-ching server
 *
 * This client sends a message to the silverorange cha-ching server causing
 * various halarious sound effects.
 *
 * The cha-ching system works as follows:
 *
 * Server:
 *  Runs on a single system and receives requests. The server propegates
 *  cha-ching pings to connected playback clients.
 *
 * Playback Client:
 *  Runs on one or many machines. Clients connect to a server and play noises
 *  that are pushed from the server.
 *
 * Ping Client (this class):
 *  Notifies the cha-ching server to push a cha-ching to connected playback
 *  clients.
 *
 * Cha-ching pings are sent to the server in the following format:
 *
 * <pre>
 * Byte1      | Byte2      | Remaining Bytes
 * -----------+------------+----------------------------
 * Length MSB | Length LSB | Arbitrary string of Length characters identifying
 *            |            | the cha-ching sound to play.
 * </pre>
 *
 * @category  Net
 * @package   ChaChing
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2006-2010 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class ChaChingClient
{
    // {{{ class constants

    /**
     * Connect socket timeout value
     */
    const TIMEOUT = 5;

    // }}}
    // {{{ protected properties

    /**
     * The address of the cha-ching server
     *
     * @var string
     */
    protected $address;

    /**
     * The port of the cha-ching server
     *
     * By default, this is 2000.
     *
     * @var integer
     */
    protected $port;

    /**
     * The stream connection to the cha-ching server
     *
     * @var resource
     */
    protected $stream;

    /**
     * Whether or not this client is connected
     *
     * @var boolean
     */
    protected $connected = false;

    /**
     * Old error handler so some errors can be handed off instead of suppressed
     *
     * @var mixed
     *
     * @see ChachingClient::handleError()
     */
    protected $oldErrorHandler = null;

    // }}}
    // {{{ __construct()

    /**
     * Creates a new cha-ching client
     *
     * @param string  $address the address of the cha-ching server.
     * @param integer $port    an optional port number of the cha-ching server.
     *                         If no port number is specified, 2000 is used.
     */
    public function __construct($address, $port = 2000)
    {
        $this->address = $address;
        $this->port = $port;
    }

    // }}}
    // {{{ __destruct()

    /**
     * Always disconnect if we're connected and the client is destroyed
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
    // {{{ chaChing()

    /**
     * Sends a cha-ching request
     *
     * @param string $id    the id of the cha-ching sound to play.
     * @param float  $value optional. A value to associate with the id.
     *
     * @return void
     */
    public function chaChing($id, $value = null)
    {
        $this->oldErrorHandler = set_error_handler(
            array($this, 'handleError')
        );

        // for backwards compatibility with older playback clients
        $this->connect();
        $this->send($id);
        $this->disconnect();

        $this->connect();
        $message = json_encode(array('id' => $id, 'value' => $value));
        $this->send($message);
        $this->disconnect();

        restore_error_handler();
    }

    // }}}
    // {{{ handleError()

    /**
     * Handles PHP errors that may be raised by socket operations
     *
     * Since the cha-ching client is non-critical, we fail silently.
     *
     * @param integer $errno   the level of the error raised.
     * @param string  $errstr  the error message.
     * @param string  $errfile the filename that the error was raised in.
     * @param integer $errline the line number the error was raised at.
     *
     * @return boolean false.
     */
    public function handleError($errno, $errstr, $errfile, $errline)
    {
        // don't suppress user errors since they should cause the script to end
        if ($errno === E_USER_ERROR) {
            if ($this->oldErrorHandler !== null) {
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

    // }}}
    // {{{ send()

    /**
     * Sends data to the cha-ching server
     *
     * Data is only sent if this client is connected.
     *
     * @param string $data the data to send.
     *
     * @return void
     */
    protected function send($data)
    {
        if ($this->connected) {
            $lengthData = $this->getLengthData($data);
            fwrite($this->stream, $lengthData . $data);
        }
    }

    // }}}
    // {{{ connect()

    /**
     * Connects this client to the cha-ching server
     *
     * @return void
     */
    protected function connect()
    {
        $this->stream = fsockopen(
            $this->address,
            $this->port,
            $errno,
            $errstr,
            self::TIMEOUT
        );

        if ($this->stream === false) {
            // handle error
        } else {
            stream_set_blocking($this->stream, false);
            $this->connected = true;
        }
    }

    // }}}
    // {{{ disconnect()

    /**
     * Disconnects this client from the cha-ching server if it is connected
     *
     * @return void
     */
    protected function disconnect()
    {
        if ($this->connected) {
            fflush($this->stream);
            fclose($this->stream);
            $this->connected = false;
        }
    }

    // }}}
    // {{{ getLengthData()

    /**
     * Gets a two byte string containing encoding the integer character-length
     * of the given data
     *
     * The high-order byte is displayed first (big-endian).
     *
     * @param string $data the data from which to get the length data.
     *
     * @return string a two-byte big-endian binary string representing the
     *                integer character-length of the given data.
     */
    protected function getLengthData($data)
    {
        $byteLength = mb_strlen($data, '8bit');
        return pack('n', $byteLength);
    }

    // }}}
}

?>
