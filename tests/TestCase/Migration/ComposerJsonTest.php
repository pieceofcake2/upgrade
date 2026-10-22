<?php
/**
 * ComposerJsonTest file
 *
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use PieceOfCake2\Upgrade\Migration\ComposerJson;

/**
 * The changes of composer.json, which keep the rest of the file as it is written
 */
class ComposerJsonTest extends CakeTestCase
{
    /**
     * The paths in moved directories, in the autoloading and in extra.installer-paths; a path whose files moved to
     * different places is listed
     *
     * @return void
     */
    public function testMovePaths(): void
    {
        $json = <<<'JSON'
{
    "autoload": {
        "classmap": ["app/Controller/", "app/Model", "app/View/", "vendors/"],
        "psr-0": {"Legacy_": ["app/Lib/", "lib/"]},
        "files": ["app/Config/functions.php"]
    },
    "extra": {
        "installer-paths": {
            "app/Plugin/{$name}/": ["type:cakephp-plugin"],
            "vendor/{$name}/": ["type:library"]
        }
    }
}
JSON;
        $composer = new ComposerJson($json);

        [$changes, $unknown] = $composer->movePaths([
            'app/Config' => 'config',
            'app/Controller' => 'src/Controller',
            'app/Model' => 'src/Model',
            'app/Lib' => 'src/Lib',
            'app/View/Helper' => 'src/View/Helper',
            'app/Plugin' => 'plugins',
        ]);

        $this->assertSame([
            'autoload.classmap: app/Controller/ => src/Controller/',
            'autoload.classmap: app/Model => src/Model',
            'autoload.psr-0: app/Lib/ => src/Lib/',
            'autoload.files: app/Config/functions.php => config/functions.php',
            'extra.installer-paths: app/Plugin/{$name}/ => plugins/{$name}/',
        ], $changes);
        $this->assertSame(['autoload.classmap: app/View/'], $unknown);
        $data = json_decode($composer->contents(), true);
        $this->assertSame(['src/Controller/', 'src/Model', 'app/View/', 'vendors/'], $data['autoload']['classmap']);
        $this->assertSame(['src/Lib/', 'lib/'], $data['autoload']['psr-0']['Legacy_']);
        $this->assertSame(['config/functions.php'], $data['autoload']['files']);
        $this->assertSame(['plugins/{$name}/', 'vendor/{$name}/'], array_keys($data['extra']['installer-paths']));
    }

    /**
     * Nothing changes, the text stays
     *
     * @return void
     */
    public function testUnchanged(): void
    {
        $json = '{"autoload": {"psr-4": {"App\\\\": "src/"}}, "name": "x"}';
        $composer = new ComposerJson($json);

        $this->assertSame([[], []], $composer->movePaths(['app/Controller' => 'src/Controller']));
        $this->assertFalse($composer->isChanged());
        $this->assertSame($json, $composer->contents());
    }

    /**
     * Not JSON
     *
     * @return void
     */
    public function testInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ComposerJson('{"name": ');
    }
}
