<?php
/**
 * MoverTest file
 *
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use PieceOfCake2\Upgrade\Migration\Mover;

App::uses('Folder', 'Utility');

/**
 * The moves of files and directories
 */
class MoverTest extends CakeTestCase
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
        $this->_root = rtrim((string)realpath(sys_get_temp_dir()), DS) . DS . 'upgrade-mover-test-' . getmypid();
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
    protected function _write(string $file, string $contents = ''): void
    {
        $path = $this->_root . DS . str_replace('/', DS, $file);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $contents);
    }

    /**
     * A directory moves, also into a new parent directory
     *
     * @return void
     */
    public function testMove(): void
    {
        $this->_write('app/Controller/PostsController.php', 'posts');
        $mover = new Mover($this->_root);

        $mover->move($this->_root . '/app/Controller', $this->_root . '/src/Controller');

        $this->assertSame('posts', file_get_contents($this->_root . '/src/Controller/PostsController.php'));
        $this->assertFileDoesNotExist($this->_root . '/app/Controller');
        $this->assertSame([], $mover->warnings());
        $this->assertFalse($mover->usesGit());
    }

    /**
     * A directory moved onto an existing one is merged; an existing file is not overwritten
     *
     * @return void
     */
    public function testMerge(): void
    {
        $this->_write('app/tmp/cache/a', 'a');
        $this->_write('app/tmp/sessions/s', 's');
        $this->_write('tmp/cache/a', 'kept');
        $this->_write('tmp/cache/b', 'b');
        $mover = new Mover($this->_root);

        $mover->move($this->_root . '/app/tmp', $this->_root . '/tmp');

        $this->assertSame('kept', file_get_contents($this->_root . '/tmp/cache/a'));
        $this->assertSame('b', file_get_contents($this->_root . '/tmp/cache/b'));
        $this->assertSame('s', file_get_contents($this->_root . '/tmp/sessions/s'));
        $this->assertSame('a', file_get_contents($this->_root . '/app/tmp/cache/a'));
        $this->assertFileDoesNotExist($this->_root . '/app/tmp/sessions');
        $this->assertSame(['app/tmp/cache/a was not moved: tmp/cache/a exists'], $mover->warnings());
    }

    /**
     * A rename of the case only, also on case-insensitive file systems
     *
     * @return void
     */
    public function testCase(): void
    {
        $this->_write('Config/bootstrap.php', 'bootstrap');
        $mover = new Mover($this->_root);

        $mover->move($this->_root . '/Config', $this->_root . '/config');

        $this->assertSame(['config'], array_values(array_diff((array)scandir($this->_root), ['.', '..'])));
        $this->assertSame('bootstrap', file_get_contents($this->_root . '/config/bootstrap.php'));
    }

    /**
     * A missing source does nothing; the empty directories are removed
     *
     * @return void
     */
    public function testMissingAndEmpty(): void
    {
        $this->_write('app/Vendor/lib.php');
        mkdir($this->_root . '/app/View/Posts', 0777, true);
        $mover = new Mover($this->_root);

        $mover->move($this->_root . '/app/Nothing', $this->_root . '/src/Nothing');
        $mover->removeEmpty($this->_root . '/app');

        $this->assertFileDoesNotExist($this->_root . '/src');
        $this->assertFileDoesNotExist($this->_root . '/app/View');
        $this->assertFileExists($this->_root . '/app/Vendor/lib.php');
        $this->assertSame('app/Vendor', $mover->relative($this->_root . '/app/Vendor'));
        $this->assertSame('/elsewhere', $mover->relative('/elsewhere'));
    }

    /**
     * In a Git repository, a tracked file moves with git mv, an untracked one with rename()
     *
     * @return void
     */
    public function testGit(): void
    {
        exec('git -C ' . escapeshellarg($this->_root) . ' init -q 2>&1', $output, $code);
        if ($code !== 0) {
            $this->markTestSkipped('git is not available');
        }
        $this->_write('app/Model/Post.php', 'post');
        $this->_write('app/Model/Draft.php', 'draft');
        exec('git -C ' . escapeshellarg($this->_root) . ' add app/Model/Post.php');
        $mover = new Mover($this->_root);

        $mover->move($this->_root . '/app/Model', $this->_root . '/src/Model');

        $this->assertTrue($mover->usesGit());
        exec('git -C ' . escapeshellarg($this->_root) . ' ls-files', $files);
        $this->assertSame(['src/Model/Post.php'], $files);
        $this->assertSame('draft', file_get_contents($this->_root . '/src/Model/Draft.php'));
    }

    /**
     * Whether Git has the contents of a file: tracked, and committed without changes since
     *
     * @return void
     */
    public function testIsCommitted(): void
    {
        exec('git -C ' . escapeshellarg($this->_root) . ' init -q 2>&1', $output, $code);
        if ($code !== 0) {
            $this->markTestSkipped('git is not available');
        }
        $this->_write('webroot/index.php', 'old');
        $this->_write('webroot/test.php', 'old');
        $this->_write('webroot/new.php', 'new');
        $mover = new Mover($this->_root);
        exec('git -C ' . escapeshellarg($this->_root) . ' add webroot/index.php webroot/test.php');
        $this->assertFalse($mover->isCommitted($this->_root . '/webroot/index.php'));

        exec('git -C ' . escapeshellarg($this->_root) . ' -c user.name=Test -c user.email=test@example.com -c commit.gpgsign=false commit -q -m Old');
        $this->_write('webroot/test.php', 'changed');

        $this->assertTrue($mover->isCommitted($this->_root . '/webroot/index.php'));
        $this->assertFalse($mover->isCommitted($this->_root . '/webroot/test.php'));
        $this->assertFalse($mover->isCommitted($this->_root . '/webroot/new.php'));
    }
}
