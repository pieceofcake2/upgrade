<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeVisitor;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Rector\Reflection\ReflectionResolver;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Adds the variadic parameter of a method of CakePHP to the methods that override it.
 *
 * PHP refuses an override with fewer parameters than the method it overrides, so a method of the application that
 * overrides `Model::query($sql)` must become `query($sql, ...$params)` once CakePHP declares the parameter. The
 * `func_get_args()` of the method becomes the list of its parameters when they are all required: with an optional
 * one, `func_get_args()` leaves it out when it is not passed, so it stays, and returns the same values as before.
 */
final class VariadicOverrideRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * The methods of CakePHP and the names of their variadic parameters, by class
     *
     * @var array<string, array<string, string>>
     */
    private array $methods = [];

    /**
     * @param \Rector\Reflection\ReflectionResolver $reflectionResolver Reflection of the classes
     * @param \PHPStan\Reflection\ReflectionProvider $reflectionProvider Reflection of the parent classes
     */
    public function __construct(private ReflectionResolver $reflectionResolver, private ReflectionProvider $reflectionProvider)
    {
    }

    /**
     * @param array<string, array<string, string>> $configuration The names of the variadic parameters, by method and class
     * @return void
     */
    public function configure(array $configuration): void
    {
        $this->methods = $configuration;
    }

    /**
     * @return \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Add the variadic parameter of a method of CakePHP to the methods that override it', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
class AppModel extends Model
{
    public function query($sql)
    {
        return parent::query(...func_get_args());
    }
}
CODE_SAMPLE,
                <<<'CODE_SAMPLE'
class AppModel extends Model
{
    public function query($sql, ...$params)
    {
        return parent::query($sql, ...$params);
    }
}
CODE_SAMPLE,
                ['Model' => ['query' => 'params']],
            ),
        ]);
    }

    /**
     * @return array<class-string<\PhpParser\Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param \PhpParser\Node\Stmt\Class_ $node Class
     * @return \PhpParser\Node\Stmt\Class_|null
     */
    public function refactor(Node $node): ?Class_
    {
        $classReflection = $this->reflectionResolver->resolveClassReflection($node);
        if ($classReflection === null) {
            return null;
        }
        $changed = false;
        $parents = $this->parentNames($classReflection);
        foreach ($this->methods as $class => $methods) {
            if (!$classReflection->isSubclassOf($class) && !in_array(strtolower($class), $parents, true)) {
                continue;
            }
            foreach ($node->getMethods() as $classMethod) {
                foreach ($methods as $method => $variadic) {
                    if ($this->isName($classMethod, $method) && $this->addVariadic($classMethod, $variadic)) {
                        $changed = true;
                    }
                }
            }
        }

        return $changed ? $node : null;
    }

    /**
     * The names of the parent classes as the classes declare them, lowercase. The reflection does not resolve the
     * global names of pieceofcake2/cakephp 2.13, which are aliases of the classes in the Cake namespace
     * (`class_alias(Model::class, 'Model')`), so it finds no parents for `class AppModel extends Model`.
     *
     * @param \PHPStan\Reflection\ClassReflection $classReflection The class
     * @return array<string>
     */
    private function parentNames(ClassReflection $classReflection): array
    {
        $names = [];
        $reflection = $classReflection->getNativeReflection()->getBetterReflection();
        while (($parent = $reflection->getParentClassName()) !== null && !in_array(strtolower($parent), $names, true)) {
            $names[] = strtolower($parent);
            if (!$this->reflectionProvider->hasClass($parent)) {
                break;
            }
            $reflection = $this->reflectionProvider->getClass($parent)->getNativeReflection()->getBetterReflection();
        }

        return $names;
    }

    /**
     * Adds the variadic parameter to a method that has none, and replaces its `func_get_args()` when it can
     *
     * @param \PhpParser\Node\Stmt\ClassMethod $classMethod Method
     * @param string $name Name of the variadic parameter of CakePHP
     * @return bool Whether the method changed
     */
    private function addVariadic(ClassMethod $classMethod, string $name): bool
    {
        $names = [];
        foreach ($classMethod->params as $param) {
            if ($param->variadic) {
                return false;
            }
            $names[] = $this->getName($param->var);
        }
        // Neither a parameter nor a variable of the method
        $this->traverseNodesWithCallable((array)$classMethod->stmts, function (Node $node) use (&$names) {
            if ($node instanceof Variable && is_string($node->name)) {
                $names[] = $node->name;
            }

            return null;
        });
        $candidates = [$name, 'args', 'moreArgs', 'variadicArgs'];
        $name = current(array_diff($candidates, $names)) ?: $name . count($names);
        $classMethod->params[] = new Param(new Variable($name), null, null, false, true);

        $required = array_filter($classMethod->params, static fn(Param $param): bool => $param->default === null && !$param->variadic);
        if (count($required) === count($classMethod->params) - 1 && $classMethod->stmts !== null) {
            $this->replaceFuncGetArgs($classMethod);
        }

        return true;
    }

    /**
     * Replaces `func_get_args()` with the list of the parameters, outside the closures and functions of the method:
     * `parent::query(...func_get_args())` becomes `parent::query($sql, ...$params)`, other uses an array
     *
     * @param \PhpParser\Node\Stmt\ClassMethod $classMethod Method whose parameters are all required but the variadic
     * @return void
     */
    private function replaceFuncGetArgs(ClassMethod $classMethod): void
    {
        $this->traverseNodesWithCallable((array)$classMethod->stmts, function (Node $node) use ($classMethod) {
            if ($node instanceof Closure || $node instanceof ArrowFunction || $node instanceof Function_ || $node instanceof Class_) {
                return NodeVisitor::DONT_TRAVERSE_CHILDREN;
            }
            $isCall = $node instanceof MethodCall || $node instanceof StaticCall || $node instanceof FuncCall
                || $node instanceof New_ || $node instanceof NullsafeMethodCall;
            if ($isCall && !$node->isFirstClassCallable()) {
                $args = [];
                $changed = false;
                foreach ($node->getArgs() as $arg) {
                    if ($arg->unpack && $this->isFuncGetArgs($arg->value)) {
                        foreach ($classMethod->params as $param) {
                            $args[] = new Arg(new Variable((string)$this->getName($param->var)), false, $param->variadic);
                        }
                        $changed = true;
                    } else {
                        $args[] = $arg;
                    }
                }
                if ($changed) {
                    $node->args = $args;

                    return $node;
                }
            }
            if (!$this->isFuncGetArgs($node)) {
                return null;
            }
            $items = [];
            foreach ($classMethod->params as $param) {
                $items[] = new ArrayItem(new Variable((string)$this->getName($param->var)), null, false, [], $param->variadic);
            }

            return new Array_($items);
        });
    }

    /**
     * Whether a node is a call of `func_get_args()`
     *
     * @param \PhpParser\Node $node Node
     * @return bool
     */
    private function isFuncGetArgs(Node $node): bool
    {
        return $node instanceof FuncCall && $this->isName($node, 'func_get_args');
    }
}
