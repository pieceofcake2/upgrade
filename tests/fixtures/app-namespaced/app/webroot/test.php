<?php
/**
 * The test runner of the CakePHP 2 layout
 */
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(dirname(dirname(__FILE__))));
define('APP_DIR', basename(dirname(dirname(__FILE__))));
define('SITE_NAME', 'Acme Blog');
require ROOT . DS . 'vendor' . DS . 'autoload.php';
CakeTestSuiteDispatcher::run();
