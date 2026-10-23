<?php
/**
 * The variadic parameters that pieceofcake2/cakephp declares instead of func_get_args(): adds them to the methods of
 * the application that override those of CakePHP.
 *
 * ```
 * bin/cake upgrade rector --rules variadic-arguments src tests
 * ```
 */

declare(strict_types=1);

use PieceOfCake2\Upgrade\Rector\VariadicOverrideRector;
use Rector\Config\RectorConfig;

$methods = [
    'Shell' => ['dispatchShell' => 'args'],
    'AuthComponent' => ['allow' => 'args', 'deny' => 'args'],
    'SecurityComponent' => [
        'requirePost' => 'args',
        'requireGet' => 'args',
        'requirePut' => 'args',
        'requireDelete' => 'args',
        'requireSecure' => 'args',
        'requireAuth' => 'args',
    ],
    'Controller' => ['setAction' => 'args', 'validate' => 'args', 'validateErrors' => 'objects'],
    'ContainableBehavior' => ['contain' => 'args'],
    'DboSource' => ['query' => 'args'],
    'Model' => ['isUnique' => 'args', 'query' => 'params'],
    'CakeRequest' => [
        'data' => 'args',
        'param' => 'args',
        'input' => 'args',
        'allowMethod' => 'args',
        'onlyAllow' => 'args',
    ],
    'Router' => ['parseExtensions' => 'extensions'],
    'CakeTestCase' => ['loadFixtures' => 'args'],
    'Hash' => ['merge' => 'args'],
    'Sanitize' => ['stripTags' => 'tags'],
    'Set' => ['merge' => 'args'],
    'HtmlHelper' => ['css' => 'args', 'useTag' => 'args'],
];
// From pieceofcake2/cakephp 2.13, the global names are aliases of the classes in the Cake namespace, which the
// reflection of Rector names as the parents of the classes of the application
$classes = require __DIR__ . '/classes/cakephp.php';
foreach (array_intersect_key($classes, $methods) as $class => $namespaced) {
    $methods[$namespaced] = $methods[$class];
}

return RectorConfig::configure()
    ->withConfiguredRule(VariadicOverrideRector::class, $methods);
