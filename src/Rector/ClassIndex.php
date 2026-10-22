<?php
declare(strict_types=1);

namespace PieceOfCake2\Upgrade\Rector;

use PhpParser\Error;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Rector\Configuration\Option;
use Rector\Configuration\Parameter\SimpleParameterProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The classes declared in the analysed code, and the classes and interfaces that they extend or implement.
 *
 * A rule of Rector sees one file at a time, so the classes of the other files are read here, once per process, from
 * the paths that Rector processes and from the other paths that a rule is configured with.
 */
final class ClassIndex
{
    /**
     * The indexes, by the list of their paths
     *
     * @var array<string, self>
     */
    private static array $indexes = [];

    /**
     * The declared classes, interfaces and traits: their names, by lowercase name
     *
     * @var array<string, string>
     */
    private array $classes = [];

    /**
     * The extended classes and implemented interfaces, by lowercase name: the classes that extend or implement them
     *
     * @var array<string, array<string>>
     */
    private array $parents = [];

    /**
     * @param array<string> $paths Files and directories
     */
    private function __construct(array $paths)
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $finder = new NodeFinder();
        foreach ($this->files($paths) as $file) {
            try {
                $stmts = $parser->parse((string)file_get_contents($file)) ?? [];
            } catch (Error) {
                continue;
            }
            $stmts = (new NodeTraverser(new NameResolver()))->traverse($stmts);
            foreach ($finder->findInstanceOf($stmts, ClassLike::class) as $classLike) {
                if ($classLike->namespacedName === null) {
                    continue;
                }
                $name = $classLike->namespacedName->toString();
                $this->classes[strtolower($name)] = $name;
                $parents = [];
                if ($classLike instanceof Class_) {
                    $parents = $classLike->implements;
                    if ($classLike->extends !== null) {
                        $parents[] = $classLike->extends;
                    }
                } elseif ($classLike instanceof Interface_) {
                    $parents = $classLike->extends;
                }
                foreach ($parents as $parent) {
                    $this->parents[strtolower($parent->toString())][] = $name;
                }
            }
        }
    }

    /**
     * The index of the paths that Rector processes and of other paths
     *
     * @param array<string> $paths Other files and directories
     * @return self
     */
    public static function create(array $paths = []): self
    {
        $paths = array_merge(SimpleParameterProvider::provideArrayParameter(Option::SOURCE), $paths);
        $paths = array_values(array_unique(array_filter(array_map(static fn($path): string => (string)realpath((string)$path), $paths))));
        sort($paths);
        $key = implode(PATH_SEPARATOR, $paths);

        return self::$indexes[$key] ??= new self($paths);
    }

    /**
     * The declared classes, interfaces and traits
     *
     * @return array<string, string> The names, by lowercase name
     */
    public function classes(): array
    {
        return $this->classes;
    }

    /**
     * Returns the classes that extend a class, or implement an interface
     *
     * @param string $class The name of the class or interface
     * @return array<string> The names of the classes
     */
    public function subclasses(string $class): array
    {
        return $this->parents[strtolower(ltrim($class, '\\'))] ?? [];
    }

    /**
     * The PHP files of the paths, outside the directories of Composer and Git
     *
     * @param array<string> $paths Files and directories
     * @return array<string>
     */
    private function files(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = $path;
                continue;
            }
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $pathname = $file->getPathname();
                if ($file->isFile() && $file->getExtension() === 'php' && !preg_match('#[\\\\/](vendor|node_modules|\.git)[\\\\/]#', substr($pathname, strlen($path)))) {
                    $files[] = $pathname;
                }
            }
        }

        return array_unique($files);
    }
}
