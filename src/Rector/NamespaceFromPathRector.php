<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Namespace_;
use PHPStan\PhpDocParser\Ast\Node as PhpDocNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\Type\Generic\TemplateType;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\PhpDocParser\PhpDocParser\PhpDocNodeTraverser;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Rector\StaticTypeMapper\StaticTypeMapper;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Declares the namespace of the classes of a file, which the upgrade shell derives from the path of the file, e.g.
 * `App\Model` for `src/Model/Post.php`.
 *
 * The names of the classes of the file do not change, so the other rules (`RenameClassRector`) rename the uses of the
 * classes in the other files. The global names in docblocks get a leading backslash, e.g. `@param \ArrayObject`, so
 * that they still name the global classes in the namespace; DocblockNameImportRector then imports them, as Rector
 * imports the names in the code.
 *
 * A file that declares a copy of a class of CakePHP in the `Cake` namespace (a core class override) also gets the
 * `class_alias()` of the global name, as the files of CakePHP have.
 */
final class NamespaceFromPathRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * Configuration key: the namespaces, by path of the file
     *
     * @var string
     */
    public const FILES = 'files';

    /**
     * Configuration key: the paths of the files whose classes also get a `class_alias()` of their global name
     *
     * @var string
     */
    public const ALIASES = 'aliases';

    /**
     * The namespaces, by real path of the file
     *
     * @var array<string, string>
     */
    private array $files = [];

    /**
     * The real paths of the files with aliases
     *
     * @var array<string, true>
     */
    private array $aliases = [];

    /**
     * @param \Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory $phpDocInfoFactory Docblocks
     * @param \Rector\Comments\NodeDocBlock\DocBlockUpdater $docBlockUpdater Prints the changed docblocks
     * @param \Rector\StaticTypeMapper\StaticTypeMapper $staticTypeMapper Types of the docblocks
     */
    public function __construct(
        private PhpDocInfoFactory $phpDocInfoFactory,
        private DocBlockUpdater $docBlockUpdater,
        private StaticTypeMapper $staticTypeMapper,
    ) {
    }

    /**
     * @param array<string, mixed> $configuration `files`: the namespaces by path, `aliases`: the paths of the
     *   core class overrides
     * @return void
     */
    public function configure(array $configuration): void
    {
        $this->files = [];
        foreach ((array)($configuration[self::FILES] ?? []) as $file => $namespace) {
            $this->files[$this->normalize((string)$file)] = (string)$namespace;
        }
        $this->aliases = [];
        foreach ((array)($configuration[self::ALIASES] ?? []) as $file) {
            $this->aliases[$this->normalize((string)$file)] = true;
        }
    }

    /**
     * @return \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Declare the namespace of the classes of a file from the path of the file', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
<?php
class Post extends AppModel
{
    /**
     * @param ArrayObject $data Data
     */
    public function import($data)
    {
    }
}
CODE_SAMPLE,
                <<<'CODE_SAMPLE'
<?php

namespace App\Model;

class Post extends AppModel
{
    /**
     * @param \ArrayObject $data Data
     */
    public function import($data)
    {
    }
}
CODE_SAMPLE,
                [self::FILES => ['src/Model/Post.php' => 'App\Model']],
            ),
        ]);
    }

    /**
     * @return array<class-string<\PhpParser\Node>>
     */
    public function getNodeTypes(): array
    {
        return [FileNode::class];
    }

    /**
     * @param \Rector\PhpParser\Node\FileNode $node File
     * @return \PhpParser\Node|null
     */
    public function refactor(Node $node): ?Node
    {
        $path = $this->normalize($this->file->getFilePath());
        if (!isset($this->files[$path])) {
            return null;
        }
        $classes = [];
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Namespace_) {
                return null;
            }
            if ($stmt instanceof ClassLike && $stmt->name !== null) {
                $classes[] = $stmt->name->toString();
            }
        }
        if ($classes === []) {
            return null;
        }

        $this->qualifyDocblockNames($node->stmts);

        // The namespace comes before the other statements, which keep their formatting; the upgrade shell then
        // orders the docblock of the file, the namespace and the use statements
        $declares = [];
        $stmts = $node->stmts;
        while ($stmts !== [] && $stmts[0] instanceof Declare_) {
            $declares[] = array_shift($stmts);
        }
        if (isset($this->aliases[$path])) {
            foreach ($classes as $class) {
                $stmts[] = new Expression(new FuncCall(new Name('class_alias'), [
                    new Arg(new ClassConstFetch(new Name($class), 'class')),
                    new Arg(new String_($class)),
                ]));
            }
        }
        $node->stmts = [...$declares, new Namespace_(new Name($this->files[$path]), []), ...$stmts];

        return $node;
    }

    /**
     * Adds a leading backslash to the global class names of the docblocks, e.g. `@param \ArrayObject`
     *
     * @param array<\PhpParser\Node> $nodes The statements of the file
     * @return void
     */
    private function qualifyDocblockNames(array $nodes): void
    {
        $this->traverseNodesWithCallable($nodes, function (Node $node) {
            if ($node->getDocComment() === null) {
                return null;
            }
            $phpDocInfo = $this->phpDocInfoFactory->createFromNode($node);
            if ($phpDocInfo === null) {
                return null;
            }
            $changed = false;
            $traverser = new PhpDocNodeTraverser();
            $traverser->traverseWithCallable($phpDocInfo->getPhpDocNode(), '', function (PhpDocNode $docNode) use ($node, &$changed): ?PhpDocNode {
                if (!$docNode instanceof IdentifierTypeNode || str_starts_with($docNode->name, '\\')) {
                    return null;
                }
                if (in_array(strtolower($docNode->name), ['self', 'static', 'parent', '$this'], true)) {
                    return null;
                }
                $type = $this->staticTypeMapper->mapPHPStanPhpDocTypeNodeToPHPStanType($docNode, $node);
                if ($type->getObjectClassNames() === [] || $type instanceof TemplateType) {
                    return null;
                }
                $changed = true;

                return new IdentifierTypeNode('\\' . $docNode->name);
            });
            if ($changed) {
                $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($node);
            }

            return null;
        });
    }

    /**
     * @param string $path Path of a file
     * @return string The real path, with slashes
     */
    private function normalize(string $path): string
    {
        return str_replace('\\', '/', realpath($path) ?: $path);
    }
}
