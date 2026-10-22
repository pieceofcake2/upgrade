<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Migration;

use PhpParser\Comment\Doc;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\InlineHTML;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

/**
 * Imports the classes of a file that Rector does not import, a template with HTML or `bin/cake` (its `#!` line is
 * HTML for PHP), with `use` statements: `\Cake\Core\Configure::read('debug')` or `Configure::read('debug')` becomes
 * `Configure::read('debug')` with `use Cake\Core\Configure;`.
 *
 * The use statements go at the top, as in the templates of CakePHP 5: in the first block of PHP when the file starts
 * with one (after its docblock, or after its use statements), else in a new block:
 *
 * ```
 * <?php
 * use Cake\Core\Configure;
 * ?>
 * <h2><?php echo h($message); ?></h2>
 * ```
 *
 * A name stays fully qualified when its short name is taken: by a use statement, a class of the file, or another
 * class name of the file. The rest of the file stays as it is; a file without a known class does not change.
 */
final class NameImporter
{
    /**
     * Imports the known classes of the code
     *
     * @param string $code The code of the file
     * @param array<string, string> $classes The namespaced names of the classes, by global name
     * @return string The code
     */
    public static function import(string $code, array $classes): string
    {
        try {
            $stmts = (new ParserFactory())->createForNewestSupportedVersion()->parse($code) ?? [];
        } catch (Error) {
            return $code;
        }
        if ((new NodeFinder())->findFirstInstanceOf($stmts, Namespace_::class) !== null) {
            return $code;
        }
        $names = self::classNames($stmts);
        $imports = self::imports($stmts, $names, $classes);
        if ($imports === []) {
            return $code;
        }

        $edits = [];
        $new = [];
        foreach ($names as $name) {
            $class = $imports[spl_object_id($name)] ?? null;
            if ($class === null) {
                continue;
            }
            [$short, $added] = $class;
            if ($added !== null) {
                $new[strtolower($added)] = 'use ' . $added . ';';
            }
            $edits[] = [$name->getStartFilePos(), $name->getEndFilePos() + 1, $short];
        }
        if ($new !== []) {
            $edits[] = self::useBlock($code, $stmts, array_values($new));
        }
        usort($edits, static fn(array $a, array $b): int => $b[0] <=> $a[0]);
        foreach ($edits as [$start, $end, $text]) {
            $code = substr($code, 0, $start) . $text . substr($code, $end);
        }

        return $code;
    }

    /**
     * The class names of the code, with their resolved names, in their order
     *
     * @param array<\PhpParser\Node\Stmt> $stmts The statements
     * @return array<\PhpParser\Node\Name>
     */
    private static function classNames(array $stmts): array
    {
        $traverser = new NodeTraverser(new NameResolver(null, ['replaceNodes' => false]));
        $traverser->traverse($stmts);
        $collector = new class extends NodeVisitorAbstract {
            /**
             * @var array<\PhpParser\Node\Name>
             */
            public array $names = [];

            /**
             * @var array<int, true>
             */
            private array $skip = [];

            /**
             * @param \PhpParser\Node $node Node
             * @return int|null
             */
            public function enterNode(Node $node)
            {
                if ($node instanceof Use_ || $node instanceof GroupUse) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }
                if (($node instanceof FuncCall || $node instanceof ConstFetch) && $node->name instanceof Name) {
                    $this->skip[spl_object_id($node->name)] = true;
                }
                if ($node instanceof Name && !isset($this->skip[spl_object_id($node)]) && $node->hasAttribute('resolvedName')) {
                    $this->names[] = $node;
                }

                return null;
            }
        };
        (new NodeTraverser($collector))->traverse($stmts);

