<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Nop;
use Rector\PhpParser\Enum\NodeGroup;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Removes the statements `App::uses('Class', 'Package');`.
 *
 * Composer loads the classes of a namespaced application and of the namespaced plugins, and pieceofcake2/cakephp 2.13
 * deprecates `App::uses()`. The comments of a removed statement move to the next statement, so that the docblock of a
 * file is kept.
 */
final class RemoveAppUsesRector extends AbstractRector
{
    /**
     * @return \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Remove the App::uses() statements', [
            new CodeSample(
                <<<'CODE_SAMPLE'
App::uses('AppController', 'Controller');

class PostsController extends AppController
{
}
CODE_SAMPLE,
                <<<'CODE_SAMPLE'
class PostsController extends AppController
{
}
CODE_SAMPLE,
            ),
        ]);
    }

    /**
     * @return array<class-string<\PhpParser\Node>>
     */
    public function getNodeTypes(): array
    {
        return NodeGroup::STMTS_AWARE;
    }

    /**
     * @param \PhpParser\Node $node A node with statements
     * @return \PhpParser\Node|null
     */
    public function refactor(Node $node): ?Node
    {
        if (!property_exists($node, 'stmts') || !is_array($node->stmts)) {
            return null;
        }
        $stmts = $node->stmts;
        $kept = [];
        $comments = [];
        foreach ($stmts as $stmt) {
            if ($this->isAppUses($stmt)) {
                $comments = array_merge($comments, $stmt->getComments());
                continue;
            }
            if ($comments !== []) {
                $stmt->setAttribute('comments', array_merge($comments, $stmt->getComments()));
                $comments = [];
            }
            $kept[] = $stmt;
        }
        if (count($kept) === count($stmts)) {
            return null;
        }
        if ($comments !== []) {
            $kept[] = new Nop(['comments' => $comments]);
        }
        $node->stmts = $kept;

        return $node;
    }

    /**
     * @param \PhpParser\Node\Stmt $stmt Statement
     * @return bool
     */
    private function isAppUses(Node $stmt): bool
    {
        return $stmt instanceof Expression
            && $stmt->expr instanceof StaticCall
            && $this->isNames($stmt->expr->class, ['App', 'Cake\Core\App'])
            && $this->isName($stmt->expr->name, 'uses');
    }
}
