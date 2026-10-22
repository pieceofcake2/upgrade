<?php
/**
 * The classes of pieceofcake2/cakephp in the Cake namespace (CakePHP 2.13): replaces the global names of the classes
 * of CakePHP by the namespaced names, with use statements, e.g. `extends Controller` by
 * `use Cake\Controller\Controller;` and `extends Controller`, also in the strings compared with the class of an
 * object, e.g. `get_class($request) === 'CakeRequest'`. The global names keep working, so the change is optional.
 *
 * ```
 * bin/cake upgrade rector --rules namespaced-classes src tests
 * ```
 */

declare(strict_types=1);

use PieceOfCake2\Upgrade\Rector\NamespacedClassNameRector;
use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\Name\RenameClassRector;

$classes = require __DIR__ . '/classes/cakephp.php';

return RectorConfig::configure()
    ->withImportNames(importShortClasses: false, removeUnusedImports: false)
    ->withConfiguredRule(RenameClassRector::class, $classes)
    ->withConfiguredRule(NamespacedClassNameRector::class, $classes);
