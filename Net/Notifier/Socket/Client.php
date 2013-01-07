<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Client connection socket class definition
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
 * This file is adapted from HTTP_Request2_SocketWrapper, originally licensed
 * as follows:
 *
 *
 *     Copyright (c) 2008-2012, Alexey Borzov <avb@php.net>
 *
 *     All rights reserved.
 *
 *     Redistribution and use in source and binary forms, with or without
 *     modification, are permitted provided that the following conditions
 *     are met:
 *
 *        * Redistributions of source code must retain the above copyright
 *          notice, this list of conditions and the following disclaimer.
 *        * Redistributions in binary form must reproduce the above copyright
 *          notice, this list of conditions and the following disclaimer in the
 *          documentation and/or other materials provided with the distribution.
 *        * The names of the authors may not be used to endorse or promote products
 *          derived from this software without specific prior written permission.
 *
 *     THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS
 *     IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO,
 *     THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *     PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
 *     CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 *     EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 *     PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 *     PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
 *     OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 *     NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 *     SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Alexey Borzov <avb@php.net>
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2008-2013 Alexy Borzov, 2012-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      https://github.com/silverorange/Net_Notifier
 */

/**
 * Base socket class.
 */
require_once 'Net/Notifier/Socket/Abstract.php';

/**
 * Socket used for clients connecting to servers
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Alexey Borzov <avb@php.net>
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2008-2012 Alexy Borzov, 2012-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      https://github.com/silverorange/Net_Notifier
 */
class Net_Notifier_Socket_Client
    extends Net_Notifier_Socket_Abstract
{
    // {{{ __construct()

    /**
     * Class constructor, tries to establish connection
     *
     * @param string  $address    Address for stream_socket_client() call,
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

        $this->socket = stream_socket_client(
            $address,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        restore_error_handler();

        if (!$this->socket) {
            $error = ($errstr == '')
                ? implode("\n", $this->connectionWarnings)
                : $errstr;

            throw new Net_Notifier_Socket_ConnectionException(
                "Unable to connect to {$address}. Error: {$error}",
                0,
                $errno
            );
        }
    }

    // }}}
}

?>
