<?php
/**
 * This file is loaded automatically by webroot/index.php after core.php
 */

Cache::config('default', ['engine' => 'File']);

Configure::write('Dispatcher.filters', [
    'AssetDispatcher',
    'CacheDispatcher',
]);

CakeLog::config('error', [
    'engine' => 'File',
    'types' => ['warning', 'error', 'critical', 'alert', 'emergency'],
    'file' => 'error',
]);

CakePlugin::load(['Tags', 'Admin']);
