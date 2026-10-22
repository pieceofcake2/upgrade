<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces the global class names in the strings that PHP itself reads as class names by `::class`.
 *
 * PHP does not know the short names of CakePHP, so a string passed to `class_exists()`, `is_a()`, `new
 * ReflectionClass()`, a callable `['Class', 'method']` or `'Class::method'`, or to the mocks and assertions of PHPUnit
 * names the class itself. Once the class is namespaced, the global name no longer exists (the global names of
 * CakePHP still work, as aliases), so the string becomes `\Namespaced\Name::class`, which Rector imports.
 */
final class ClassNameArgumentRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * The arguments that name a class, by lowercase name of the function
     *
     * @var array<string, int>
     */
    private const FUNCTIONS = [
        'class_exists' => 0,
        'interface_exists' => 0,
        'trait_exists' => 0,
        'enum_exists' => 0,
        'get_class_methods' => 0,
        'get_class_vars' => 0,
        'get_parent_class' => 0,
        'method_exists' => 0,
        'property_exists' => 0,
        'class_implements' => 0,
        'class_parents' => 0,
        'class_uses' => 0,
        'class_alias' => 0,
        'is_a' => 1,
        'is_subclass_of' => 1,
    ];

    /**
     * The arguments that are callables, by lowercase name of the function
     *
     * @var array<string, int>
     */
    private const CALLABLES = [
        'call_user_func' => 0,
        'call_user_func_array' => 0,
        'forward_static_call' => 0,
        'forward_static_call_array' => 0,
        'is_callable' => 0,
        'array_map' => 0,
        'register_shutdown_function' => 0,
        'set_error_handler' => 0,
        'set_exception_handler' => 0,
        'spl_autoload_register' => 0,
        'array_filter' => 1,
        'array_reduce' => 1,
        'array_walk' => 1,
        'array_walk_recursive' => 1,
        'usort' => 1,
        'uasort' => 1,
        'uksort' => 1,
    ];

    /**
     * The classes of reflection whose first argument names a class, in lowercase
     *
     * @var array<string>
     */
    private const REFLECTION = ['reflectionclass', 'reflectionmethod', 'reflectionproperty', 'reflectionclassconstant', 'reflectionenum'];

    /**
     * The methods of PHPUnit (and CakeTestCase::getMock()) whose first argument names a class, in lowercase
     *
     * @var array<string>
     */
    private const TEST_METHODS = [
        'getmock', 'getmockbuilder', 'getmockclass', 'getmockforabstractclass', 'getmockfortrait', 'createmock',
        'createpartialmock', 'createstub', 'createconfiguredmock', 'assertinstanceof', 'assertnotinstanceof',
        'assertcontainsonlyinstancesof', 'expectexception',
    ];

    /**
     * The namespaced names of the classes, by lowercase global name
     *
     * @var array<string, string>
     */
    private array $classes = [];

    /**
     * @param array<string, string> $configuration The namespaced names of the classes, by global name
     * @return void
     */
    public function configure(array $configuration): void
    {
        $this->classes = [];
        foreach ($configuration as $global => $namespaced) {
            $this->classes[strtolower((string)$global)] = $namespaced;
        }
    }

    /**
     * @return \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Replace the class names in the strings that PHP reads as class names by ::class', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
if (class_exists('Post')) {
    $this->Posts = $this->getMock('PostsController', ['redirect']);
}
CODE_SAMPLE,
                <<<'CODE_SAMPLE'
if (class_exists(\App\Model\Post::class)) {
    $this->Posts = $this->getMock(\App\Controller\PostsController::class, ['redirect']);
}
CODE_SAMPLE,
                ['Post' => 'App\Model\Post', 'PostsController' => 'App\Controller\PostsController'],
            ),
        ]);
    }

    /**
     * @return array<class-string<\PhpParser\Node>>
     */
    public function getNodeTypes(): array
    {
        return [FuncCall::class, New_::class, MethodCall::class, StaticCall::class];
    }

    /**
     * @param \PhpParser\Node\Expr\FuncCall|\PhpParser\Node\Expr\New_|\PhpParser\Node\Expr\MethodCall|\PhpParser\Node\Expr\StaticCall $node Call
     * @return \PhpParser\Node|null
     */
    public function refactor(Node $node): ?Node
    {
        if ($node->isFirstClassCallable()) {
            return null;
        }
        $args = $node->getArgs();
        if ($node instanceof FuncCall) {
            $name = $node->name instanceof Name ? strtolower($node->name->getLast()) : '';
            if (isset(self::FUNCTIONS[$name])) {
                return $this->replaceClass($args[self::FUNCTIONS[$name]] ?? null) ? $node : null;
            }
            if (isset(self::CALLABLES[$name])) {
                return $this->replaceCallable($args[self::CALLABLES[$name]] ?? null) ? $node : null;
            }

            return null;
        }
        if ($node instanceof New_) {
            if (!$node->class instanceof Name || !in_array(strtolower($node->class->getLast()), self::REFLECTION, true)) {
                return null;
            }

            return $this->replaceClass($args[0] ?? null) ? $node : null;
        }
        $method = $this->getName($node->name);
        if ($method === null || !in_array(strtolower($method), self::TEST_METHODS, true)) {
            return null;
        }

        return $this->replaceClass($args[0] ?? null) ? $node : null;
    }

    /**
     * `'Post'` becomes `Post::class`
     *
     * @param \PhpParser\Node\Arg|null $arg Argument
     * @return bool Whether it changed
     */
    private function replaceClass(?Arg $arg): bool
    {
        $class = $arg !== null && !$arg->unpack ? $this->classConstant($arg->value) : null;
        if ($class === null) {
            return false;
        }
        /** @var \PhpParser\Node\Arg $arg */
        $arg->value = $class;

        return true;
    }

    /**
     * `['Post', 'method']` becomes `[Post::class, 'method']`, `'Post::method'` becomes `[Post::class, 'method']`
     *
     * @param \PhpParser\Node\Arg|null $arg Argument
     * @return bool Whether it changed
     */
    private function replaceCallable(?Arg $arg): bool
    {
        if ($arg === null || $arg->unpack) {
            return false;
        }
        $value = $arg->value;
        if ($value instanceof Array_ && count($value->items) === 2 && $value->items[0]->key === null) {
            $class = $this->classConstant($value->items[0]->value);
            if ($class === null) {
                return false;
            }
            $value->items[0]->value = $class;

            return true;
        }
        if ($value instanceof String_ && substr_count($value->value, '::') === 1) {
            [$className, $method] = explode('::', $value->value);
            $class = $this->classConstant(new String_($className));
            if ($class === null || $method === '') {
                return false;
            }
            $arg->value = new Array_([new ArrayItem($class), new ArrayItem(new String_($method))]);

            return true;
        }

        return false;
    }

    /**
     * `\Namespaced\Name::class` for a string with a global name of the configuration
     *
     * @param \PhpParser\Node\Expr $expr Expression
     * @return \PhpParser\Node\Expr\ClassConstFetch|null
     */
    private function classConstant(Expr $expr): ?ClassConstFetch
    {
        if (!$expr instanceof String_) {
            return null;
        }
        $namespaced = $this->classes[strtolower(ltrim($expr->value, '\\'))] ?? null;

        return $namespaced === null ? null : new ClassConstFetch(new FullyQualified($namespaced), 'class');
    }
}
