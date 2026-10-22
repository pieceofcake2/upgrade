<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces the class names in the strings of the settings that name a class of CakePHP 2 by `::class`, e.g.
 * `public $components = ['Auth']` by `public $components = [AuthComponent::class]`.
 *
 * Only the places that take a class name change: `$components`, `$helpers`, `$actsAs`, `$uses`, `$tasks` and the
 * associations of models (their keys, `className` and `with`), `$viewClass`, the Auth objects (`authenticate`,
 * `authorize`, `passwordHasher`), the engines of `Cache::config()`, `CakeLog::config()` and of the helpers, the
 * `datasource` of the connections, the email `transport`, the `Session` handler, `Exception.renderer`, the error and
 * exception handlers (as `[ErrorHandler::class, 'handleError']`), `Dispatcher.filters`, `Acl.classname`,
 * `routeClass`, `viewClassMap`, `Components->load()`, `Helpers->load()`, `Behaviors->load()`, `loadModel()` and
 * `ClassRegistry::init()`. Other strings stay.
 *
 * A name is replaced by the class that CakePHP finds for it now: the class of the application (in `App.namespace`),
 * of the plugin of a `Plugin.Name`, or of CakePHP, in this order. Only namespaced classes are used, as CakePHP reads a
 * class name without a backslash as a short name. A full class name (`'AppExceptionRenderer'`, `'Post'`) is always
 * replaced; a short name, to which CakePHP adds a suffix or a plugin (`'Auth'` for `AuthComponent`), only with the
 * option `shortNames`, which is on by default. A short name is looked up when the application runs, so an
 * application or a plugin can replace the class of CakePHP with a class of the same name; the class name fixes it.
 */
