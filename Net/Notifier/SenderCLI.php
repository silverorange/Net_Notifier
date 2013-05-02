<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Sender command line interface
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
 * @copyright 2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      https://github.com/silverorange/Net_Notifier
 */

/**
 * Command line interface parser class.
 */
require_once 'Console/CommandLine.php';

/**
 * Base exception class.
 */
require_once 'Net/Notifier/Exception.php';

/**
 * Logger implementation class.
 */
require_once 'Net/Notifier/LoggerCLI.php';

/**
 * Sender implementation.
 */
require_once 'Net/Notifier/Sender.php';

/**
 * Sender command line interface
 *
 * Provides a CLI for sending arbitrary notifications.
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      https://github.com/silverorange/Net_Notifier
 */
class Net_Notifier_SenderCLI
{
    // {{{ __invoke()

    /**
     * Runs this CLI sender
     *
     * @return void
     */
    public function __invoke()
    {
        $parser = $this->getParser();
        $logger = $this->getLogger($parser);

        try {

            $result = $parser->parse();

            $logger->setVerbosity($result->options['verbose']);

            try {
                $sender = $this->getListener($result->options, $result->args);
                $sender->setLogger($logger);
                if (isset($result->args['data']) && $result->args['data'] != '') {
                    $data = json_decode($result->args['data'], true);
                    if ($data === null) {
                        $logger->log(
                            'Data must be valid JSON object.' . PHP_EOL,
                            Net_Notifier_Logger::VERBOSITY_ERRORS
                        );
                        exit(1);
                    }
                } else {
                    $data = array();
                }
                $sender>send($result->args['action'], $data);
            } catch (Net_Notifier_Exception $e) {
                $logger->log(
                    $e->getMessage() . PHP_EOL,
                    Net_Notifier_Logger::VERBOSITY_ERRORS
                );

                exit(1);
            }

        } catch (Console_CommandLine_Exception $e) {
            $logger->log(
                $e->getMessage() . PHP_EOL,
                Net_Notifier_Logger::VERBOSITY_ERRORS
            );

            exit(1);
        } catch (Exception $e) {
            $logger->log(
                $e->getMessage() . PHP_EOL,
                Net_Notifier_Logger::VERBOSITY_ERRORS
            );

            $logger->log(
                $e->getTraceAsString() . PHP_EOL,
                Net_Notifier_Logger::VERBOSITY_ERRORS
            );

            exit(1);
        }
    }

    // }}}
    // {{{ getParser()

    /**
     * Gets the command line parser for this sender
     *
     * @return Console_CommandLine the command line parser for this sender.
     */
    protected function getParser()
    {
        return Console_CommandLine::fromXmlFile($this->getUiXml());
    }

    // }}}
    // {{{ getLogger()

    /**
     * Gets the logger for this sender
     *
     * Subclasses can and should override this method to provide a custom
     * logger.
     *
     * @param Console_CommandLine $parser the command line parser for this
     *                                    CLI.
     *
     * @return Net_Notifier_Logger the logger for this sender.
     */
    protected function getLogger(Console_CommandLine $parser)
    {
        return new Net_Notifier_LoggerCLI($parser->outputter);
    }

    // }}}
    // {{{ getListener()

    /**
     * Gets the sender used by this CLI
     *
     * @param array $options   an indexed array of command line options.
     * @param array $arguments an indexed array of command line arguments.
     *
     * @return Net_Notifier_Sender the sender used by this CLI.
     */
    protected function getSender(array $options, array $arguments)
    {
        return new Net_Notifier_Sender(
            $arguments['address'],
            $options['timeout']
        );
    }

    // }}}
    // {{{ getUiXml()

    /**
     * Gets the XML command line interface definition for this sender
     *
     * @return string the XML command line interface definition for this
     *                sender.
     */
    protected function getUiXml()
    {
        $dir = '@data-dir@' . DIRECTORY_SEPARATOR
            . '@package-name@' . DIRECTORY_SEPARATOR . 'data';

        if ($dir[0] == '@') {
            $dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..'
                . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data';
        }

        return $dir . DIRECTORY_SEPARATOR . 'sender-cli.xml';
    }

    // }}}
}

?>
