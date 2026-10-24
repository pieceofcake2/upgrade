<?php
/**
 * ReferencesTest file
 *
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use PieceOfCake2\Upgrade\Migration\References;

App::uses('Folder', 'Utility');

/**
 * The lines that name paths of the old layout, for a review
 */
class ReferencesTest extends CakeTestCase
{
    /**
     * @var string
     */
    protected $_root;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->_root = rtrim((string)realpath(sys_get_temp_dir()), DS) . DS . 'upgrade-references-test-' . getmypid();
        (new Folder($this->_root))->delete();
        mkdir($this->_root, 0777, true);
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        (new Folder($this->_root))->delete();
        parent::tearDown();
    }

    /**
     * @param string $file Path relative to the root
     * @param string $contents Contents
     * @return void
     */
    protected function _write(string $file, string $contents): void
    {
        $path = $this->_root . DS . str_replace('/', DS, $file);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $contents);
    }

    /**
     * The template names and directories of CakePHP 2, but not the words alone or the new names
     *
     * @return void
     */
    public function testTemplates(): void
    {
        $matches = References::matches(implode("\n", [
            "'templates/Pages/home.ctp'",
            '$this->ext = \'.thtml\';',
            "'Layouts' . DS . 'dev_error'",
            'View/Elements/menu',
            'Errors\\error400',
            'templates/layout/default.php',
            'MyLayouts/default',
            'Errors are logged',
            'home.ctpx',
        ]), References::TEMPLATES);

        $this->assertSame([1, 2, 3, 4, 5], array_keys($matches));
    }

    /**
     * The names that `upgrade namespace` leaves behind; `src/Lib/` itself only when it is gone
     *
     * @return void
     */
    public function testNamespacePattern(): void
    {
        $text = implode("\n", [
            ' * autoloaded by the Composer classmap',
            ' * (run `composer dump-autoload` after adding it)',
            ' * in src/Lib/Error.',
            ' * in app/Lib/Network/Email.',
            ' * in src/Lib.',
            ' * in src/Lib/Markdown.php',
            ' * in src/Error.',
        ]);

        $this->assertSame([1, 2, 3, 4], array_keys(References::matches($text, References::namespacePattern(false))));
        $this->assertSame([1, 2, 3, 4, 5, 6], array_keys(References::matches($text, References::namespacePattern(true))));
    }

    /**
     * A long line is shortened around the match
     *
     * @return void
     */
    public function testLongLine(): void
    {
        $line = '    <small>' . str_repeat('x', 200) . ' templates/Pages/home.ctp ' . str_repeat('y', 200) . '</small>';

        $matches = References::matches($line, References::TEMPLATES);

        $this->assertStringStartsWith('...', $matches[1]);
        $this->assertStringEndsWith('...', $matches[1]);
        $this->assertStringContainsString(' templates/Pages/home.ctp ', $matches[1]);
        $this->assertLessThanOrEqual(116, strlen($matches[1]));
    }

    /**
     * The files of the directories, sorted, without the skipped directories and the binary files; in a dry run,
     * the files that the moves bring into the directories, at their new places
     *
     * @return void
     */
    public function testFind(): void
    {
        $this->_write('config/routes.php', "<?php\n// templates/Pages/home.ctp\n");
        $this->_write('templates/Pages/home.php', "<p>Layouts/default.ctp</p>\n");
        $this->_write('src/Console/Templates/default/views/index.ctp', "<?php include 'form.ctp'; ?>\n");
        $this->_write('src/Lib/image.png', "\0\0Layouts/default.ctp");
        $this->_write('webroot/index.php', "<?php // home.ctp\n");
        $this->_write('app/View/Posts/index.ctp', "<?php echo \$this->element('Elements/menu'); ?>\n");

        $lines = References::find($this->_root, ['templates', 'config', 'src'], References::TEMPLATES, ['src/Console/Templates']);

        $this->assertSame([
            'config/routes.php:2: // templates/Pages/home.ctp',
            'templates/Pages/home.php:1: <p>Layouts/default.ctp</p>',
        ], $lines);

        $moves = [[$this->_root . DS . 'app' . DS . 'View' . DS . 'Posts' . DS . 'index.ctp', $this->_root . DS . 'templates' . DS . 'Posts' . DS . 'index.php']];
        $lines = References::find($this->_root, ['templates'], References::TEMPLATES, [], $moves);

        $this->assertSame([
            'templates/Pages/home.php:1: <p>Layouts/default.ctp</p>',
            "templates/Posts/index.php:1: <?php echo \$this->element('Elements/menu'); ?>",
        ], $lines);
    }
}
