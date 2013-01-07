<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * WebSocket notification sender class
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
 * Client class.
 */
require_once 'Net/Notifier/Client.php';

/**
 * A client for sending notifications
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2012-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      https://github.com/silverorange/Net_Notifier
 */
class Net_Notifier_Sender extends Net_Notifier_Client
{
    // {{{ send()

    /**
     * Connects to the WebSocket server, sends a text message and disconnects
     *
     * @param string $action the notification action.
     * @param array  $data   optional. The notification data.
     *
     * @return Net_Notifier_Sender the current object, for fluent interface.
     *
     * @throws Net_Notifier_ClientException if there is an error connecting
     *         to the notification server or sending the message.
     */
    public function send($action, array $data = array())
    {
        $message = array('action' => $action);

        if (count($data) > 0) {
            $message['data'] = $data;
        }

        $message = json_encode($message);

        $this->connect();
        $this->connection->writeText($message);
        $this->disconnect();

        return $this;
    }

    // }}}
}

?>
