<?php
/**
 * NameImporterTest file
 *
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use PieceOfCake2\Upgrade\Migration\NameImporter;

/**
 * The use statements of the templates and of bin/cake
 */
class NameImporterTest extends CakeTestCase
{
    /**
     * The classes of the tests, by global name
     *
     * @var array<string, string>
     */
    private const CLASSES = [
        'Configure' => 'Cake\Core\Configure',
        'Debugger' => 'Cake\Utility\Debugger',
        'Folder' => 'Cake\Utility\Folder',
        'NotFoundException' => 'Cake\Error\NotFoundException',
        'ShellDispatcher' => 'Cake\Console\ShellDispatcher',
        'Slug' => 'App\Utility\Slug',
    ];

    /**
     * A template that does not start with a block of PHP gets a new one at the top
     *
     * @return void
     */
    public function testNewBlock(): void
    {
        $code = "<h2><?php echo h(\$message); ?></h2>\n<?php\nif (\\Cake\\Core\\Configure::read('debug') > 0):\n    echo \$this->element('exception_stack_trace');\nendif;\n";

        $this->assertSame(
            "<?php\nuse Cake\\Core\\Configure;\n?>\n<h2><?php echo h(\$message); ?></h2>\n<?php\nif (Configure::read('debug') > 0):\n    echo \$this->element('exception_stack_trace');\nendif;\n",
            NameImporter::import($code, self::CLASSES),
        );

        $code = "<?= Configure::read('App.title') ?>\n<p><?php echo h(Slug::normalize(\$title)); ?></p>\n";
        $this->assertSame(
            "<?php\nuse Cake\\Core\\Configure;\nuse App\\Utility\\Slug;\n?>\n<?= Configure::read('App.title') ?>\n<p><?php echo h(Slug::normalize(\$title)); ?></p>\n",
            NameImporter::import($code, self::CLASSES),
        );
    }

    /**
     * A template that starts with a block of PHP gets the use statements in it: after its docblock, as in the
     * templates of CakePHP 5, else at its beginning
     *
     * @return void
     */
    public function testFirstBlock(): void
    {
        $code = "<?php\n/**\n * @var \\Cake\\View\\View \$this\n */\nif (!\\Cake\\Core\\Configure::read('debug')):\n    throw new \\Cake\\Error\\NotFoundException();\nendif;\n?>\n<p><?php \\Cake\\Utility\\Debugger::checkSecurityKeys(); ?></p>\n";

        $this->assertSame(
            "<?php\n/**\n * @var \\Cake\\View\\View \$this\n */\nuse Cake\\Core\\Configure;\nuse Cake\\Error\\NotFoundException;\nuse Cake\\Utility\\Debugger;\n\nif (!Configure::read('debug')):\n    throw new NotFoundException();\nendif;\n?>\n<p><?php Debugger::checkSecurityKeys(); ?></p>\n",
            NameImporter::import($code, self::CLASSES),
        );

        $code = "<?php\n\$this->assign('title', \\Cake\\Core\\Configure::read('App.title'));\n?>\n<div></div>\n";
        $this->assertSame(
            "<?php\nuse Cake\\Core\\Configure;\n\n\$this->assign('title', Configure::read('App.title'));\n?>\n<div></div>\n",
            NameImporter::import($code, self::CLASSES),
        );

        $code = "<?php\n/**\n * @var array \$posts\n */\n?>\n<p><?php echo Configure::read('App.title'); ?></p>\n";
        $this->assertSame(
            "<?php\n/**\n * @var array \$posts\n */\nuse Cake\\Core\\Configure;\n?>\n<p><?php echo Configure::read('App.title'); ?></p>\n",
            NameImporter::import($code, self::CLASSES),
        );
    }

    /**
     * The new use statements go after the use statements of the file; an imported class gets its short name
     *
     * @return void
     */
    public function testExistingUses(): void
    {
        $code = "<?php\nuse Cake\\Core\\Configure;\n?>\n<p><?php echo \\Cake\\Core\\Configure::read('a') . Debugger::trace(); ?></p>\n";

        $this->assertSame(
            "<?php\nuse Cake\\Core\\Configure;\nuse Cake\\Utility\\Debugger;\n?>\n<p><?php echo Configure::read('a') . Debugger::trace(); ?></p>\n",
            NameImporter::import($code, self::CLASSES),
        );
    }

    /**
     * A class whose short name is taken stays fully qualified: by a use statement, a class of the file or another
     * class name of the file
     *
     * @return void
     */
    public function testTakenShortNames(): void
    {
        $code = "<?php\nuse Acme\\Configure;\n?>\n<p><?php echo Configure::read('a') . \\Cake\\Core\\Configure::read('b'); ?></p>\n";
        $this->assertSame($code, NameImporter::import($code, self::CLASSES));

        $code = "<p><?php echo \\Cake\\Utility\\Folder::slashTerm(\$a) . Folder::make(); ?></p>\n";
        $this->assertSame(
            "<?php\nuse Cake\\Utility\\Folder;\n?>\n<p><?php echo Folder::slashTerm(\$a) . Folder::make(); ?></p>\n",
            NameImporter::import($code, self::CLASSES),
        );

        $code = "<p><?php echo \\Cake\\Utility\\Debugger::trace() . Debugger::dump(\$a); ?></p>\n";
        $this->assertSame($code, NameImporter::import($code, ['Dbg' => 'Cake\Utility\Debugger']));
    }

    /**
     * Functions, constants, strings and unknown classes stay; a file without a known class does not change
     *
     * @return void
     */
    public function testUnchanged(): void
    {
        $codes = [
            "<h2><?php echo h(\$message); ?></h2>\n",
            "<p><?php echo __('Configure') . Configure(CONFIGURE) . Acme\\Configure::read('a') . Inflector::slug('a'); ?></p>\n",
            "<?php\nnamespace App;\n\nConfigure::read('a');\n",
            "<p><?php echo Configure::read( ?></p>\n",
        ];
        foreach ($codes as $code) {
            $this->assertSame($code, NameImporter::import($code, self::CLASSES));
        }
    }

    /**
     * bin/cake: the use statements go after the docblock, below the #! line, with the blank lines of the file
     *
     * @return void
     */
    public function testBinCake(): void
    {
        $code = "#!/usr/bin/env php\n<?php\n/**\n * The console\n */\n\nrequire_once 'vendor/autoload.php';\n\nShellDispatcher::run(\$argv);\n";

        $this->assertSame(
            "#!/usr/bin/env php\n<?php\n/**\n * The console\n */\n\nuse Cake\\Console\\ShellDispatcher;\n\nrequire_once 'vendor/autoload.php';\n\nShellDispatcher::run(\$argv);\n",
            NameImporter::import($code, self::CLASSES),
        );
    }

    /**
     * The line endings of the file
     *
     * @return void
     */
    public function testCrlf(): void
    {
        $code = "<p><?php echo Configure::read('a'); ?></p>\r\n";

        $this->assertSame("<?php\r\nuse Cake\\Core\\Configure;\r\n?>\r\n<p><?php echo Configure::read('a'); ?></p>\r\n", NameImporter::import($code, self::CLASSES));
    }
}
