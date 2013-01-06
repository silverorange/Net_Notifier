<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'Net/Notifier/Logger.php';

interface Net_Notifier_Loggable
{
    // {{{ setLogger()

    /**
     * Sets the logger for this loggable object
     *
     * Loggers receive status messages and debug output and can store or
     * display received messages.
     *
     * @param Net_Notifier_Logger|null $logger the logger to set for this
     *                                         server, or null to unset the
     *                                         logger.
     *
     * @return Net_Notifier_Loggable the current object, for fluent interface.
     */
    public function setLogger(Net_Notifier_Logger $logger = null);

    // }}}
}
