<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Migration;

use PhpParser\Error;
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
 * A file without a namespace (`config/core.php`) is only changed when it starts with use statements.
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
        $declares = [];
        $header = [];
        $namespace = null;
        $body = [];
        foreach ($stmts as $index => $stmt) {
            if ($stmt instanceof Declare_ && $stmt->stmts === null) {
                $declares[] = $stmt;
            } elseif ($stmt instanceof Nop) {
                $header[] = $stmt;
            } elseif ($stmt instanceof Namespace_ && $stmt->name !== null && $stmt->getAttribute('kind') === Namespace_::KIND_SEMICOLON && count($stmts) === $index + 1) {
                $namespace = $stmt;
                $body = $stmt->stmts;
            } elseif ($stmt instanceof Namespace_) {
                return $code;
            } else {
                $body = array_slice($stmts, $index);
                break;
            }
        }
        $uses = [];
        $first = null;
        foreach ($body as $stmt) {
            if ($stmt instanceof Namespace_) {
                return $code;
            }
            if ($first === null && ($stmt instanceof Use_ || $stmt instanceof GroupUse)) {
                $uses[] = $stmt;
            } elseif ($first === null && !$stmt instanceof Nop) {
                $first = $stmt;
            }
        }
        if ($first === null || ($namespace === null && $uses === [])) {
            return $code;
        }

        $docs = [];
        foreach (array_filter([...$header, ...$declares, $namespace]) as $stmt) {
            foreach ($stmt->getComments() as $comment) {
                $docs[] = $comment->getText();
            }
        }
        foreach ($uses as $use) {
            foreach ($use->getComments() as $comment) {
                $docs[] = $comment->getText();
            }
        }
        $comments = $first->getComments();
        if ($docs === [] && $comments !== [] && (!$first instanceof ClassLike || count($comments) > 1)) {
            $docs[] = array_shift($comments)->getText();
        }
        $restStart = $comments !== [] ? $comments[0]->getStartFilePos() : $first->getStartFilePos();
        $lineStart = strrpos(substr($code, 0, $restStart), "\n");
        $rest = substr($code, $lineStart === false ? $restStart : $lineStart + 1);

        $eol = str_contains($code, "\r\n") ? "\r\n" : "\n";
        $text = static fn($node): string => substr($code, $node->getStartFilePos(), $node->getEndFilePos() - $node->getStartFilePos() + 1);
        $blocks = [];
        if ($docs !== []) {
            $blocks[] = implode($eol, $docs);
        }
        if ($declares !== []) {
            $blocks[] = implode($eol, array_map($text, $declares));
        }
        if ($namespace !== null && $namespace->name !== null) {
            $blocks[] = 'namespace ' . $namespace->name->toString() . ';';
        }
        if ($uses !== []) {
            $blocks[] = implode($eol, array_map($text, $uses));
        }

        return '<?php' . $eol . ($docs === [] ? $eol : '') . implode($eol . $eol, $blocks) . $eol . $eol . $rest;
    }
}
