<?php
/**
 * The paths of the application, without app/: the application directory is the root of the project
 */
if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__, 2));
}
if (!defined('APP_DIR')) {
    define('APP_DIR', basename(dirname(__DIR__)));
}
