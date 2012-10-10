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

require_once 'Net/ChaChing/WebSocket/AbstractHandshake.php';

/**
 * A client connection to the cha-ching server
 *
 * This class is intended to be used internally by the
 * {@link ChaChingServerClientConnection} class.
 *
 * Latest update supports RFC 6455.
 *
 * @category  Net
 * @package   ChaChing
 * @copyright 2010-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Net_ChaChing_WebSocket_ServerHandshake
    extends Net_ChaChing_WebSocket_AbstractHandshake
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
        $this->setProtocols($protocols);
    }

    // }}}
    // {{{ handshake()

    /**
     * Does the actual WebSocket handshake
     *
     * See section 4.2.2 of
     * {@link http://datatracker.ietf.org/doc/rfc6455/ IETF RFC 6455} for
     * further details.
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
}

?>
