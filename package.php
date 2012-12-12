<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * This is the package.xml generator for Net_Notifier
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
 * @author    Nathan Fredrikson <nathan@silverorange.com>
 * @copyright 2006-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

require_once 'PEAR/PackageFileManager2.php';
PEAR::setErrorHandling(PEAR_ERROR_DIE);

$apiVersion     = '0.1.2';
$apiState       = 'alpha';

$releaseVersion = '0.1.2';
$releaseState   = 'alpha';

$releaseNotes   = <<<EOT
* First release.
EOT;

$description = <<<EOT
All connections are made using WebSockets. The notification system works as
follows:

Server:
=======
Runs on a single system and receives requests. The server relays received
messages to connected listening clients.

Listen Client:
================
Runs on one or many machines. Clients connect to a server and listen for
messages relayed from the server.

Send Client:
============
Sends a message to the notification server to be relayed to connected listen
clients.
EOT;

$package = new PEAR_PackageFileManager2();

$package->setOptions(
    array(
        'filelistgenerator'                   => 'svn',
        'simpleoutput'                        => true,
        'baseinstalldir'                      => '/',
        'packagedirectory'                    => './',
        'dir_roles'                           => array(
            'Net'                             => 'php',
            'Net/Notifier/'                   => 'php',
            'Net/Notifier/Socket'             => 'php',
            'Net/Notifier/WebSocket'          => 'php',
            'tests'                           => 'test'
        ),
        'exceptions'                          => array(
            'scripts/net-notifier-server' => 'script'
        ),
        'ignore'                              => array(
            'package.php',
            '*.tgz'
        ),
        'installexceptions'                   => array(
            'scripts/net-notifier-server' => '/'
        )
    )
);

$package->setPackage('Net_Notifier');
$package->setSummary('WebSocket relay notification system.');
$package->setDescription($description);
$package->setChannel('pear.silverorange.com');
$package->setPackageType('php');
$package->setLicense('LGPL', 'http://www.gnu.org/copyleft/lesser.html');

$package->setReleaseVersion($releaseVersion);
$package->setReleaseStability($releaseState);
$package->setAPIVersion($apiVersion);
$package->setAPIStability($apiState);
$package->setNotes($releaseNotes);

$package->addMaintainer(
    'lead',
    'gauthierm',
    'Mike Gauthier',
    'mike@silverorange.com'
);

$package->addMaintainer(
    'lead',
    'nrf',
    'Nathan Fredrickson',
    'nrf@silverorange.com'
);
$package->setPhpDep('5.2.1');
$package->setPearinstallerDep('1.4.0');
$package->addExtensionDep('required', 'mbstring');
$package->addPackageDepWithChannel(
    'required',
    'Console_CommandLine',
    'pear.php.net',
    '1.1.10'
);
$package->generateContents();

$package->addRelease();
$package->addInstallAs(
    'scripts/net-notifier-server',
    'net-notifier-server'
);

if (   isset($_GET['make'])
    || (isset($_SERVER['argv']) && @$_SERVER['argv'][1] == 'make')
) {
    $package->writePackageFile();
} else {
    $package->debugPackageFile();
}

?>
