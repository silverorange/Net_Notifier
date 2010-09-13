<?php

/**
 * This is the package.xml generator for ChaChing
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
 * @author    Nathan Fredrikson <nathan@silverorange.com>
 * @copyright 2006-2010 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

require_once 'PEAR/PackageFileManager2.php';
PEAR::setErrorHandling(PEAR_ERROR_DIE);

$apiVersion     = '1.2.0';
$apiState       = 'stable';

$releaseVersion = '1.2.0';
$releaseState   = 'stable';

$releaseNotes   = <<<EOT
* Added WebSocket client support.
EOT;

$description = <<<EOT
The cha-ching system works as follows:

Server:
=======
Runs on a single system and receives requests. The server propegates
cha-ching pings to connected playback clients.

Playback Client:
================
Runs on one or many machines. Clients connect to a server and play noises
that are pushed from the server. Clients may be regular socket clients or
WebSocket clients.

Ping Client:
============
Notifies the cha-ching server to push a cha-ching to connected playback
clients.
EOT;

$package = new PEAR_PackageFileManager2();

$package->setOptions(
	array(
		'filelistgenerator'            => 'svn',
		'simpleoutput'                 => true,
		'baseinstalldir'               => '/',
		'packagedirectory'             => './',
		'dir_roles'                    => array(
			'ChaChing'                 => 'php',
			'tests'                    => 'test'
		),
		'exceptions'                   => array(
			'scripts/cha-ching-server' => 'script'
		),
		'ignore'                       => array(
			'package.php',
			'*.tgz'
		),
		'installexceptions'            => array(
			'scripts/cha-ching-server' => '/'
		)
	)
);

$package->setPackage('ChaChing');
$package->setSummary('Cha-ching notification system.');
$package->setDescription($description);
$package->setChannel('pear.silverorange.com');
$package->setPackageType('php');
$package->setLicense('LGPL', 'http://www.gnu.org/copyleft/lesser.html');

$package->setReleaseVersion($releaseVersion);
$package->setReleaseStability($relaseState);
$package->setAPIVersion($apiVersion);
$package->setAPIStability($apiState);
$package->setNotes($releaseNotes);

$package->addMaintainer(
	'lead',
	'nrf',
	'Nathan Fredrickson',
	'nathan@silverorange.com'
);

$package->addMaintainer(
	'lead',
	'gauthierm',
	'Mike Gauthier',
	'mike@silverorange.com'
);

$package->setPhpDep('5.1.5');
$package->setPearinstallerDep('1.4.0');
$package->addExtensionDep('required', 'sockets');
$package->addExtensionDep('required', 'mbstring');
$package->generateContents();

$package->addRelease();
$package->addInstallAs('scripts/cha-ching-server', 'cha-ching-server');

if (   isset($_GET['make'])
	|| (isset($_SERVER['argv']) && @$_SERVER['argv'][1] == 'make')
) {
	$package->writePackageFile();
} else {
	$package->debugPackageFile();
}

?>