final class ClassNameStringRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * Configuration key: the namespaced names of the classes, by global name (CakePHP, and the classes of the
     * application that become namespaced)
     *
     * @var string
     */
    public const CLASSES = 'classes';

    /**
     * Configuration key: the namespace of the application, App.namespace
     *
     * @var string
     */
    public const APP_NAMESPACE = 'namespace';

    /**
     * Configuration key: whether short names (`'Auth'`) are also replaced, besides full class names
     *
     * @var string
     */
    public const SHORT_NAMES = 'shortNames';

    /**
     * Configuration key: other files and directories whose classes can be named, besides the processed paths
     *
     * @var string
     */
    public const INDEX_PATHS = 'indexPaths';

    /**
     * The packages and suffixes of the kinds of classes, as App::className() takes them
     *
     * @var array<string, array{string, string}>
     */
    private const KINDS = [
        'component' => ['Controller/Component', 'Component'],
        'helper' => ['View/Helper', 'Helper'],
        'behavior' => ['Model/Behavior', 'Behavior'],
        'model' => ['Model', ''],
        'task' => ['Console/Command/Task', 'Task'],
        'cache' => ['Cache/Engine', 'Engine'],
        'log' => ['Log/Engine', 'Log'],
        'utility' => ['Utility', ''],
        'datasource' => ['Model/Datasource', ''],
        'session' => ['Model/Datasource/Session', ''],
        'renderer' => ['Error', ''],
        'filter' => ['Routing/Filter', ''],
        'acl' => ['Controller/Component/Acl', ''],
        'route' => ['Routing/Route', ''],
        'view' => ['View', 'View'],
        'authenticate' => ['Controller/Component/Auth', 'Authenticate'],
        'authorize' => ['Controller/Component/Auth', 'Authorize'],
        'passwordHasher' => ['Controller/Component/Auth', 'PasswordHasher'],
        'transport' => ['Network/Email', 'Transport'],
    ];

    /**
     * The kinds of the properties of controllers, models, shells, components and helpers
     *
     * @var array<string, string>
     */
    private const PROPERTIES = [
        'components' => 'component',
        'helpers' => 'helper',
        'actsAs' => 'behavior',
        'uses' => 'model',
        'tasks' => 'task',
        'belongsTo' => 'model',
        'hasOne' => 'model',
        'hasMany' => 'model',
        'hasAndBelongsToMany' => 'model',
        'viewClass' => 'view',
    ];

    /**
     * The settings of Configure that name a class, by path
     *
     * @var array<string, string>
     */
    private const CONFIGURE = [
        'Exception.renderer' => 'renderer',
        'Session.handler.engine' => 'session',
        'Acl.classname' => 'acl',
    ];

    /**
     * The settings of Configure that are callables, `'Class::method'`
     *
     * @var array<string>
     */
    private const HANDLERS = ['Error.handler', 'Error.consoleHandler', 'Exception.handler', 'Exception.consoleHandler'];

    /**
     * The namespaced names of the classes, by lowercase global name
     *
     * @var array<string, string>
     */
    private array $classes = [];

    /**
     * @var string|null
     */
    private ?string $namespace = null;

    /**
     * @var bool
     */
    private bool $shortNames = true;

    /**
     * @var array<string>
     */
    private array $indexPaths = [];

    /**
     * The known namespaced classes, by lowercase name
     *
     * @var array<string, string>|null
     */
    private ?array $known = null;

    /**
     * The global classes of the analysed code that stay global, by lowercase name
     *
     * @var array<string, true>
     */
    private array $globals = [];

    /**
     * @param array<string, mixed> $configuration `classes`, `namespace`, `shortNames` and `indexPaths`
     * @return void
     */
    public function configure(array $configuration): void
    {
        $this->classes = [];
        foreach ((array)($configuration[self::CLASSES] ?? []) as $global => $namespaced) {
            $this->classes[strtolower((string)$global)] = (string)$namespaced;
        }
        $namespace = $configuration[self::APP_NAMESPACE] ?? null;
        $this->namespace = is_string($namespace) && $namespace !== '' ? trim($namespace, '\\') : null;
        $this->shortNames = (bool)($configuration[self::SHORT_NAMES] ?? true);
        $this->indexPaths = (array)($configuration[self::INDEX_PATHS] ?? []);
        $this->known = null;
    }

    /**
     * @return \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Replace the class names in the settings that name a class by ::class', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
class PostsController extends AppController
{
    public $components = ['Session', 'Auth' => ['authenticate' => ['Form']]];

    public $uses = ['Post'];
}
CODE_SAMPLE,
                <<<'CODE_SAMPLE'
class PostsController extends AppController
{
    public $components = [\Cake\Controller\Component\SessionComponent::class, \Cake\Controller\Component\AuthComponent::class => ['authenticate' => [\Cake\Controller\Component\Auth\FormAuthenticate::class]]];

    public $uses = [\App\Model\Post::class];
}
CODE_SAMPLE,
                [self::CLASSES => ['SessionComponent' => 'Cake\Controller\Component\SessionComponent'], self::APP_NAMESPACE => 'App', self::SHORT_NAMES => true],
            ),
        ]);
    }

    /**
     * @return array<class-string<\PhpParser\Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class, Assign::class, MethodCall::class, StaticCall::class];
    }

    /**
     * @param \PhpParser\Node\Stmt\Class_|\PhpParser\Node\Expr\Assign|\PhpParser\Node\Expr\MethodCall|\PhpParser\Node\Expr\StaticCall $node Node
     * @return \PhpParser\Node|null
     */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Class_) {
            $changed = $this->refactorClass($node);
        } elseif ($node instanceof Assign) {
            $changed = $this->refactorAssign($node);
        } elseif ($node instanceof MethodCall) {
            $changed = $this->refactorMethodCall($node);
        } else {
            $changed = $this->refactorStaticCall($node);
        }

        return $changed ? $node : null;
    }

    /**
     * The properties of a class, and of the configuration classes of the connections and emails
     *
     * @param \PhpParser\Node\Stmt\Class_ $class Class
     * @return bool Whether it changed
     */
    private function refactorClass(Class_ $class): bool
    {
        $changed = false;
        $name = $class->name?->toString();
        foreach ($class->getProperties() as $property) {
            foreach ($property->props as $prop) {
                if ($prop->default === null) {
                    continue;
                }
                $propertyName = $prop->name->toString();
                if (isset(self::PROPERTIES[$propertyName])) {
                    $changed = $this->property($propertyName, $prop->default, static function (Expr $class) use ($prop): void {
                        $prop->default = $class;
                    }) || $changed;
                } elseif ($name === 'DATABASE_CONFIG') {
                    $changed = $this->setting($prop->default, 'datasource', 'datasource') || $changed;
                } elseif ($name === 'EmailConfig') {
                    $changed = $this->setting($prop->default, 'transport', 'transport') || $changed;
                }
            }
        }

        return $changed;
    }

    /**
     * `$this->viewClass = 'Json'`, `$this->helpers[] = 'Text'`, `$this->Auth->authenticate = ['Form']`
     *
     * @param \PhpParser\Node\Expr\Assign $assign Assignment
     * @return bool Whether it changed
     */
    private function refactorAssign(Assign $assign): bool
    {
        $var = $assign->var;
        $append = false;
        if ($var instanceof ArrayDimFetch && $var->dim === null) {
            $var = $var->var;
            $append = true;
        }
        if (!$var instanceof PropertyFetch) {
            return false;
        }
        $name = $this->getName($var->name);
        if ($name === null) {
            return false;
        }
        if (!$append && in_array($name, ['authenticate', 'authorize'], true)) {
            return $this->authObjects($assign->expr, $name);
        }
        if (!isset(self::PROPERTIES[$name])) {
            return false;
        }
        if ($append) {
            return $this->replace($assign->expr, self::PROPERTIES[$name], static function (Expr $class) use ($assign): void {
                $assign->expr = $class;
            });
        }

        return $this->property($name, $assign->expr, static function (Expr $class) use ($assign): void {
            $assign->expr = $class;
        });
    }

    /**
     * `$this->Components->load('Auth', [...])`, `Helpers->load()`, `Behaviors->load()`, `loadModel()`,
     * `viewClassMap()`, `$email->transport('Smtp')`, `$this->Post->bindModel([...])`
     *
     * @param \PhpParser\Node\Expr\MethodCall $call Call
     * @return bool Whether it changed
     */
    private function refactorMethodCall(MethodCall $call): bool
    {
        if ($call->isFirstClassCallable()) {
            return false;
        }
        $args = $call->getArgs();
        $method = $this->getName($call->name);
        $collection = $call->var instanceof PropertyFetch ? $this->getName($call->var->name) : null;
        $collections = ['Components' => 'component', 'Helpers' => 'helper', 'Behaviors' => 'behavior'];
        if (in_array($method, ['load', 'attach'], true) && isset($collections[(string)$collection], $args[0])) {
            return $this->loadObject($args, $collections[(string)$collection]);
        }
        if ($method === 'loadModel' && isset($args[0])) {
            return $this->replace($args[0]->value, 'model', static function (Expr $class) use ($args): void {
                $args[0]->value = $class;
            });
        }
        if ($method === 'viewClassMap' && isset($args[0])) {
            if ($args[0]->value instanceof Array_) {
                return $this->viewClassMap($args[0]->value);
            }

            return isset($args[1]) && $this->replace($args[1]->value, 'view', static function (Expr $class) use ($args): void {
                $args[1]->value = $class;
            });
        }
        if ($method === 'transport' && count($args) === 1) {
            return $this->replace($args[0]->value, 'transport', static function (Expr $class) use ($args): void {
                $args[0]->value = $class;
            });
        }
        if ($method === 'bindModel' && isset($args[0]) && $args[0]->value instanceof Array_) {
            $changed = false;
            foreach ($args[0]->value->items as $item) {
                if ($item->key instanceof String_ && in_array($item->key->value, ['belongsTo', 'hasOne', 'hasMany', 'hasAndBelongsToMany'], true)) {
                    $changed = $this->property($item->key->value, $item->value) || $changed;
                }
            }

            return $changed;
        }

        return false;
    }

    /**
     * `ClassRegistry::init()`, `Cache::config()`, `CakeLog::config()`, `Router::connect()`,
     * `Router::defaultRouteClass()`, `ConnectionManager::create()` and `Configure::write()`
     *
     * @param \PhpParser\Node\Expr\StaticCall $call Call
     * @return bool Whether it changed
     */
    private function refactorStaticCall(StaticCall $call): bool
    {
        if ($call->isFirstClassCallable()) {
            return false;
        }
        $args = $call->getArgs();
        $method = $this->getName($call->name);
        $class = $this->getName($call->class);
        if ($class === null || $method === null) {
            return false;
        }
        $class = substr($class, (int)strrpos('\\' . $class, '\\'));
        $value = static fn(int $index): ?Expr => isset($args[$index]) ? $args[$index]->value : null;

        return match ($class . '::' . $method) {
            'ClassRegistry::init' => $this->registryInit($args[0] ?? null),
            'Cache::config' => $this->setting($value(1), 'engine', 'cache'),
            'CakeLog::config' => $this->setting($value(1), 'engine', 'log'),
            'Router::connect' => $this->setting($value(2), 'routeClass', 'route'),
            'Router::defaultRouteClass' => isset($args[0]) && $this->replace($args[0]->value, 'route', static function (Expr $expr) use ($args): void {
                $args[0]->value = $expr;
            }),
            'ConnectionManager::create' => $this->setting($value(1), 'datasource', 'datasource'),
            'Configure::write' => isset($args[0], $args[1]) && $args[0]->value instanceof String_
                && $this->configureWrite($args[0]->value->value, $args[1]->value, static function (Expr $expr) use ($args): void {
                    $args[1]->value = $expr;
                }),
            default => false,
        };
    }

    /**
     * `ClassRegistry::init('Post')` and `ClassRegistry::init(['class' => 'Post', ...])`
     *
     * @param \PhpParser\Node\Arg|null $arg The first argument
     * @return bool Whether it changed
     */
    private function registryInit(?Arg $arg): bool
    {
        if ($arg === null) {
            return false;
        }
        if ($arg->value instanceof Array_) {
            $changed = $this->setting($arg->value, 'class', 'model');
            foreach ($arg->value->items as $item) {
                if ($item->key === null && $item->value instanceof Array_) {
                    $changed = $this->setting($item->value, 'class', 'model') || $changed;
                }
            }

            return $changed;
        }

        return $this->replace($arg->value, 'model', static function (Expr $class) use ($arg): void {
            $arg->value = $class;
        });
    }

    /**
     * A property, or an array of the same kind: `$components`, `$helpers`, `$actsAs`, `$uses`, `$tasks`, the
     * associations or `$viewClass`
     *
     * @param string $name Name of the property
     * @param \PhpParser\Node\Expr $value The value
     * @param callable(\PhpParser\Node\Expr): void|null $set Sets a new value instead of a string
     * @return bool Whether it changed
     */
    private function property(string $name, Expr $value, ?callable $set = null): bool
    {
        $kind = self::PROPERTIES[$name];
        if ($value instanceof String_) {
            return $set !== null && in_array($name, ['viewClass', 'uses'], true) && $this->replace($value, $kind, $set);
        }
        if (!$value instanceof Array_ || $name === 'viewClass') {
            return false;
        }

        return $this->objects($value, $kind, function (Array_ $settings) use ($name): bool {
            return match ($name) {
                'components' => $this->componentSettings($settings),
                'helpers' => $this->setting($settings, 'engine', 'utility'),
                'hasAndBelongsToMany' => $this->setting($settings, 'with', 'model'),
                default => false,
            };
        });
    }

    /**
     * A list of objects, as CakePHP 2 normalizes it: `['Session', 'Auth' => [...settings...]]`
     *
     * The key of an object with a `className` is its alias and stays, the `className` changes.
     *
     * @param \PhpParser\Node\Expr\Array_ $array The objects
     * @param string $kind The kind of the classes
     * @param callable(\PhpParser\Node\Expr\Array_): bool $settings Changes the settings of an object
     * @return bool Whether it changed
     */
    private function objects(Array_ $array, string $kind, callable $settings): bool
    {
        $changed = false;
        foreach ($array->items as $item) {
            if ($item->unpack) {
                continue;
            }
            if ($item->key === null) {
                $changed = $this->replace($item->value, $kind, static function (Expr $class) use ($item): void {
                    $item->value = $class;
                }) || $changed;
                continue;
            }
            if (!$item->value instanceof Array_) {
                continue;
            }
            if ($this->classNameOf($item->value) !== null) {
                $changed = $this->setting($item->value, 'className', $kind) || $changed;
            } else {
                $changed = $this->replace($item->key, $kind, static function (Expr $class) use ($item): void {
                    $item->key = $class;
                }) || $changed;
            }
            $changed = $settings($item->value) || $changed;
        }

        return $changed;
    }

    /**
     * The Auth objects in the settings of a component
     *
     * @param \PhpParser\Node\Expr\Array_ $settings Settings
     * @return bool Whether it changed
     */
    private function componentSettings(Array_ $settings): bool
    {
        $changed = false;
        foreach ($settings->items as $item) {
            if ($item->key instanceof String_ && in_array($item->key->value, ['authenticate', 'authorize'], true)) {
                $changed = $this->authObjects($item->value, $item->key->value, static function (Expr $expr) use ($item): void {
                    $item->value = $expr;
                }) || $changed;
            } elseif ($item->key instanceof String_ && $item->key->value === 'viewClassMap' && $item->value instanceof Array_) {
                $changed = $this->viewClassMap($item->value) || $changed;
            }
        }

        return $changed;
    }

    /**
     * `Components->load('Auth', [...])`, `Helpers->load()`, `Behaviors->load()` (and `attach()`): the class, or the
     * `className` of the settings, which make the first argument an alias
     *
     * @param array<\PhpParser\Node\Arg> $args The arguments
     * @param string $kind The kind of the object
     * @return bool Whether it changed
     */
    private function loadObject(array $args, string $kind): bool
    {
        $changed = false;
        $settings = isset($args[1]) && $args[1]->value instanceof Array_ ? $args[1]->value : null;
        if ($settings !== null) {
            $changed = $this->setting($settings, 'className', $kind);
            $changed = match ($kind) {
                'component' => $this->componentSettings($settings),
                'helper' => $this->setting($settings, 'engine', 'utility'),
                default => false,
            } || $changed;
        }
        if ($this->classNameOf($settings) === null) {
            $changed = $this->replace($args[0]->value, $kind, static function (Expr $class) use ($args): void {
                $args[0]->value = $class;
            }) || $changed;
        }

        return $changed;
    }

    /**
     * The view classes of RequestHandlerComponent, by type: `['json' => 'MyJson']`
     *
     * @param \PhpParser\Node\Expr\Array_ $map The map
     * @return bool Whether it changed
     */
    private function viewClassMap(Array_ $map): bool
    {
        $changed = false;
        foreach ($map->items as $item) {
            if ($item->key !== null) {
                $changed = $this->replace($item->value, 'view', static function (Expr $class) use ($item): void {
                    $item->value = $class;
                }) || $changed;
            }
        }

        return $changed;
    }

    /**
     * `authenticate` or `authorize` of AuthComponent: `'Controller'`, or `['Form' => [...], 'all' => [...]]`, whose
     * settings may name a `passwordHasher`
     *
     * @param \PhpParser\Node\Expr $value The objects
     * @param string $kind `authenticate` or `authorize`
     * @param callable(\PhpParser\Node\Expr): void|null $set Sets a new value instead of a string
     * @return bool Whether it changed
     */
    private function authObjects(Expr $value, string $kind, ?callable $set = null): bool
    {
        if ($value instanceof String_) {
            return $set !== null && $this->replace($value, $kind, $set);
        }
        if (!$value instanceof Array_) {
            return false;
        }
        $hasher = function (Array_ $settings): bool {
            $changed = false;
            foreach ($settings->items as $item) {
                if (!$item->key instanceof String_ || $item->key->value !== 'passwordHasher') {
                    continue;
                }
                if ($item->value instanceof Array_) {
                    $changed = $this->setting($item->value, 'className', 'passwordHasher') || $changed;
                } else {
                    $changed = $this->replace($item->value, 'passwordHasher', static function (Expr $class) use ($item): void {
                        $item->value = $class;
                    }) || $changed;
                }
            }

            return $changed;
        };
        $changed = false;
        foreach ($value->items as $item) {
            if ($item->key instanceof String_ && $item->key->value === 'all' && $item->value instanceof Array_) {
                $changed = $hasher($item->value) || $changed;
                $item->setAttribute('upgradeAll', true);
            }
        }
        $all = new Array_(array_values(array_filter($value->items, static fn(ArrayItem $item): bool => !$item->getAttribute('upgradeAll'))));

        return $this->objects($all, $kind, $hasher) || $changed;
    }

    /**
     * A setting of an array: `['engine' => 'File']`
     *
     * @param \PhpParser\Node\Expr|null $array The settings
     * @param string $key The key of the setting
     * @param string $kind The kind of the class
     * @return bool Whether it changed
     */
    private function setting(?Expr $array, string $key, string $kind): bool
    {
        if (!$array instanceof Array_) {
            return false;
        }
        $changed = false;
        foreach ($array->items as $item) {
            if ($item->key instanceof String_ && $item->key->value === $key) {
                $changed = $this->replace($item->value, $kind, static function (Expr $class) use ($item): void {
                    $item->value = $class;
                }) || $changed;
            } elseif ($item->value instanceof Array_ && $array !== $item->value && in_array($kind, ['datasource', 'transport'], true)) {
                // The connections and the email configurations of an array of them
                $changed = $this->setting($item->value, $key, $kind) || $changed;
            }
        }

        return $changed;
    }

    /**
     * `Configure::write($path, $value)`, also with the settings of the path in an array
     *
     * @param string $path The path
     * @param \PhpParser\Node\Expr $value The value
     * @param callable(\PhpParser\Node\Expr): void $set Sets a new value
     * @return bool Whether it changed
     */
    private function configureWrite(string $path, Expr $value, callable $set): bool
    {
        if (isset(self::CONFIGURE[$path])) {
            return $this->replace($value, self::CONFIGURE[$path], $set);
        }
        if (in_array($path, self::HANDLERS, true)) {
            return $this->handler($value, $set);
        }
        if (!$value instanceof Array_) {
            return false;
        }
        if ($path === 'Dispatcher.filters') {
            return $this->filters($value);
        }
        $changed = false;
        foreach ($value->items as $item) {
            if ($item->key instanceof String_) {
                $changed = $this->configureWrite($path . '.' . $item->key->value, $item->value, static function (Expr $expr) use ($item): void {
                    $item->value = $expr;
                }) || $changed;
            }
        }

        return $changed;
    }

    /**
     * `Dispatcher.filters`: `['AssetDispatcher', 'MyFilter' => [...settings...], ['callable' => 'MyFilter']]`
     *
     * @param \PhpParser\Node\Expr\Array_ $filters The filters
     * @return bool Whether it changed
     */
    private function filters(Array_ $filters): bool
    {
        $changed = false;
        foreach ($filters->items as $item) {
            if ($item->key === null && $item->value instanceof Array_) {
                $changed = $this->setting($item->value, 'callable', 'filter') || $changed;
            } elseif ($item->key === null) {
                $changed = $this->replace($item->value, 'filter', static function (Expr $class) use ($item): void {
                    $item->value = $class;
                }) || $changed;
            } elseif ($item->value instanceof Array_) {
                $changed = $this->replace($item->key, 'filter', static function (Expr $class) use ($item): void {
                    $item->key = $class;
                }) || $changed;
            }
        }

        return $changed;
    }

    /**
     * An error or exception handler: `'ErrorHandler::handleError'` becomes `[ErrorHandler::class, 'handleError']`
     *
     * @param \PhpParser\Node\Expr $value The handler
     * @param callable(\PhpParser\Node\Expr): void $set Sets a new value
     * @return bool Whether it changed
     */
    private function handler(Expr $value, callable $set): bool
    {
        if ($value instanceof Array_ && count($value->items) === 2 && $value->items[0]->key === null) {
            $item = $value->items[0];

            return $this->replaceGlobal($item->value, static function (Expr $class) use ($item): void {
                $item->value = $class;
            });
        }
        if (!$value instanceof String_ || substr_count($value->value, '::') !== 1) {
            return false;
        }
        [$class, $method] = explode('::', $value->value);
        $classConstant = null;
        $this->replaceGlobal(new String_($class), static function (Expr $expr) use (&$classConstant): void {
            $classConstant = $expr;
        });
        if ($classConstant === null || $method === '') {
            return false;
        }
        $set(new Array_([new ArrayItem($classConstant), new ArrayItem(new String_($method))]));

        return true;
    }

    /**
     * The name of the class in the `className` setting of an object, if any
     *
     * @param \PhpParser\Node\Expr|null $settings The settings
     * @return \PhpParser\Node\Expr|null
     */
    private function classNameOf(?Expr $settings): ?Expr
    {
        if (!$settings instanceof Array_) {
            return null;
        }
        foreach ($settings->items as $item) {
            if ($item->key instanceof String_ && $item->key->value === 'className') {
                return $item->value;
            }
        }

        return null;
    }

    /**
     * Replaces a string with a class name of a kind by `\Namespaced\Name::class`
     *
     * @param \PhpParser\Node\Expr $expr The expression
     * @param string $kind The kind of the class
     * @param callable(\PhpParser\Node\Expr): void $set Sets the new expression
     * @return bool Whether it was replaced
     */
    private function replace(Expr $expr, string $kind, callable $set): bool
    {
        if (!$expr instanceof String_) {
            return false;
        }
        $resolved = $this->resolve($expr->value, $kind);
        if ($resolved === null || ($resolved[1] && !$this->shortNames)) {
            return false;
        }
        $set(new ClassConstFetch(new FullyQualified($resolved[0]), 'class'));

        return true;
    }

    /**
     * Replaces a string with a global class name, which PHP reads as it is, by `\Namespaced\Name::class`
     *
     * @param \PhpParser\Node\Expr $expr The expression
     * @param callable(\PhpParser\Node\Expr): void $set Sets the new expression
     * @return bool Whether it was replaced
     */
    private function replaceGlobal(Expr $expr, callable $set): bool
    {
        if (!$expr instanceof String_) {
            return false;
        }
        $name = ltrim($expr->value, '\\');
        $known = $this->known();
        if (str_contains($name, '\\')) {
            $class = $known[strtolower($name)] ?? null;
        } else {
            $class = isset($this->globals[strtolower($name)]) ? null : ($this->classes[strtolower($name)] ?? null);
        }
        if ($class === null) {
            return false;
        }
        $set(new ClassConstFetch(new FullyQualified($class), 'class'));

        return true;
    }

    /**
     * The namespaced class that CakePHP finds for a name, as App::className() does: in the namespace of the plugin or
     * of the application, then the global class (of CakePHP)
     *
     * @param string $value The name
     * @param string $kind The kind of the class
     * @return array{string, bool}|null The class, and whether the name is a short name
     */
    private function resolve(string $value, string $kind): ?array
    {
        [$type, $suffix] = self::KINDS[$kind];
        $known = $this->known();
        if (str_contains($value, '\\')) {
            $name = ltrim($value, '\\');
            $short = $suffix !== '' && !str_ends_with($name, $suffix);
            $class = $known[strtolower($name . ($short ? $suffix : ''))] ?? null;

            return $class === null ? null : [$class, $short];
        }
        $plugin = null;
        $name = $value;
        if (str_contains($name, '.')) {
            [$plugin, $name] = explode('.', $name, 2);
        }
        $short = $plugin !== null;
        if (str_contains($name, '/')) {
            $type .= '/' . substr($name, 0, (int)strrpos($name, '/'));
            $name = substr($name, (int)strrpos($name, '/') + 1);
            $short = true;
        }
        if ($name === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            return null;
        }
        // CakeLog adds the suffix only when the name does not end with it
        $full = $name . ($kind === 'log' && str_ends_with($name, $suffix) ? '' : $suffix);
        $short = $short || $full !== $name;

        $namespace = $plugin ?? $this->namespace;
        if ($namespace !== null) {
            $class = $known[strtolower($namespace . '\\' . str_replace('/', '\\', $type) . '\\' . $full)] ?? null;
            if ($class !== null) {
                return [$class, $short];
            }
        }
        if ($plugin !== null || isset($this->globals[strtolower($full)])) {
            return null;
        }
        $class = $this->classes[strtolower($full)] ?? null;

        return $class === null ? null : [$class, $short];
    }

    /**
     * The namespaced classes of the analysed code (with their new names) and of the configuration
     *
     * @return array<string, string> The names, by lowercase name
     */
    private function known(): array
    {
        if ($this->known !== null) {
            return $this->known;
        }
        $this->known = [];
        $this->globals = [];
        foreach (ClassIndex::create($this->indexPaths)->classes() as $lower => $class) {
            if (str_contains($class, '\\')) {
                $this->known[$lower] = $class;
            } elseif (isset($this->classes[$lower])) {
                $this->known[strtolower($this->classes[$lower])] = $this->classes[$lower];
            } else {
                $this->globals[$lower] = true;
            }
        }
        foreach ($this->classes as $class) {
            $this->known[strtolower($class)] = $class;
        }

        return $this->known;
    }
}
