<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Client class.
 */
require_once 'Net/Notifier/Client.php';

/**
 * A simple notification listener client
 *
 * May be extended to provide additional functionality.
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Net_Notifier_Listener extends Net_Notifier_Client
{
    // {{{ class constants

    /**
     * How long the read buffer for client connections is.
     *
     * If this is too short, multiple read calls will be made on client
     * connections to receive messages.
     */
    const READ_BUFFER_LENGTH = 2048;

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
     * The level of verbosity to use
     *
     * @var integer
     *
     * @see Net_Notifier_Listener::setVerbosity()
     * @see Net_Notifier_Listener::VERBOSITY_NONE
     * @see Net_Notifier_Listener::VERBOSITY_ERRORS
     * @see Net_Notifier_Listener::VERBOSITY_MESSAGES
     * @see Net_Notifier_Listener::VERBOSITY_CLIENT
     * @see Net_Notifier_Listener::VERBOSITY_ALL
     */
    protected $verbosity = 0;

    // }}}
    // {{{ setVerbosity()

    /**
     * Sets the level of verbosity to use
     *
     * @param integer $verbosity the level of verbosity to use.
     *
     * @return void
     *
     * @see Net_Notifier_Listener::VERBOSITY_NONE
     * @see Net_Notifier_Listener::VERBOSITY_ERRORS
     * @see Net_Notifier_Listener::VERBOSITY_MESSAGES
     * @see Net_Notifier_Listener::VERBOSITY_CLIENT
     * @see Net_Notifier_Listener::VERBOSITY_ALL
     */
    public function setVerbosity($verbosity)
    {
        $this->verbosity = (integer)$verbosity;
    }

    // }}}
    // {{{ run()

    /**
     * Runs this listen client
     *
     * This client listens for messages relayed from the server.
     *
     * @return void
     */
    public function run()
    {
        $this->connect();

        // tell server we want to listen
        $this->connection->writeText('{ "action" : "listen" }');

        while (true) {

            $read = array($this->connection->getSocket()->getRawSocket());

            $result = stream_select(
                $read,
                $write = null,
                $except = null,
                null
            );

            if ($result < 1) {
                continue;
            }

            $moribund = false;

            // check if server closed connection
            $bytes = $this->connection->getSocket()->peek(32);

            if (mb_strlen($bytes, '8bit') === 0) {
                $this->output(
                    "server closed connection.\n",
                    self::VERBOSITY_CLIENT
                );

                $moribund = true;
            }

            try {

                if ($this->connection->read(self::READ_BUFFER_LENGTH)) {

                     if ($this->connection->getState() < Net_Notifier_WebSocket_Connection::STATE_CLOSING) {

                        $messages = $this->connection->getTextMessages();
                        foreach ($messages as $message) {
                            if (mb_strlen($message, '8bit') > 0) {
                                $this->output(
                                    sprintf(
                                        "received message: '%s'\n",
                                        $message
                                    ),
                                    self::VERBOSITY_MESSAGES
                                );
                                $this->handleMessage($message);
                            }
                        }
                    }

                } else {

                    $this->output(
                        "got a message chunk from server\n",
                        self::VERBOSITY_CLIENT
                    );

                }

            } catch (Net_Notifier_WebSocket_HandshakeFailureException $e) {
                $this->output(
                    sprintf(
                        "failed server handshake: %s\n",
                        $e->getMessage()
                    ),
                    self::VERBOSITY_CLIENT
                );
            }

            if ($moribund) {
                $this->connection->close();
                break;
            }

        }

        if ($this->connection->getState() < Net_Notifier_WebSocket_Connection::STATE_CLOSING) {
            $this->disconnect();
        }
    }

    // }}}
    // {{{ protected function handleMessage()

    protected function handleMessage($message)
    {
    }

    // }}}
    // {{{ output()

    /**
     * Displays a debug string based on the verbosity level
     *
     * @param string  $string    the string to display.
     * @param integer $verbosity an optional verbosity level to display at. By
     *                           default, this is 1.
     * @param boolean $timestamp optional. Whether or not to include a
     *                           timestamp with the output.
     *
     * @return void
     */
    protected function output($string, $verbosity = 1, $timestamp = true)
    {
        if ($verbosity <= $this->verbosity) {
            if ($timestamp) {
                echo '[' . date('Y-m-d H:i:s') . '] ' . $string;
            } else {
                echo $string;
            }
        }
    }

    // }}}
}

?>
