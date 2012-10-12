<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * WebSocket handshake parser used by cha-ching server
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
 * @copyright 2010-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

/**
 * Exception class for unsupported requested sub-protocol
 */
require_once 'Net/ChaChing/WebSocket/ProtocolException.php';

/**
 * Exception class for failing the WebSocket connection
 */
require_once 'Net/ChaChing/WebSocket/HandshakeFailureException.php';

/**
 * Latest update supports RFC 6455.
 *
 * @category  Net
 * @package   ChaChing
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2010-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Net_ChaChing_WebSocket_Handshake
{
    // {{{ class constants

    /**
     * Magic number used to identify WebSocket handshake requests
     *
     * Taken from the IETF RFC 6455.
     *
     * @link http://datatracker.ietf.org/doc/rfc6455/
     */
    const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    /**
     * Protocol version
     *
     * See IETF RFC 6455 Section 4.1 item 9.
     *
     * @link http://datatracker.ietf.org/doc/rfc6455/
     */
    const VERSION = 13;

    // }}}

    /**
     * @param array $protocols optional. A list of supported application-
     *                         specific sub-protocols. If this array is
     *                         specified, only handshake requests for the
     *                         specified protocols will succeed.
     */
    public function start(
        $host,
        $port,
        $nonce,
        $resource = '/',
        array $protocols = array()
    ) {
        $version = Net_ChaChing_WebSocket_Handshake::VERSION;

        $request =
              "GET " . $resource . " HTTP/1.1\r\n"
            . "Host: " . $host . "\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Key: " . $nonce . "\r\n"
            . "Sec-WebSocket-Version: " . $version . "\r\n";

        if (count($protocols) > 0) {
            $protocols = implode(',', $protocols);
            $request .= "Sec-WebSocket-Protocol: " . $protocols . "\r\n";
        }

        $request .= "\r\n";

        return $request;
    }

    // {{{ receive()

    /**
     * Does the actual WebSocket handshake
     *
     * See section 4.2.2 of
     * {@link http://datatracker.ietf.org/doc/rfc6455/ IETF RFC 6455} for
     * further details.
     *
     * @param string $data               the handshake request/response data.
     * @param string $nonce              the nonce value used to validate
     *                                   WebSocket requests. Not set for
     *                                   receiving client handshakes.
     * @param array  $supportedProtocols optional. A list of supported
     *                                   application-specific sub-protocols. If
     *                                   this array is specified, only
     *                                   handshake requests for the specified
     *                                   protocols will succeed.
     *
     * @return string|null the handshake response or null if there is no
     *                     response.
     *
     * @throws Net_ChaChing_WebSocket_HandshakeFailureException if the
     *         handshake fails.
     */
    public function receive($data, $nonce, array $supportedProtocols = array())
    {
        $handshake = $this->parseHeaders($data);
        $headers   = $handshake['headers'];

        // get status code from status line
        $status_parts = explode(' ', $handshake['status']);
        if (count($status_parts) > 1) {
            $status = (integer)$status_parts[1];
        } else {
            $status = 400;
        }

        // get method from status line
        $method = $status_parts[0];

        if ($status == '101') {
            $response = $this->receiveServerHandshake($headers, $nonce);
        } elseif ($method === 'GET') {
            $response = $this->receiveClientHandshake(
                $headers,
                $supportedProtocols
            );
        } else {
            $response = "HTTP/1.1 400 Bad Request\r\n\r\n";
        }


        return $response;
    }

    // }}}

    protected function receiveClientHandshake(
        array $headers,
        array $supportedProtocols
    ) {
/*        if (
               isset($headers['Sec-WebSocket-Version'])
               isset($headers['Connection'])
               $headers['Connection'] == 'Upgrade'
        ) {
        }*/

        $key     = $headers['Sec-WebSocket-Key'];
        $accept  = $this->getAccept($key);
        $version = $headers['Sec-WebSocket-Version'];

        if ($version == self::VERSION) {

            $response =
                  "HTTP/1.1 101 Switching Protocols\r\n"
                . "Upgrade: websocket\r\n"
                . "Connection: Upgrade\r\n"
                . "Sec-WebSocket-Accept: " . $accept . "\r\n";

            if (isset($headers['Sec-WebSocket-Protocol'])) {
                $protocols = explode(',', $headers['Sec-WebSocket-Protocol']);
                $protocols = array_map('trim', $protocols);

                $supportedProtocol = $this->getSupportedProtocol(
                    $supportedProtocols,
                    $protocols
                );

                if ($supportedProtocol === null) {
                    throw new Net_ChaChing_WebSocket_ProtocolException(
                        'None of the requested sub-protocols ('
                        . implode(', ', $protocols) . ') are supported by '
                        . 'this server.',
                        0,
                        $supportedProtocols,
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

    protected function receiveServerHandshake(array $headers, $nonce)
    {
        // Make sure required headers and values are present as per RFC 6455
        // section 4.1 client validation of server response.
        if (!isset($headers['Sec-WebSocket-Accept'])) {
            throw new Net_ChaChing_WebSocket_HandshakeFailureException(
                'Sec-WebSocket-Accept header missing.'
            );
        }

        if (   !isset($headers['Upgrade'])
            || strtolower($headers['Upgrade']) != 'websocket'
        ) {
            throw new Net_ChaChing_WebSocket_HandshakeFailureException(
                'Upgrade header missing or not set to "websocket".'
            );
        }

        if (   !isset($headers['Connection'])
            || strtolower($headers['Connection']) != 'upgrade'
        ) {
            throw new Net_ChaChing_WebSocket_HandshakeFailureException(
                'Connection header missing or not set to "Upgrade".'
            );
        }

        // Make sure server responded with appropriate Sec-WebSocket-Accept
        // header. Ignore leading and trailing whitespace as per RFC 6455
        // section 4.1 client validation of server response item 4.
        $responseAccept = trim($headers['Sec-WebSocket-Accept']);
        $validAccept = $this->getAccept($nonce);
        if ($responseAccept != $validAccept) {
            throw new Net_ChaChing_WebSocket_HandshakeFailureException(
                sprintf(
                    'Sec-WebSocket-Accept header "%s" does not validate '
                    . 'against nonce "%s"',
                    $responseAccept,
                    $nonce
                )
            );
        }

        return null;
    }

    // {{{ getSupportedProtocol()

    protected function getSupportedProtocol(array $supported, array $requested)
    {
        $supportedProtocol = null;

        foreach ($requested as $protocol) {
            if (in_array($protocol, $supported)) {
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
