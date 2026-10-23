<?php
/**
 * The console of the CakePHP 2 layout, in the application directory
 */
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
define('SHOP_CURRENCY', 'EUR');
require dirname(__DIR__) . DS . 'vendor' . DS . 'autoload.php';
return ShellDispatcher::run($argv);