        return $collector->names;
    }

    /**
     * The short names of the known classes, and the classes to import, by id of the name
     *
     * @param array<\PhpParser\Node\Stmt> $stmts The statements
     * @param array<\PhpParser\Node\Name> $names The class names
     * @param array<string, string> $classes The namespaced names of the classes, by global name
     * @return array<int, array{string, string|null}> The new name, and the class to import or null
     */
    private static function imports(array $stmts, array $names, array $classes): array
    {
        $global = array_change_key_case($classes);
        $known = [];
        foreach ($classes as $class) {
            $known[strtolower($class)] = $class;
        }
        // The short names that name a class already, by lowercase short name
        $taken = [];
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Use_ && $stmt->type === Use_::TYPE_NORMAL) {
                foreach ($stmt->uses as $use) {
                    $taken[strtolower($use->getAlias()->toString())] = $use->name->toString();
                }
            }
        }
        $existing = $taken;
        foreach ((new NodeFinder())->findInstanceOf($stmts, ClassLike::class) as $classLike) {
            if ($classLike->name !== null) {
                $taken[$classLike->name->toLowerString()] = '';
            }
        }
        $wanted = [];
        foreach ($names as $name) {
            $class = self::knownClass($name, $global, $known);
            if ($class !== null) {
                $wanted[spl_object_id($name)] = $class;
            } elseif ($name->isUnqualified()) {
                $taken[$name->toLowerString()] ??= $name->getAttribute('resolvedName')->toString();
            }
        }

        $imports = [];
        foreach ($wanted as $id => $class) {
            $short = substr($class, (int)strrpos('\\' . $class, '\\'));
            $owner = $taken[strtolower($short)] ?? null;
            if ($owner === null) {
                $taken[strtolower($short)] = $class;
                $imports[$id] = [$short, $class];
            } elseif (strtolower($owner) === strtolower($class)) {
                $imports[$id] = [$short, isset($existing[strtolower($short)]) ? null : $class];
            } else {
                $imports[$id] = ['\\' . $class, null];
            }
        }

        return $imports;
    }

    /**
     * The namespaced class of a name, when it is a known class: a global name of the map, or a namespaced name of it
     * written fully qualified
     *
     * @param \PhpParser\Node\Name $name The name
     * @param array<string, string> $global The namespaced names, by lowercase global name
     * @param array<string, string> $known The namespaced names, by lowercase namespaced name
     * @return string|null
     */
    private static function knownClass(Name $name, array $global, array $known): ?string
    {
        /** @var \PhpParser\Node\Name $resolved */
        $resolved = $name->getAttribute('resolvedName');
        $lower = $resolved->toLowerString();
        if ($name instanceof FullyQualified && isset($known[$lower])) {
            return $known[$lower];
        }

        return !str_contains($lower, '\\') ? $global[$lower] ?? null : null;
    }

    /**
     * The edit that adds the use statements at the top of the file
     *
     * @param string $code The code
     * @param array<\PhpParser\Node\Stmt> $stmts The statements
     * @param array<string> $uses The use statements
     * @return array{int, int, string} The start and the end of the replaced text, and the new text
     */
    private static function useBlock(string $code, array $stmts, array $uses): array
    {
        $eol = str_contains($code, "\r\n") ? "\r\n" : "\n";
        $lines = implode($eol, $uses);
        $offset = 0;
        if (isset($stmts[0]) && $stmts[0] instanceof InlineHTML && str_starts_with($code, '#!')) {
            $offset = (int)strpos($code, "\n") + 1;
            array_shift($stmts);
        }
        if (!preg_match('/\G<\?php\r?\n/', $code, $match, 0, $offset)) {
            return [$offset, $offset, '<?php' . $eol . $lines . $eol . '?>' . $eol];
        }

        $start = $offset + 5;
        $before = $eol;
        $first = null;
        $last = null;
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Declare_ && $stmt->stmts === null && $first === null) {
                $start = $stmt->getEndFilePos() + 1;
                $before = $eol . $eol;
                continue;
            }
            if ($stmt instanceof Use_ || $stmt instanceof GroupUse) {
                $first ??= $stmt;
                $last = $stmt;
                continue;
            }
            $first ??= $stmt;
            break;
        }
        if ($last !== null) {
            $end = $last->getEndFilePos() + 1;

            return [$end, $end, $eol . $lines];
        }
        $comments = $first instanceof Stmt ? $first->getComments() : [];
        if ($comments !== [] && $comments[0] instanceof Doc) {
            $start = $comments[0]->getEndFilePos() + 1;
            $before = null;
        }
        $length = strspn($code, " \t\r\n", $start);
        $gap = substr($code, $start, $length);
        if ($before === null) {
            $before = substr_count($gap, "\n") > 1 ? $eol . $eol : $eol;
        }
        $after = substr($code, $start + $length, 2) === '?>' ? $eol : $eol . $eol;

        return [$start, $start + $length, $before . $lines . $after];
    }
}
