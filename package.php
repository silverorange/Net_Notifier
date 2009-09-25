<?php

require_once 'PEAR/PackageFileManager2.php';
PEAR::setErrorHandling(PEAR_ERROR_DIE);

$version = '1.1.0';
$notes = <<<EOT
see ChangeLog
EOT;

$description = <<<EOT
ChaChing package
EOT;

$package = new PEAR_PackageFileManager2();

$package->setOptions(
	array(
		'filelistgenerator'           => 'svn',
		'simpleoutput'                => true,
		'baseinstalldir'              => '/',
		'packagedirectory'            => './',
		'dir_roles'                   => array(
			'ChaChing'                => 'php',
			'tests'                   => 'test'
		),
		'exceptions'                  => array(
			'scripts/chaching-server' => 'script'
		),
		'ignore'                      => array(
			'package.php',
			'*.tgz'
		),
		'installexceptions'           => array(
			'scripts/chaching-server' => '/'
		)
	)
);

$package->setPackage('ChaChing');
$package->setSummary('ChaChing client');
$package->setDescription($description);
$package->setChannel('pear.silverorange.com');
$package->setPackageType('php');
$package->setLicense('LGPL', 'http://www.gnu.org/copyleft/lesser.html');

$package->setReleaseVersion($version);
$package->setReleaseStability('stable');
$package->setAPIVersion('1.0.0');
$package->setAPIStability('stable');
$package->setNotes($notes);

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
$package->generateContents();

$package->addRelease();
$pacjage->addInstallAs('scripts/chaching-server', 'chaching-server');

if (   isset($_GET['make'])
	|| (isset($_SERVER['argv']) && @$_SERVER['argv'][1] == 'make')
) {
	$package->writePackageFile();
} else {
	$package->debugPackageFile();
}

?>
