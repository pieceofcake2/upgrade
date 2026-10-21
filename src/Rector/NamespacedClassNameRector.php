<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Switch_;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces the global CakePHP 2 class names that are compared with the class of an object.
 *
 * pieceofcake2/cakephp declares its classes in the `Cake` namespace and keeps the global names as aliases, so
 * `get_class($request)` returns `Cake\Network\CakeRequest`, not `CakeRequest`. A comparison of the class with the
 * global name, `get_class($request) === 'CakeRequest'`, becomes `get_class($request) ===
 * \Cake\Network\CakeRequest::class`, which compares the same classes as before. `instanceof` would also match the
 * subclasses, so it is not used.
 *
 * The class is the result of `get_class()`, `get_parent_class()`, `get_called_class()` or `::class` of an object or of
 * `static`. It is compared with `===`, `==`, `!==`, `!=`, `in_array()`, `switch` or `match`.
 */
final class NamespacedClassNameRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * The namespaced names of the classes of CakePHP, by lowercase global name
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
            $this->classes[strtolower($global)] = $namespaced;
        }
    }

    /**
     * @return \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Replace the global CakePHP class names compared with the class of an object', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
if (get_class($this->request) === 'CakeRequest') {
}
CODE_SAMPLE,
                <<<'CODE_SAMPLE'
if (get_class($this->request) === \Cake\Network\CakeRequest::class) {
}
CODE_SAMPLE,
                ['CakeRequest' => 'Cake\Network\CakeRequest'],
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
        if ($this->isClassOfObject($node->left)) {
            return $this->replace($node->right, static function (Expr $class) use ($node): void {
                $node->right = $class;
            }) ? $node : null;
        }
        if ($this->isClassOfObject($node->right)) {
            return $this->replace($node->left, static function (Expr $class) use ($node): void {
                $node->left = $class;
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
        $changed = false;
        foreach ($args[1]->value->items as $item) {
            if ($item->key === null) {
                $changed = $this->replace($item->value, static function (Expr $class) use ($item): void {
                    $item->value = $class;
                }) || $changed;
            }
        }

        return $changed ? $node : null;
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
     * Replaces a string with the global name of a class of CakePHP by `\Namespaced\Name::class`
     *
     * @param \PhpParser\Node\Expr|null $expr The compared expression
     * @param callable(\PhpParser\Node\Expr): void $set Sets the new expression
     * @return bool Whether it was replaced
     */
    private function replace(?Expr $expr, callable $set): bool
    {
        if (!$expr instanceof String_) {
            return false;
        }
        $name = strtolower(ltrim($expr->value, '\\'));
        if (!isset($this->classes[$name])) {
            return false;
        }
        $set(new ClassConstFetch(new FullyQualified($this->classes[$name]), 'class'));

        return true;
    }
}
