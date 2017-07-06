<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Server socket class definition
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
 * Socket used by servers to listen for incomming connections
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2012-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      https://github.com/silverorange/Net_Notifier
 */
class Net_Notifier_Socket_Server
    extends Net_Notifier_Socket_Abstract
{
    // {{{ __construct()

    /**
     * Creates a new server socket
     *
     * @param string  $address    Address for stream_socket_server() call,
     *                            e.g. 'tcp://localhost:80'.
     * @param integer $timeout    the connection timeout in seconds.
     * @param array   $sslOptions SSL context options.
     *
     * @throws Net_Notifier_Socket_ConnectionException
     */
    public function __construct($address, $timeout, array $sslOptions = array())
    {
        $context = stream_context_create();
        foreach ($sslOptions as $name => $value) {
            if (!stream_context_set_option($context, 'ssl', $name, $value)) {
                throw new Net_Notifier_Socket_ConnectionException(
                    "Error setting SSL context option '{$name}'"
                );
            }
        }

        set_error_handler(array($this, 'connectionWarningsHandler'));

        $this->socket = stream_socket_server(
            $address,
            $errstr,
            $errno,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );

        restore_error_handler();

        if (!$this->socket) {
            $error = ($errstr == '')
                ? implode("\n", $this->connectionWarnings)
                : $errstr;

            throw new Net_Notifier_Socket_ConnectionException(
                "Unable to start server {$address}. Error: {$error}",
                0,
                $errno
            );
        }
    }

    // }}}
}

?>
