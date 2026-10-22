<?php
/**
 * The console of the CakePHP 2 layout
 */
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
define('CONSOLE_USER', getenv('USER') ?: 'deploy');
require dirname(dirname(__DIR__)) . DS . 'vendor' . DS . 'autoload.php';
return ShellDispatcher::run($argv);
