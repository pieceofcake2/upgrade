<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Migration;

use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Use_;

/**
 * The order of the use statements that the CakePHP coding standard expects
 * (SlevomatCodingStandard.Namespaces.AlphabeticallySortedUses): the classes, then the functions, then the constants,
 * each by name, compared part by part (`Cake\Core\App` before `Cake\Core\CakePlugin`) and without case.
 */
final class UseOrder
{
    /**
     * Sorts use statements
     *
     * @param array<array{\PhpParser\Node\Stmt, string}> $uses The statements (Use_ or GroupUse) and their texts
     * @return array<string> The texts, sorted
     */
    public static function sort(array $uses): array
    {
        $keys = array_map(static fn(array $use): array => self::key($use[0]), $uses);
        $order = array_keys($uses);
        usort($order, static fn(int $a, int $b): int => self::compare($keys[$a], $keys[$b]) ?: $a <=> $b);

        return array_map(static fn(int $index): string => $uses[$index][1], $order);
    }

    /**
     * The type and the name of a use statement, for the order
     *
     * @param \PhpParser\Node\Stmt $use A Use_ or GroupUse statement
     * @return array{int, string}
     */
    private static function key(Stmt $use): array
    {
        if ($use instanceof GroupUse) {
            $item = $use->uses[0] ?? null;
            $type = $use->type !== Use_::TYPE_UNKNOWN ? $use->type : ($item->type ?? Use_::TYPE_NORMAL);

            return [$type, $use->prefix->toString() . ($item !== null ? '\\' . $item->name->toString() : '')];
        }
        /** @var \PhpParser\Node\Stmt\Use_ $use */
        $item = $use->uses[0] ?? null;

        return [$use->type, $item !== null ? $item->name->toString() : ''];
    }

    /**
     * Compares two use statements
     *
     * @param array{int, string} $a The type and the name of the first one
     * @param array{int, string} $b The type and the name of the second one
     * @return int
     */
    private static function compare(array $a, array $b): int
    {
        // As psr12Compatible of the sniff: classes, functions, constants
        $types = [Use_::TYPE_NORMAL => 1, Use_::TYPE_FUNCTION => 2, Use_::TYPE_CONSTANT => 3];
        if ($a[0] !== $b[0]) {
            return ($types[$a[0]] ?? 1) <=> ($types[$b[0]] ?? 1);
        }
        $aParts = explode('\\', ltrim($a[1], '\\'));
        $bParts = explode('\\', ltrim($b[1], '\\'));
        $count = min(count($aParts), count($bParts));
        for ($i = 0; $i < $count; $i++) {
            $comparison = strcasecmp($aParts[$i], $bParts[$i]);
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return count($aParts) <=> count($bParts);
    }
}
