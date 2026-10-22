<?php
/**
 * The front controller of the CakePHP 2 layout
 */
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
if (!defined('ROOT')) {
    define('ROOT', dirname(dirname(dirname(__FILE__))));
}
if (!defined('APP_DIR')) {
    define('APP_DIR', basename(dirname(dirname(__FILE__))));
}
define('CAKE_CORE_INCLUDE_PATH', ROOT . DS . 'vendor' . DS . 'pieceofcake2' . DS . 'cakephp' . DS . 'lib');

// The settings of the application
define('SITE_NAME', 'Acme Blog');
define('UPLOADS', dirname(__FILE__) . DS . 'files' . DS);

if (!defined('WEBROOT_DIR')) {
    define('WEBROOT_DIR', basename(dirname(__FILE__)));
}
if (!defined('WWW_ROOT')) {
    define('WWW_ROOT', dirname(__FILE__) . DS);
}
require CAKE_CORE_INCLUDE_PATH . DS . 'Cake' . DS . 'bootstrap.php';

$Dispatcher = new Dispatcher();
$Dispatcher->dispatch(new CakeRequest(), new CakeResponse());
