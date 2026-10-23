<?php
/**
 * The front controller of the CakePHP 2 layout, in the application directory
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
define('CAKE_CORE_INCLUDE_PATH', ROOT . DS . APP_DIR . DS . 'vendor' . DS . 'pieceofcake2' . DS . 'cakephp' . DS . 'lib');

// The settings of the application
define('SHOP_NAME', 'Acme Shop');
define('IMAGES_DIR', ROOT . DS . APP_DIR . DS . 'webroot' . DS . 'img' . DS);
if (getenv('SHOP_DEMO')) {
    define('SHOP_DEMO', true);
}

if (!defined('WEBROOT_DIR')) {
    define('WEBROOT_DIR', basename(dirname(__FILE__)));
}
if (!defined('WWW_ROOT')) {
    define('WWW_ROOT', dirname(__FILE__) . DS);
}
require CAKE_CORE_INCLUDE_PATH . DS . 'Cake' . DS . 'bootstrap.php';

$Dispatcher = new Dispatcher();
$Dispatcher->dispatch(new CakeRequest(), new CakeResponse());
