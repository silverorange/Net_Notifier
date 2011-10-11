<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * WebSocket handshake parser used by cha-ching client connection
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
 * @copyright 2010-2011 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

require_once 'Net/ChaChing/WebSocket/ProtocolException.php';

/**
 * A client connection to the cha-ching server
 *
 * This class is intended to be used internally by the
 * {@link ChaChingServerClientConnection} class.
 *
 * @category  Net
 * @package   ChaChing
 * @copyright 2010-2011 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Net_ChaChing_WebSocket_Handshake
{
    // {{{ class constants

    /**
     * Magic number used to identify WebSocket handshake requests
     *
     * Taken from the IETF draft spec version 10.
     */
    const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    /**
     * Protocol version
     */
    const VERSION = 8;

    // }}}
    // {{{ protected properties

    /**
     * Supported application-specific sub-protocols
     *
     * @var array
     */
    protected $protocols = array();

    // }}}
    // {{{ __construct()

    /**
     * Creates a new handshake
     *
     * @param array $protocols optional. A list of supported application-
     *                         specific sub-protocols. If this array is
     *                         specified, only handshake requests for the
     *                         specified protocols will succeed.
     */
    public function __construct(array $protocols = array())
    {
        $this->protocols = $protocols;
    }

    // }}}
    // {{{ handshake()

    /**
     * Does the actual WebSocket handshake
     *
     * See sections 1.3 and 6.2 of
     * {@link http://www.whatwg.org/specs/web-socket-protocol/ The WebSocket Protocol}
     * for further details.
     *
     * @param string $handshake the handshake request.
     *
     * @return string the handshake response.
     */
    public function handshake($handshake)
    {
        $handshake = $this->parseHeaders($handshake);
        $headers   = $handshake['headers'];
        $key       = $headers['Sec-WebSocket-Key'];
        $accept    = $this->getAccept($key);
        $version   = $headers['Sec-WebSocket-Version'];

        if ($version == self::VERSION) {

            $response =
                "HTTP/1.1 101 Switching Protocols\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept: " . $accept . "\r\n";

            if (isset($headers['Sec-WebSocket-Protocol'])) {
                $protocols = explode(',', $headers['Sec-WebSocket-Protocol']);
                $protocols = array_map('trim', $protocols);
                $supportedProtocol = $this->getSupportedProtocol($protocols);

                if ($supportedProtocol === null) {
                    throw new Net_ChaChing_WebSocket_ProtocolException(
                        'None of the requested sub-protocols ('
                        . implode(', ', $protocols) . ') are supported by '
                        . 'this server.',
                        0,
                        $this->protocols,
                        $protocols
                    );
                }

                $response .= 'Sec-WebSocket-Protocol: '
                    . $supportedProtocol . "\r\n";
            }

        } else {

            $response =
                "HTTP/1.1 426 Upgrade Required\r\n";
                "Sec-WebSocket-Version: " . self::VERSION . "\r\n";

        }

        $response .= "\r\n";

        return $response;
    }

    // }}}
    // {{{ getSupportedProtocol()

    protected function getSupportedProtocol(array $protocols)
    {
        $supportedProtocol = null;

        foreach ($protocols as $protocol) {
            if (in_array($protocol, $this->protocols)) {
                $supportedProtocol = $protocol;
                break;
            }
        }

        return $supportedProtocol;
    }

    // }}}
    // {{{ parseHeaders()

    /**
     * Parses the raw handshake header into an array of headers
     *
     * @param string $header the raw handshake header.
     *
     * @return array a structured array containing the following:
     *               - <kbd>status</kbd>  - the handshake status line, and
     *               - <kbd>headers</kbd> - an array of headers. Array keys
     *                                      are the header names and array
     *                                      values are the values.
     */
    protected function parseHeaders($header)
    {
        $parsedHeaders = array(
            'status'  => '',
            'headers' => array()
        );

        $headers = explode("\r\n", $header);
        $parsedHeaders['status'] = array_shift($headers);

        foreach ($headers as $header) {
            list($name, $value) = explode(':', $header, 2);
            $parsedHeaders['headers'][$name] = ltrim($value);
        }

        return $parsedHeaders;
    }

    // }}}
    // {{{ getAccept()

    /**
     * Gets the accept header value for this handshake
     *
     * The 20-character string is derived through the following:
     *
     *  1. start with the key string
     *  2. append the string 258EAFA5-E914-47DA-95CA-C5AB0DC85B11
     *  3. take the sha1() of the resulting string
     *  4. base-64 encode the sha1 result
     *
     * See section 5.2.2 of {@link http://www.whatwg.org/specs/web-socket-protocol/ The WebSocket Protocol}
     * for further details.
     *
     * @param string $key the key from which to generate the accept hash.
     *
     * @return string the accept hash.
     */
    protected function getAccept($key)
    {
        $accept = $key . self::GUID;
        $accept = sha1($accept, true);
        $accept = base64_encode($accept);
        return $accept;
    }

    // }}}
}

?>
