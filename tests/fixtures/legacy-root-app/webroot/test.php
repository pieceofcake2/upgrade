<?php
/**
 * The test runner of the CakePHP 2 layout, in the application directory
 */
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(dirname(dirname(__FILE__))));
define('APP_DIR', basename(dirname(dirname(__FILE__))));
require ROOT . DS . APP_DIR . DS . 'vendor' . DS . 'autoload.php';
CakeTestSuiteDispatcher::run();
