<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Switch_;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces the global class names that are compared with the class of an object.
 *
 * pieceofcake2/cakephp declares its classes in the `Cake` namespace and keeps the global names as aliases, so
 * `get_class($request)` returns `Cake\Network\CakeRequest`, not `CakeRequest`; the classes of an application become
 * namespaced in the same way. A comparison of the class with the global name, `get_class($request) ===
 * 'CakeRequest'`, becomes `$request instanceof \Cake\Network\CakeRequest` when no class of CakePHP and no class of the
 * analysed code extends the class, as `instanceof` then matches the same objects. Otherwise it becomes
 * `get_class($request) === \Cake\Network\CakeRequest::class`, which compares the same classes as before, and the place
 * is listed in the report of the upgrade shell. The classes of other packages (`vendor/`) are not checked.
 *
 * The class is the result of `get_class()`, `get_parent_class()`, `get_called_class()` or `::class` of an object or of
 * `static`. It is compared with `===`, `==`, `!==`, `!=`, `in_array()`, `switch` or `match`. The comparisons with
 * `get_class($object)` or `$object::class` may become `instanceof`; the others always keep `::class`.
 */
final class NamespacedClassNameRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * Configuration key: the namespaced names of the classes, by global name
     *
     * @var string
     */
    public const CLASSES = 'classes';

    /**
     * Configuration key: the global names of the classes and interfaces that other classes extend or implement
     *
     * @var string
     */
    public const EXTENDED = 'extended';

    /**
     * Configuration key: whether a comparison may become `instanceof`
     *
     * @var string
     */
    public const INSTANCEOF = 'instanceof';

    /**
     * Configuration key: other files and directories whose classes are checked, besides the processed paths
     *
     * @var string
     */
    public const INDEX_PATHS = 'indexPaths';

    /**
     * The namespaced names of the classes, by lowercase global name
     *
     * @var array<string, string>
     */
    private array $classes = [];

    /**
     * The lowercase names of the classes and interfaces that other classes extend or implement
     *
     * @var array<string, true>
     */
    private array $extended = [];

    /**
     * @var bool
     */
    private bool $instanceof = false;

    /**
     * @var array<string>
     */
    private array $indexPaths = [];

    /**
     * @param array<string, mixed> $configuration The classes (`classes`, or the whole configuration as in version
     *   1.1), and the options `extended`, `instanceof` and `indexPaths`
     * @return void
     */
    public function configure(array $configuration): void
    {
        $classes = $configuration[self::CLASSES] ?? $configuration;
        $this->classes = [];
        foreach ($classes as $global => $namespaced) {
            if (is_string($namespaced)) {
                $this->classes[strtolower((string)$global)] = $namespaced;
            }
        }
        $this->extended = [];
        foreach ((array)($configuration[self::EXTENDED] ?? []) as $class) {
            $this->extended[strtolower($class)] = true;
        }
        $this->instanceof = (bool)($configuration[self::INSTANCEOF] ?? false);
        $this->indexPaths = (array)($configuration[self::INDEX_PATHS] ?? []);
    }

    /**
     * @return \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Replace the global class names compared with the class of an object', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
if (get_class($this->request) === 'CakeRequest') {
}
if (get_class($this->response) === 'CakeResponse') {
}
CODE_SAMPLE,
                <<<'CODE_SAMPLE'
if ($this->request instanceof \Cake\Network\CakeRequest) {
}
if (get_class($this->response) === \Cake\Network\CakeResponse::class) {
}
CODE_SAMPLE,
                [
                    self::CLASSES => ['CakeRequest' => 'Cake\Network\CakeRequest', 'CakeResponse' => 'Cake\Network\CakeResponse'],
                    self::EXTENDED => ['CakeResponse'],
                    self::INSTANCEOF => true,
                ],
            ),
        ]);
    }

    /**
     * @return array<class-string<\PhpParser\Node>>
     */
    public function getNodeTypes(): array
    {
        return [Identical::class, NotIdentical::class, Equal::class, NotEqual::class, FuncCall::class, Switch_::class, Match_::class];
    }

    /**
     * @param \PhpParser\Node $node Comparison, call of in_array(), switch or match
     * @return \PhpParser\Node|null
     */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof BinaryOp) {
            return $this->refactorComparison($node);
        }
        if ($node instanceof FuncCall) {
            return $this->refactorInArray($node);
        }
        if ($node instanceof Switch_) {
            if (!$this->isClassOfObject($node->cond)) {
                return null;
            }
            $changed = false;
            foreach ($node->cases as $case) {
                $changed = $this->replace($case->cond, static function (Expr $class) use ($case): void {
                    $case->cond = $class;
                }) || $changed;
            }

            return $changed ? $node : null;
        }

        /** @var \PhpParser\Node\Expr\Match_ $node */
        if (!$this->isClassOfObject($node->cond)) {
            return null;
        }
        $changed = false;
        foreach ($node->arms as $arm) {
            foreach ((array)$arm->conds as $index => $cond) {
                $changed = $this->replace($cond, static function (Expr $class) use ($arm, $index): void {
                    $arm->conds[$index] = $class;
                }) || $changed;
            }
        }

        return $changed ? $node : null;
    }

    /**
     * `get_class($x) === 'CakeRequest'` and `'CakeRequest' === get_class($x)`
     *
     * @param \PhpParser\Node\Expr\BinaryOp $node Comparison
     * @return \PhpParser\Node|null
     */
    private function refactorComparison(BinaryOp $node): ?Node
    {
        foreach ([[$node->left, $node->right, 'right'], [$node->right, $node->left, 'left']] as [$class, $name, $side]) {
            if (!$this->isClassOfObject($class)) {
                continue;
            }
            $object = $this->objectOf($class);
            $namespaced = $this->namespacedName($name);
            if ($object !== null && $namespaced !== null && $this->instanceof) {
                if (!$this->isExtended($name)) {
                    $instanceof = new Instanceof_($object, new FullyQualified($namespaced));

                    return $node instanceof Identical || $node instanceof Equal ? $instanceof : new BooleanNot($instanceof);
                }
                $this->reportKept($node, $name);
            }

            return $this->replace($name, static function (Expr $expr) use ($node, $side): void {
                $node->{$side} = $expr;
            }) ? $node : null;
        }

        return null;
    }

    /**
     * `in_array(get_class($x), ['CakeRequest', ...])`
     *
     * @param \PhpParser\Node\Expr\FuncCall $node Call
     * @return \PhpParser\Node|null
     */
    private function refactorInArray(FuncCall $node): ?Node
    {
        if (!$this->isName($node, 'in_array') || $node->isFirstClassCallable()) {
            return null;
        }
        $args = $node->getArgs();
        if (count($args) < 2 || !$this->isClassOfObject($args[0]->value) || !$args[1]->value instanceof Array_) {
            return null;
        }
        $instanceof = $this->instanceofAny($args[0]->value, $args[1]->value);
        if ($instanceof !== null) {
            return $instanceof;
        }
        $changed = false;
        foreach ($args[1]->value->items as $item) {
            if ($item->key === null && !$item->unpack) {
                if ($this->instanceof && $this->namespacedName($item->value) !== null && $this->isExtended($item->value)) {
                    $this->reportKept($node, $item->value);
                }
                $changed = $this->replace($item->value, static function (Expr $class) use ($item): void {
                    $item->value = $class;
                }) || $changed;
            }
        }

        return $changed ? $node : null;
    }

    /**
     * `$x instanceof A || $x instanceof B` for `in_array(get_class($x), ['A', 'B'])`, when all the names are classes
     * that no class extends and `$x` is a variable or a property, which can be read more than once
     *
     * @param \PhpParser\Node\Expr $class The class of the object
     * @param \PhpParser\Node\Expr\Array_ $names The names
     * @return \PhpParser\Node\Expr|null
     */
    private function instanceofAny(Expr $class, Array_ $names): ?Expr
    {
        $object = $this->objectOf($class);
        if (!$this->instanceof || $object === null || !$this->isPlain($object) || $names->items === []) {
            return null;
        }
        $instanceof = null;
        foreach ($names->items as $item) {
            $namespaced = $this->namespacedName($item->value);
            if ($item->key !== null || $item->unpack || $namespaced === null || $this->isExtended($item->value)) {
                return null;
            }
            $check = new Instanceof_($instanceof === null ? $object : clone $object, new FullyQualified($namespaced));
            $instanceof = $instanceof === null ? $check : new BooleanOr($instanceof, $check);
        }

        return $instanceof;
    }

    /**
     * Returns whether an expression is the class name of an object
     *
     * @param \PhpParser\Node\Expr|null $expr Expression
     * @return bool
     */
    private function isClassOfObject(?Expr $expr): bool
    {
        if ($expr instanceof FuncCall) {
            return $this->isNames($expr, ['get_class', 'get_parent_class', 'get_called_class']);
        }
        if ($expr instanceof ClassConstFetch && $expr->name instanceof Identifier && $expr->name->toLowerString() === 'class') {
            return $expr->class instanceof Expr || $expr->class->toLowerString() === 'static';
        }

        return false;
    }

    /**
     * The object of `get_class($object)` or `$object::class`, for which a comparison may become `instanceof`
     *
     * @param \PhpParser\Node\Expr $class The class of an object
     * @return \PhpParser\Node\Expr|null
     */
    private function objectOf(Expr $class): ?Expr
    {
        if ($class instanceof FuncCall) {
            if (!$this->isName($class, 'get_class') || $class->isFirstClassCallable()) {
                return null;
            }
            $args = $class->getArgs();

            return count($args) === 1 && !$args[0]->unpack && $args[0]->name === null ? $args[0]->value : null;
        }

        return $class instanceof ClassConstFetch && $class->class instanceof Expr ? $class->class : null;
    }

    /**
     * Whether an expression is a variable or a property of one, without calls
     *
     * @param \PhpParser\Node\Expr $expr Expression
     * @return bool
     */
    private function isPlain(Expr $expr): bool
    {
        if ($expr instanceof Variable) {
            return is_string($expr->name);
        }

        return $expr instanceof PropertyFetch && $expr->name instanceof Identifier && $this->isPlain($expr->var);
    }

    /**
     * The namespaced name of a string with the global name of a class
     *
     * @param \PhpParser\Node\Expr|null $expr The compared expression
     * @return string|null
     */
    private function namespacedName(?Expr $expr): ?string
    {
        if (!$expr instanceof String_) {
            return null;
        }

        return $this->classes[strtolower(ltrim($expr->value, '\\'))] ?? null;
    }

    /**
     * Whether a class of CakePHP or of the analysed code extends the class of the string, or implements it
     *
     * @param \PhpParser\Node\Expr $expr The string with the global name
     * @return bool
     */
    private function isExtended(Expr $expr): bool
    {
        if (!$expr instanceof String_) {
            return true;
        }
        $global = ltrim($expr->value, '\\');
        if (isset($this->extended[strtolower($global)])) {
            return true;
        }
        $index = ClassIndex::create($this->indexPaths);

        return $index->subclasses($global) !== [] || $index->subclasses((string)$this->namespacedName($expr)) !== [];
    }

    /**
     * Lists a comparison that stays a comparison, as the class has subclasses
     *
     * @param \PhpParser\Node $node The comparison
     * @param \PhpParser\Node\Expr $expr The string with the global name
     * @return void
     */
    private function reportKept(Node $node, Expr $expr): void
    {
        $class = $expr instanceof String_ ? ltrim($expr->value, '\\') : '';
        Report::add(
            $this->file->getFilePath(),
            $node->getStartLine(),
            sprintf('kept the comparison with %s::class, as %s has subclasses; use instanceof if they should match too', $class, $class),
        );
    }

    /**
     * Replaces a string with the global name of a class by `\Namespaced\Name::class`
     *
     * @param \PhpParser\Node\Expr|null $expr The compared expression
     * @param callable(\PhpParser\Node\Expr): void $set Sets the new expression
     * @return bool Whether it was replaced
     */
    private function replace(?Expr $expr, callable $set): bool
    {
        $namespaced = $this->namespacedName($expr);
        if ($namespaced === null) {
            return false;
        }
        $set(new ClassConstFetch(new FullyQualified($namespaced), 'class'));

        return true;
    }
}
