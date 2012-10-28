<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * WebSocket handshake parser
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
 * @copyright 2010-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

/**
 * Exception class for unsupported requested sub-protocol
 */
require_once 'Net/Notifier/WebSocket/ProtocolException.php';

/**
 * Exception class for failing the WebSocket connection
 */
require_once 'Net/Notifier/WebSocket/HandshakeFailureException.php';

/**
 * Latest update supports RFC 6455.
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2010-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       Net_Notifier_WebSocket_Connection
 */
class Net_Notifier_WebSocket_Handshake
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
    // {{{ start

    /**
     * Initiates a WebSocket handshake
     *
     * @param string  $host      the server host name or IP address.
     * @param integer $port      the server connection port.
     * @param string  $nonce     the nonce value used to validate this
     *                           handshake.
     * @param string  $resource  optional. The WebSocket resource name.
     * @param array   $protocols optional. A list of requested application-
     *                           specific sub-protocols. If this array is
     *                           specified, only handshake requests for the
     *                           specified protocols will succeed.
     *
     * @return string the HTTP GET request data for initiating a WebSocket
     *                handshake for a WebSocket client.
     */
    public function start(
        $host,
        $port,
        $nonce,
        $resource = '/',
        array $protocols = array()
    ) {
        $version = Net_Notifier_WebSocket_Handshake::VERSION;

        $request
            = "GET " . $resource . " HTTP/1.1\r\n"
            . "Host: " . $host . "\r\n"
            . "Connection: Upgrade\r\n"
            . "Upgrade: websocket\r\n"
            . "Sec-WebSocket-Key: " . $nonce . "\r\n"
            . "Sec-WebSocket-Version: " . $version . "\r\n";

        if (count($protocols) > 0) {
            $protocols = implode(',', $protocols);
            $request .= "Sec-WebSocket-Protocol: " . $protocols . "\r\n";
        }

        $request .= "\r\n";

        return $request;
    }

    // }}}
    // {{{ receive()

    /**
     * Does the actual WebSocket handshake
     *
     * See section 4.2.2 of
     * {@link http://datatracker.ietf.org/doc/rfc6455/ IETF RFC 6455} for
     * further details.
     *
     * @param string $data      the handshake request/response data.
     * @param string $nonce     the nonce value used to validate this handshake.
     *                          Not set for receiving client handshake requests.
     * @param array  $protocols optional. A list of supported or requested
     *                          application-specific sub-protocols. If
     *                          specified, only handshake requests for the
     *                          specified protocols will succeed.
     *
     * @return string|null the handshake response or null if there is no
     *                     response.
     *
     * @throws Net_Notifier_WebSocket_HandshakeFailureException if the
     *         handshake fails.
     *
     * @todo Handle 4XX responses from server properly on client.
     */
    public function receive($data, $nonce, array $protocols = array())
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
            $response = $this->receiveServerHandshake(
                $headers,
                $nonce,
                $protocols
            );
        } elseif ($method === 'GET') {
            $response = $this->receiveClientHandshake($headers, $protocols);
        } else {
            $response = "HTTP/1.1 400 Bad Request\r\n\r\n";
        }

        return $response;
    }

    // }}}
    // {{{ receiveClientHandshake()

    /**
     * Receives and validates a client handshake request and returns an
     * appropriate server response
     *
     * @param array $headers            the parsed request headers from the
     *                                  client.
     * @param array $supportedProtocols optional. A list of sub-protocols
     *                                  supported by the server.
     *
     * @return string an appropriate HTTP response for the client handshake
     *                request. If there were errors in the client request,
     *                an appropriate HTTP 4XX response is returned. If the
     *                handshake response was successful, a HTTP 101 is returned.
     */
    protected function receiveClientHandshake(
        array $headers,
        array $supportedProtocols
    ) {
        if (!isset($headers['Host'])) {

            $response
                = "HTTP/1.1 400 Bad Request\r\n"
                . "X-WebSocket-Message: Client request Host header is "
                . "missing.\r\n";

        } elseif (   !isset($headers['Upgrade'])
                  || strtolower($headers['Upgrade']) != 'websocket'
        ) {

            $response
                = "HTTP/1.1 400 Bad Request\r\n"
                . "X-WebSocket-Message: Client request Upgrade header is "
                . "missing or not set to 'websocket'.\r\n";

        } elseif (   !isset($headers['Connection'])
                  || strtolower($headers['Connection']) != 'upgrade'
        ) {

            $response
                = "HTTP/1.1 400 Bad Request\r\n"
                . "X-WebSocket-Message: Client request Connection header is "
                . "missing or not set to 'Upgrade'.\r\n";

        } elseif (!isset($headers['Sec-WebSocket-Key'])) {

            $response
                = "HTTP/1.1 400 Bad Request\r\n"
                . "X-WebSocket-Message: Client request Sec-WebSocket-Key "
                . "header is missing.\r\n";

        } elseif (!isset($headers['Sec-WebSocket-Version'])) {

            $response
                = "HTTP/1.1 400 Bad Request\r\n"
                . "X-WebSocket-Message: Client request Sec-WebSocket-Version "
                . "header is missing.\r\n";

        } elseif ($headers['Sec-WebSocket-Version'] != self::VERSION) {

            $response
                = "HTTP/1.1 426 Upgrade Required\r\n"
                . "Sec-WebSocket-Version: " . self::VERSION . "\r\n"
                . "X-WebSocket-Message: Client request protocol version is "
                . "unsupported.\r\n";

        } else {

            $key     = $headers['Sec-WebSocket-Key'];
            $accept  = $this->getAccept($key);
            $version = $headers['Sec-WebSocket-Version'];

            $response
                = "HTTP/1.1 101 Switching Protocols\r\n"
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

                // If no protocol is agreed upon, don't send a protocol header
                // in the response. It it up to the client to fail the
                // connection if no protocols are agreed upon. See RFC 6455
                // Section 4.1 server response validation item 6 and Section
                // 4.2.2 /subprotocol/.
                if ($supportedProtocol !== null) {
                    $response
                        .= 'Sec-WebSocket-Protocol: '
                        .  $supportedProtocol . "\r\n";
                }
            }

        }

        $response .= "\r\n";

        return $response;
    }

    // }}}
    // {{{ receiveServerHandshake()

    /**
     * Receives and validates a server handshake response
     *
     * @param array  $headers   the parsed response headers from the server.
     * @param string $nonce     the nonce value used to validate this handshake.
     * @param array  $protocols optional. A list of requested application-
     *                          specific sub-protocols. If the server handshake
     *                          response does not contain one of the requested
     *                          protocols, this handshake will fail.
     *
     * @return null
     *
     * @throws Net_Notifier_WebSocket_HandshakeFailureException if the handshake
     *         response is invalid according to RFC 6455.
     */
    protected function receiveServerHandshake(
        array $headers,
        $nonce,
        array $protocols = array()
    ) {
        // Make sure required headers and values are present as per RFC 6455
        // section 4.1 client validation of server response.
        if (!isset($headers['Sec-WebSocket-Accept'])) {
            throw new Net_Notifier_WebSocket_HandshakeFailureException(
                'Sec-WebSocket-Accept header missing.'
            );
        }

        if (   !isset($headers['Upgrade'])
            || strtolower($headers['Upgrade']) != 'websocket'
        ) {
            throw new Net_Notifier_WebSocket_HandshakeFailureException(
                'Upgrade header missing or not set to "websocket".'
            );
        }

        if (   !isset($headers['Connection'])
            || strtolower($headers['Connection']) != 'upgrade'
        ) {
            throw new Net_Notifier_WebSocket_HandshakeFailureException(
                'Connection header missing or not set to "Upgrade".'
            );
        }

        // Make sure server responded with appropriate Sec-WebSocket-Accept
        // header. Ignore leading and trailing whitespace as per RFC 6455
        // section 4.1 client validation of server response item 4.
        $responseAccept = trim($headers['Sec-WebSocket-Accept']);
        $validAccept = $this->getAccept($nonce);
        if ($responseAccept != $validAccept) {
            throw new Net_Notifier_WebSocket_HandshakeFailureException(
                sprintf(
                    'Sec-WebSocket-Accept header "%s" does not validate '
                    . 'against nonce "%s"',
                    $responseAccept,
                    $nonce
                )
            );
        }

        // If specific subprotocols were requested, verify the server supports
        // them. See RFC 6455 Section 4.1 server response validation item 6.
        if (count($protocols) > 0) {
            if (!isset($headers['Sec-WebSocket-Protocol'])) {
                throw new Net_Notifier_WebSocket_ProtocolException(
                    sprintf(
                        "Client requested '%s' sub-protocols but server does "
                        . "not support any of them.",
                        implode(' ', $protocols)
                    ),
                    0,
                    null,
                    $protocols
                );
            }

            if (!in_array($headers['Sec-WebSocket-Protocol'], $protocols)) {
                throw new Net_Notifier_WebSocket_ProtocolException(
                    sprintf(
                        "Client requested '%s' sub-protocols. Server "
                        . "responded with unsupported sub-protocol: '%s'.",
                        implode(' ', $protocols),
                        $headers['Sec-WebSocket-Protocol']
                    ),
                    0,
                    $headers['Sec-WebSocket-Protocol'],
                    $protocols
                );
            }
        }

        return null;
    }

    // }}}
    // {{{ getSupportedProtocol()

    /**
     * Gets the first supported protocol from a list of requested protocols
     *
     * @param array $supported the list of supported protocols.
     * @param array $requested the list of requested protocols.
     *
     * @return string|null the first matching requested protocol in the list
     *                     of supported protocols or null if no such protocol
     *                     exists.
     */
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
     * See section 4.2.2 item 5 subitem 4 of RFC 6455 for further details.
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
