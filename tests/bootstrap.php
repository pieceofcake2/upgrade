<?php
/**
 * Bootstrap for PHPUnit
 */

use Composer\InstalledVersions;

define('DS', DIRECTORY_SEPARATOR);
define('VENDORS', dirname(__DIR__) . DS . 'vendor' . DS);
define('ROOT', VENDORS . 'pieceofcake2' . DS . 'app');

require_once InstalledVersions::getInstallPath('pieceofcake2/cakephp') . DS . 'tests' . DS . 'bootstrap.php';

App::uses('CakePlugin', 'Core');
CakePlugin::load('Upgrade', [
    'path' => dirname(__DIR__) . DS,
]);
