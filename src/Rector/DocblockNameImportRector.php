<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PHPStan\PhpDocParser\Ast\Node as PhpDocNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\PhpDocParser\PhpDocParser\PhpDocNodeTraverser;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Rector\StaticTypeMapper\ValueObject\Type\FullyQualifiedObjectType;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Imports the fully qualified class names of the docblocks with `use` statements, as Rector imports the names of the
 * code: `@param \Cake\Controller\Controller` becomes `@param Controller` with `use Cake\Controller\Controller;`.
 *
 * Rector imports a docblock name only when it finds the class, so its result would depend on the installed version of
 * pieceofcake2/cakephp. This rule imports the classes of its configuration, which it does not look up, and, in a
 * namespaced file, the global classes (`@param \ArrayObject`). A name stays fully qualified when its short name is
 * taken: by another `use` statement, by a class of the file, or by another known class of the namespace of the file.
 * Register it after RenameClassRector, which writes the new names fully qualified.
 */
final class DocblockNameImportRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * Configuration key: the namespaced classes to import
     *
     * @var string
     */
    public const CLASSES = 'classes';

    /**
     * Configuration key: whether the global classes are imported in namespaced files
     *
     * @var string
     */
    public const GLOBALS = 'globals';

    /**
     * The classes to import, by lowercase name
     *
     * @var array<string, string>
     */
    private array $classes = [];

    /**
     * @var bool
     */
    private bool $globals = false;

    /**
     * @param \Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory $phpDocInfoFactory Docblocks
     * @param \Rector\Comments\NodeDocBlock\DocBlockUpdater $docBlockUpdater Prints the changed docblocks
     */
    public function __construct(private PhpDocInfoFactory $phpDocInfoFactory, private DocBlockUpdater $docBlockUpdater)
    {
    }

    /**
     * @param array<string, mixed> $configuration `classes`: the namespaced classes, `globals`: whether to import the
     *   global classes in namespaced files
     * @return void
     */
    public function configure(array $configuration): void
    {
        $this->classes = [];
        foreach ((array)($configuration[self::CLASSES] ?? []) as $class) {
            $class = ltrim((string)$class, '\\');
            $this->classes[strtolower($class)] = $class;
        }
        $this->globals = (bool)($configuration[self::GLOBALS] ?? false);
    }

    /**
     * @return \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Import the fully qualified class names of the docblocks', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
/**
 * @param \Cake\Controller\Controller $controller Controller
 */
function startup($controller)
{
}
CODE_SAMPLE,
                <<<'CODE_SAMPLE'
use Cake\Controller\Controller;

/**
 * @param Controller $controller Controller
 */
function startup($controller)
{
}
CODE_SAMPLE,
                [self::CLASSES => ['Cake\Controller\Controller']],
            ),
        ]);
    }

    /**
     * @return array<class-string<\PhpParser\Node>>
     */
    public function getNodeTypes(): array
    {
        return [Stmt::class];
    }

    /**
     * @param \PhpParser\Node\Stmt $node Statement
     * @return \PhpParser\Node|null
     */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof FileNode || $node->getDocComment() === null || $this->file->containsHTML()) {
            return null;
        }
        $fileNode = $this->file->getFileNode();
        $phpDocInfo = $this->phpDocInfoFactory->createFromNode($node);
        if (!$fileNode instanceof FileNode || $phpDocInfo === null) {
            return null;
        }
        $namespace = $fileNode->getNamespace()?->name?->toString();
        $changed = false;
        $traverser = new PhpDocNodeTraverser();
        $traverser->traverseWithCallable($phpDocInfo->getPhpDocNode(), '', function (PhpDocNode $docNode) use ($fileNode, $namespace, &$changed): ?PhpDocNode {
            if (!$docNode instanceof IdentifierTypeNode || !str_starts_with($docNode->name, '\\')) {
                return null;
            }
            $class = $this->importable(ltrim($docNode->name, '\\'), $namespace);
            if ($class === null || !$this->import($fileNode, $class, $namespace)) {
                return null;
            }
            $changed = true;

            return new IdentifierTypeNode(substr($class, (int)strrpos('\\' . $class, '\\')));
        });
        if (!$changed) {
            return null;
        }
        $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($node);

        return $node;
    }

    /**
     * The class to import for a fully qualified name, if any
     *
     * @param string $name The name without the leading backslash
     * @param string|null $namespace The namespace of the file
     * @return string|null
     */
    private function importable(string $name, ?string $namespace): ?string
    {
        if (str_contains($name, '\\')) {
            return $this->classes[strtolower($name)] ?? null;
        }

        return $this->globals && $namespace !== null && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) ? $name : null;
    }

    /**
     * Adds the `use` statement of a class, unless its short name is taken
     *
     * @param \Rector\PhpParser\Node\FileNode $fileNode The file
     * @param string $class The class
     * @param string|null $namespace The namespace of the file
     * @return bool Whether the short name names the class in the file
     */
    private function import(FileNode $fileNode, string $class, ?string $namespace): bool
    {
        $short = substr($class, (int)strrpos('\\' . $class, '\\'));
        $classNamespace = str_contains($class, '\\') ? substr($class, 0, (int)strrpos($class, '\\')) : null;
        if ($classNamespace === $namespace) {
            return true;
        }
        $taken = $this->takenBy($fileNode, $short, $namespace);
        if ($taken !== null) {
            return strtolower($taken) === strtolower($class);
        }
        $type = new FullyQualifiedObjectType($class);
        $pendingImports = $fileNode->getPendingImports();
        if ($pendingImports->isShortImported($type) && !$pendingImports->isImportShortable($type)) {
            return false;
        }
        $pendingImports->addUseImport($type);

        return true;
    }

    /**
     * The class that a short name names in a file already, if any: by a `use` statement, a class of the file or a
     * known class of the namespace of the file ('' for a class of the file, which is never imported)
     *
     * @param \Rector\PhpParser\Node\FileNode $fileNode The file
     * @param string $short The short name
     * @param string|null $namespace The namespace of the file
     * @return string|null
     */
    private function takenBy(FileNode $fileNode, string $short, ?string $namespace): ?string
    {
        foreach ($fileNode->getUses() as $use) {
            foreach ($use->uses as $useUse) {
                $alias = $useUse->alias !== null ? $useUse->alias->toString() : $useUse->name->getLast();
                if (strtolower($alias) === strtolower($short)) {
                    return $useUse->name->toString();
                }
            }
        }
        foreach ((new NodeFinder())->findInstanceOf($fileNode->stmts, ClassLike::class) as $classLike) {
            if ($classLike->name !== null && strtolower($classLike->name->toString()) === strtolower($short)) {
                return '';
            }
        }

        return $this->classes[strtolower(($namespace !== null ? $namespace . '\\' : '') . $short)] ?? null;
    }
}
