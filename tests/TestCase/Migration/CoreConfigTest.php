<?php
/**
 * CoreConfigTest file
 *
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use PieceOfCake2\Upgrade\Migration\CoreConfig;

/**
 * App.namespace in config/core.php
 */
class CoreConfigTest extends CakeTestCase
{
    /**
     * Before the docblock of App.encoding, with the rest of the file as it is
     *
     * @return void
     */
    public function testBeforeEncoding(): void
    {
        $core = <<<'PHP'
<?php
/**
 * Configure the Exception handler
 */
Configure::write('Exception', [
    'renderer' => 'ExceptionRenderer',
]);

/**
 * Application wide charset encoding
 */
Configure::write('App.encoding', 'UTF-8');

Cache::config('_cake_core_', []);
PHP;

        [$result, $contents, $line] = CoreConfig::addNamespace($core, 'App');

        $this->assertSame(CoreConfig::ADDED, $result);
        $this->assertSame("Configure::write('App.encoding', 'UTF-8');", $line);
        $this->assertSame(<<<'PHP'
<?php
/**
 * Configure the Exception handler
 */
Configure::write('Exception', [
    'renderer' => 'ExceptionRenderer',
]);

/**
 * The namespace of the classes of the application
 */
Configure::write('App.namespace', 'App');

/**
 * Application wide charset encoding
 */
Configure::write('App.encoding', 'UTF-8');

Cache::config('_cake_core_', []);
PHP, $contents);
    }

    /**
     * A commented App.encoding does not count; without a docblock the setting goes right before the line; the line
     * endings of the file are kept
     *
     * @return void
     */
    public function testWithoutDocblock(): void
    {
        $core = "<?php\r\n//Configure::write('App.encoding', 'ISO-8859-1');\r\n\$x = 1;\r\nConfigure::write(\"App.encoding\", 'UTF-8');\r\n";

        [$result, $contents] = CoreConfig::addNamespace($core, 'Acme\Shop');

        $this->assertSame(CoreConfig::ADDED, $result);
        $this->assertSame(
            "<?php\r\n//Configure::write('App.encoding', 'ISO-8859-1');\r\n\$x = 1;\r\n/**\r\n * The namespace of the classes of the application\r\n */\r\n" .
            "Configure::write('App.namespace', 'Acme\Shop');\r\n\r\nConfigure::write(\"App.encoding\", 'UTF-8');\r\n",
            $contents,
        );
    }

    /**
     * Without App.encoding, before the first Cache::config() and its docblock
     *
     * @return void
     */
    public function testBeforeCacheConfig(): void
    {
        $core = "<?php\n\$engine = 'File';\n\n/**\n * The core cache\n */\nCache::config('_cake_core_', []);\nCache::config('_cake_model_', []);\n";

        [$result, $contents, $line] = CoreConfig::addNamespace($core, 'App');

        $this->assertSame(CoreConfig::ADDED, $result);
        $this->assertSame("Cache::config('_cake_core_', []);", $line);
        $this->assertSame(
            "<?php\n\$engine = 'File';\n\n/**\n * The namespace of the classes of the application\n */\nConfigure::write('App.namespace', 'App');\n\n" .
            "/**\n * The core cache\n */\nCache::config('_cake_core_', []);\nCache::config('_cake_model_', []);\n",
            $contents,
        );
    }

    /**
     * Without App.encoding and Cache::config(), nothing is added
     *
     * @return void
     */
    public function testNoPlace(): void
    {
        $core = "<?php\nConfigure::write('debug', 0);\n// Cache::config('default', []);\n";

        $this->assertSame([CoreConfig::NO_PLACE, $core, null], CoreConfig::addNamespace($core, 'App'));
    }

    /**
     * Already set: the same namespace, or another one, in single or double quotes
     *
     * @return void
     */
    public function testAlreadySet(): void
    {
        $core = "<?php\nConfigure::write('App.namespace', 'App');\nConfigure::write('App.encoding', 'UTF-8');\n";
        $this->assertSame([CoreConfig::SAME, $core, 'App'], CoreConfig::addNamespace($core, 'App'));

        $core = "<?php\n    Configure::write(\"App.namespace\", \"Acme\\\\Shop\");\nConfigure::write('App.encoding', 'UTF-8');\n";
        $this->assertSame([CoreConfig::SAME, $core, 'Acme\Shop'], CoreConfig::addNamespace($core, 'Acme\Shop'));
        $this->assertSame([CoreConfig::DIFFERENT, $core, 'Acme\Shop'], CoreConfig::addNamespace($core, 'App'));

        $core = "<?php\nConfigure::write('App.namespace', env('APP_NAMESPACE'));\n";
        $this->assertSame([CoreConfig::DIFFERENT, $core, "env('APP_NAMESPACE')"], CoreConfig::addNamespace($core, 'App'));
    }
}
