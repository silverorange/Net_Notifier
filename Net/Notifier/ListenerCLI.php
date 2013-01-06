<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'Console/CommandLine.php';
require_once 'Net/Notifier/Exception.php';
require_once 'Net/Notifier/LoggerCLI.php';
require_once 'Net/Notifier/Listener.php';

class Net_Notifier_ListenerCLI
{
    public function __invoke()
    {
        $parser = $this->getParser();
        $logger = $this->getLogger($parser);

        try {

            $result = $parser->parse();

            $logger->setVerbosity($result->options['verbose']);

            try {
                $listener = $this->getListener(
                    $result->options,
                    $result->args
                );
                $listener->setLogger($logger);
                $listener->run();
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

    protected function getParser()
    {
        return Console_CommandLine::fromXmlFile($this->getUiXml());
    }

    protected function getLogger(Console_CommandLine $parser)
    {
        return new Net_Notifier_LoggerCLI($parser->outputter);
    }

    protected function getListener(array $options, array $arguments)
    {
        return new Net_Notifier_Listener(
            $arguments['address'],
            $options['timeout']
        );
    }

    protected function getUiXml()
    {
        $dir = '@data-dir@' . DIRECTORY_SEPARATOR
            . '@package-name@' . DIRECTORY_SEPARATOR . 'data';

        if ($dir[0] == '@') {
            $dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..'
                . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data';
        }

        return $dir . DIRECTORY_SEPARATOR . 'listener-cli.xml';
    }
}

?>
