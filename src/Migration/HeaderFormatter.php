<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Migration;

use PhpParser\Comment;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Use_;
use PhpParser\ParserFactory;

/**
 * Orders the beginning of a file that `upgrade namespace` changed, as the files of CakePHP are written:
 *
 * ```
 * <?php
 * /**
 *  * The docblock of the file
 *  *\/
 *
 * namespace App\Model;
 *
 * use Cake\Model\Model;
 *
 * /**
 *  * The docblock of the class
 *  *\/
 * class Post extends AppModel
 * ```
 *
 * Rector keeps the formatting of the statements that it does not change, so it prints the new namespace and use
 * statements wherever the old statements were, around the docblock of the file. The rest of the file stays as it is.
 * A file without a namespace (`config/core.php`) is only changed when it starts with use statements. The use
 * statements are sorted, as the CakePHP coding standard expects (see UseOrder).
 */
final class HeaderFormatter
{
    /**
     * Orders the beginning of the code
     *
     * @param string $code The code of a file with one namespace without braces
     * @return string The code
     */
    public static function format(string $code): string
    {
        if (!str_starts_with($code, '<?php')) {
            return $code;
        }
        try {
            $stmts = (new ParserFactory())->createForNewestSupportedVersion()->parse($code) ?? [];
        } catch (Error) {
            return $code;
        }
        $head = self::head($stmts);
        if ($head === null) {
            return $code;
        }
        [$declares, $namespace, $uses, $first, $docComments] = $head;
        $comments = $first->getComments();
        if ($docComments === [] && $comments !== [] && (!$first instanceof ClassLike || count($comments) > 1)) {
            $docComments[] = array_shift($comments);
        }
        // A comment keeps the indentation of its first line, as in config/routes.php of CakePHP 2
        $indent = static function (Comment $comment) use ($code): string {
            $start = $comment->getStartFilePos();
            $lineStart = strrpos(substr($code, 0, $start), "\n");
            $indent = substr($code, $lineStart === false ? 0 : $lineStart + 1, $start - ($lineStart === false ? 0 : $lineStart + 1));

            return trim($indent) === '' ? $indent : '';
        };
        $docs = array_map(static fn(Comment $comment): string => $indent($comment) . $comment->getText(), $docComments);
        $restStart = $comments !== [] ? $comments[0]->getStartFilePos() : $first->getStartFilePos();
        $lineStart = strrpos(substr($code, 0, $restStart), "\n");
        $rest = substr($code, $lineStart === false ? $restStart : $lineStart + 1);

        $eol = str_contains($code, "\r\n") ? "\r\n" : "\n";
        $text = static fn(Node $node): string => substr($code, $node->getStartFilePos(), $node->getEndFilePos() - $node->getStartFilePos() + 1);
        // The use statements get the indentation of the docblock of the file, as config/routes.php of pieceofcake2/app
        $useIndent = $docComments !== [] ? $indent($docComments[0]) : '';
        $useTexts = UseOrder::sort(array_map(static fn(Node\Stmt $use): array => [$use, $useIndent . $text($use)], $uses));
        $blocks = array_filter([
            implode($eol, $docs),
            implode($eol, array_map($text, $declares)),
            $namespace !== null ? 'namespace ' . $namespace . ';' : '',
            implode($eol, $useTexts),
        ], static fn(string $block): bool => $block !== '');

        return '<?php' . $eol . ($docs === [] ? $eol : '') . implode($eol . $eol, $blocks) . $eol . $eol . $rest;
    }

    /**
     * The statements of the beginning of a file
     *
     * @param array<\PhpParser\Node\Stmt> $stmts The statements of the file
     * @return array{array<\PhpParser\Node\Stmt>, string|null, array<\PhpParser\Node\Stmt>, \PhpParser\Node\Stmt, array<\PhpParser\Comment>}|null
     *   The declare statements, the namespace, the use statements, the first other statement and the comments before
     *   it, or null when the file cannot be formatted
     */
    private static function head(array $stmts): ?array
    {
        $declares = [];
        $comments = [];
        $namespace = null;
        $body = [];
        foreach ($stmts as $index => $stmt) {
            if ($stmt instanceof Declare_ && $stmt->stmts === null) {
                $declares[] = $stmt;
            } elseif ($stmt instanceof Namespace_ && $stmt->name !== null && $stmt->getAttribute('kind') === Namespace_::KIND_SEMICOLON && count($stmts) === $index + 1) {
                $namespace = $stmt;
                $body = $stmt->stmts;
            } elseif ($stmt instanceof Namespace_) {
                return null;
            } elseif (!$stmt instanceof Nop) {
                $body = array_slice($stmts, $index);
                break;
            }
            $comments = array_merge($comments, $stmt->getComments());
        }
        $uses = [];
        foreach ($body as $stmt) {
            if ($stmt instanceof Namespace_) {
                return null;
            }
            if ($stmt instanceof Use_ || $stmt instanceof GroupUse) {
                $uses[] = $stmt;
                $comments = array_merge($comments, $stmt->getComments());
            } elseif (!$stmt instanceof Nop) {
                if ($namespace === null && $uses === []) {
                    return null;
                }

                return [
                    $declares,
                    $namespace?->name?->toString(),
                    $uses,
                    $stmt,
                    $comments,
                ];
            }
        }

        return null;
    }
}
