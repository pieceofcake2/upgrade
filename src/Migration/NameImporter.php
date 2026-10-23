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
use PhpToken;

/**
 * Imports the classes of a file that Rector does not import, a template or `bin/cake` (its `#!` line is HTML for
 * PHP), with `use` statements: `\Cake\Core\Configure::read('debug')` or `Configure::read('debug')` becomes
 * `Configure::read('debug')` with `use Cake\Core\Configure;`, and `@var View $this` gets `use Cake\View\View;`.
 *
 * The use statements go at the top, sorted, as in the templates of pieceofcake2/app: in the first block of PHP when
 * the file starts with one (after its docblock, between blank lines, or with its use statements), else in a new block:
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
     * The tags of the docblocks whose type names are imported
     *
     * @var string
     */
    private const TAGS = 'var|param|return|throws|property|property-read|property-write|method|mixin';

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
        $uses = self::uses($stmts);
        $refs = array_merge(self::classNames($stmts), self::docNames($code, $uses));
        usort($refs, static fn(array $a, array $b): int => $a['start'] <=> $b['start']);
        $imports = self::imports($stmts, $refs, $uses, $classes);
        if ($imports === []) {
            return $code;
        }

        $edits = [];
        $new = [];
        foreach ($refs as $index => $ref) {
            if (!isset($imports[$index])) {
                continue;
            }
            [$short, $added] = $imports[$index];
            if ($added !== null) {
                $new[strtolower($added)] = $added;
            }
            $edits[] = [$ref['start'], $ref['end'], $short];
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
     * The classes of the use statements of the file, by lowercase alias
     *
     * @param array<\PhpParser\Node\Stmt> $stmts The statements
     * @return array<string, string>
     */
    private static function uses(array $stmts): array
    {
        $uses = [];
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Use_ && $stmt->type === Use_::TYPE_NORMAL) {
                foreach ($stmt->uses as $use) {
                    $uses[strtolower($use->getAlias()->toString())] = $use->name->toString();
                }
            }
        }

        return $uses;
    }

    /**
     * The class names of the code: their place, how they are written and the class they name
     *
     * @param array<\PhpParser\Node\Stmt> $stmts The statements
     * @return array<array{start: int, end: int, name: string, fullyQualified: bool, resolved: string}>
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

        return array_map(static fn(Name $name): array => [
            'start' => $name->getStartFilePos(),
            'end' => $name->getEndFilePos() + 1,
            'name' => $name->toString(),
            'fullyQualified' => $name instanceof FullyQualified,
            'resolved' => $name->getAttribute('resolvedName')->toString(),
        ], $collector->names);
    }

    /**
     * The class names of the types of the docblocks (`@var`, `@param`, `@return`, ...)
     *
     * @param string $code The code
     * @param array<string, string> $uses The classes of the use statements, by lowercase alias
     * @return array<array{start: int, end: int, name: string, fullyQualified: bool, resolved: string}>
     */
    private static function docNames(string $code, array $uses): array
    {
        $refs = [];
        foreach (PhpToken::tokenize($code) as $token) {
            if ($token->id !== T_DOC_COMMENT) {
                continue;
            }
            preg_match_all('/@(?:' . self::TAGS . ')[ \t]+/', $token->text, $tags, PREG_OFFSET_CAPTURE);
            foreach ($tags[0] as [$tag, $offset]) {
                $typeStart = $offset + strlen($tag);
                $type = self::typeAt($token->text, $typeStart);
                if (str_starts_with($tag, '@method') && strtolower($type) === 'static') {
                    $typeStart += strlen($type) + strspn($token->text, " \t", $typeStart + strlen($type));
                    $type = self::typeAt($token->text, $typeStart);
                }
                preg_match_all('/(?<![\w\\\\$-])\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*(?![\w\\\\-]|\s*:(?!:))/', $type, $names, PREG_OFFSET_CAPTURE);
                foreach ($names[0] as [$name, $nameOffset]) {
                    $start = $token->pos + $typeStart + $nameOffset;
                    $refs[] = [
                        'start' => $start,
                        'end' => $start + strlen($name),
                        'name' => ltrim($name, '\\'),
                        'fullyQualified' => str_starts_with($name, '\\'),
                        'resolved' => self::resolveDocName($name, $uses),
                    ];
                }
            }
        }

        return $refs;
    }

    /**
     * The type of a tag of a docblock: the text up to a space outside brackets
     *
     * @param string $doc The docblock
     * @param int $start The beginning of the type
     * @return string
     */
    private static function typeAt(string $doc, int $start): string
    {
        $depth = 0;
        $length = strlen($doc);
        for ($i = $start; $i < $length; $i++) {
            $char = $doc[$i];
            if (str_contains('<({[', $char)) {
                $depth++;
            } elseif (str_contains('>)}]', $char)) {
                $depth--;
            } elseif ($depth <= 0 && ctype_space($char)) {
                break;
            }
        }

        return substr($doc, $start, $i - $start);
    }

    /**
     * The class that a name of a docblock names, as PHP would resolve it in the code of the file
     *
     * @param string $name The name as written
     * @param array<string, string> $uses The classes of the use statements, by lowercase alias
     * @return string
     */
    private static function resolveDocName(string $name, array $uses): string
    {
        if (str_starts_with($name, '\\')) {
            return substr($name, 1);
        }
        $parts = explode('\\', $name, 2);
        $alias = $uses[strtolower($parts[0])] ?? null;
        if ($alias === null) {
            return $name;
        }

        return isset($parts[1]) ? $alias . '\\' . $parts[1] : $alias;
    }

    /**
     * The new names of the known classes, and the classes to import, by index of the reference
     *
     * @param array<\PhpParser\Node\Stmt> $stmts The statements
     * @param array<array{start: int, end: int, name: string, fullyQualified: bool, resolved: string}> $refs The class
     *   names
     * @param array<string, string> $uses The classes of the use statements, by lowercase alias
     * @param array<string, string> $classes The namespaced names of the classes, by global name
     * @return array<int, array{string, string|null}> The new name, and the class to import or null
     */
    private static function imports(array $stmts, array $refs, array $uses, array $classes): array
    {
        $global = array_change_key_case($classes);
        $known = [];
        foreach ($classes as $class) {
            $known[strtolower($class)] = $class;
        }
        // The short names that name a class already, by lowercase short name
        $taken = $uses;
        foreach ((new NodeFinder())->findInstanceOf($stmts, ClassLike::class) as $classLike) {
            if ($classLike->name !== null) {
                $taken[$classLike->name->toLowerString()] = '';
            }
        }
        $wanted = [];
        foreach ($refs as $index => $ref) {
            $class = self::knownClass($ref, $global, $known);
            if ($class !== null) {
                $wanted[$index] = $class;
            } elseif (!$ref['fullyQualified'] && !str_contains($ref['name'], '\\')) {
                $taken[strtolower($ref['name'])] ??= $ref['resolved'];
            }
        }

        $imports = [];
        foreach ($wanted as $index => $class) {
            $short = substr($class, (int)strrpos('\\' . $class, '\\'));
            $owner = $taken[strtolower($short)] ?? null;
            if ($owner === null) {
                $taken[strtolower($short)] = $class;
                $imports[$index] = [$short, $class];
            } elseif (strtolower($owner) === strtolower($class)) {
                $imports[$index] = [$short, isset($uses[strtolower($short)]) ? null : $class];
            } else {
                $imports[$index] = ['\\' . $class, null];
            }
        }

        return $imports;
    }

    /**
     * The namespaced class of a name, when it is a known class: a global name of the map, or a namespaced name of it
     * written fully qualified
     *
     * @param array{start: int, end: int, name: string, fullyQualified: bool, resolved: string} $ref The name
     * @param array<string, string> $global The namespaced names, by lowercase global name
     * @param array<string, string> $known The namespaced names, by lowercase namespaced name
     * @return string|null
     */
    private static function knownClass(array $ref, array $global, array $known): ?string
    {
        $lower = strtolower(ltrim($ref['resolved'], '\\'));
        if ($ref['fullyQualified'] && isset($known[$lower])) {
            return $known[$lower];
        }

        return !str_contains($lower, '\\') ? $global[$lower] ?? null : null;
    }

    /**
     * The edit that adds the use statements at the top of the file, sorted with the use statements of the file there
     *
     * @param string $code The code
     * @param array<\PhpParser\Node\Stmt> $stmts The statements
     * @param array<string> $classes The classes to import
     * @return array{int, int, string} The start and the end of the replaced text, and the new text
     */
    private static function useBlock(string $code, array $stmts, array $classes): array
    {
        $eol = str_contains($code, "\r\n") ? "\r\n" : "\n";
        $new = array_map(static fn(string $class): array => [new Use_([new Node\UseItem(new Name($class))]), 'use ' . $class . ';'], $classes);
        $offset = 0;
        if (isset($stmts[0]) && $stmts[0] instanceof InlineHTML && str_starts_with($code, '#!')) {
            $offset = (int)strpos($code, "\n") + 1;
            array_shift($stmts);
        }
        if (!preg_match('/\G<\?php\r?\n/', $code, $match, 0, $offset)) {
            return [$offset, $offset, '<?php' . $eol . implode($eol, UseOrder::sort($new)) . $eol . '?>' . $eol];
        }

        $start = $offset + 5;
        $first = null;
        $existing = [];
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Declare_ && $stmt->stmts === null && $first === null) {
                $start = $stmt->getEndFilePos() + 1;
                continue;
            }
            if (($stmt instanceof Use_ || $stmt instanceof GroupUse) && ($existing === [] || $stmt->getComments() === [])) {
                $first ??= $stmt;
                $existing[] = [$stmt, substr($code, $stmt->getStartFilePos(), $stmt->getEndFilePos() - $stmt->getStartFilePos() + 1)];
                continue;
            }
            $first ??= $stmt;
            break;
        }
        if ($existing !== []) {
            $useStart = $existing[0][0]->getStartFilePos();
            $useEnd = end($existing)[0]->getEndFilePos() + 1;

            return [$useStart, $useEnd, implode($eol, UseOrder::sort(array_merge($existing, $new)))];
        }
        $comments = $first instanceof Stmt ? $first->getComments() : [];
        if ($comments !== [] && $comments[0] instanceof Doc) {
            $start = $comments[0]->getEndFilePos() + 1;
        }
        $length = strspn($code, " \t\r\n", $start);

        return [$start, $start + $length, $eol . $eol . implode($eol, UseOrder::sort($new)) . $eol . $eol];
    }
}
