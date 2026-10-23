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
     * The classmap of src/ becomes PSR-4; the other entries and sections keep their text
     *
     * @return void
     */
    public function testPsr4(): void
    {
        $json = <<<'JSON'
{
    "name": "acme/app",
    "require": {"php": ">=8.0"},
    "autoload": {
        "classmap": ["src/", "./src/Model", "lib/Legacy/"],
        "files": ["src/functions.php"]
    },
    "autoload-dev": {
        "classmap": ["tests/"]
    },
    "config": {
        "sort-packages": true
    }
}

JSON;
        $composer = new ComposerJson($json);

        $this->assertSame(['src/', './src/Model'], $composer->removeClassmap('autoload', 'src/'));
        $this->assertSame(['tests/'], $composer->removeClassmap('autoload-dev', 'tests'));
        $this->assertTrue($composer->addPsr4('autoload', 'App\\', 'src/'));
        $this->assertTrue($composer->addPsr4('autoload-dev', 'App\\Test\\', 'tests/'));
        $this->assertFalse($composer->addPsr4('autoload', 'App\\', 'lib/'));

        $this->assertSame(<<<'JSON'
{
    "name": "acme/app",
    "require": {"php": ">=8.0"},
    "autoload": {
        "classmap": ["lib/Legacy/"],
        "files": ["src/functions.php"],
        "psr-4": {
            "App\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "App\\Test\\": "tests/"
        }
    },
    "config": {
        "sort-packages": true
    }
}

JSON, $composer->contents());
    }

    /**
     * New sections after require-dev (autoload first), with the indentation of the file
     *
     * @return void
     */
    public function testNewSections(): void
    {
        $json = "{\n  \"name\": \"acme/blog\",\n  \"require-dev\": {\n    \"phpunit/phpunit\": \"^9.6\"\n  },\n  \"extra\": {\"installer-name\": \"Blog\"}\n}\n";
        $composer = new ComposerJson($json);
        $composer->addPsr4('autoload', 'Blog\\', 'src/');
        $composer->addPsr4('autoload-dev', 'Blog\\Test\\', 'tests/');

        $this->assertSame(
            "{\n  \"name\": \"acme/blog\",\n  \"require-dev\": {\n    \"phpunit/phpunit\": \"^9.6\"\n  },\n" .
            "  \"autoload\": {\n    \"psr-4\": {\n      \"Blog\\\\\": \"src/\"\n    }\n  },\n" .
            "  \"autoload-dev\": {\n    \"psr-4\": {\n      \"Blog\\\\Test\\\\\": \"tests/\"\n    }\n  },\n" .
            "  \"extra\": {\"installer-name\": \"Blog\"}\n}\n",
            $composer->contents(),
        );
        $this->assertSame(['Blog\\' => 'src/'], json_decode($composer->contents(), true)['autoload']['psr-4']);
    }

    /**
     * A file without the sections before them
     *
     * @return void
     */
    public function testNewSectionsAtTheEnd(): void
    {
        $composer = new ComposerJson("{\n    \"name\": \"acme/blog\"\n}");
        $composer->addPsr4('autoload', 'Blog\\', 'src/');

        $this->assertSame("{\n    \"name\": \"acme/blog\",\n    \"autoload\": {\n        \"psr-4\": {\n            \"Blog\\\\\": \"src/\"\n        }\n    }\n}", $composer->contents());
    }

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
        "psr-4": {"App\\": "src/"},
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
            // In src/, which stays the path of App\
            'src/Lib/Utility' => 'src/Utility',
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
     * The classmap and exclude-from-classmap of a core class override
     *
     * @return void
     */
    public function testAddToList(): void
    {
        $composer = new ComposerJson('{"autoload": {"psr-4": {"App\\\\": "src/"}}}');

        $this->assertTrue($composer->addToList('autoload', 'classmap', 'src/Cake/'));
        $this->assertFalse($composer->addToList('autoload', 'classmap', './src/Cake'));
        $this->assertTrue($composer->addToList('autoload', 'exclude-from-classmap', 'vendor/pieceofcake2/cakephp/src/Routing/Dispatcher.php'));

        $data = json_decode($composer->contents(), true);
        $this->assertSame(['src/Cake/'], $data['autoload']['classmap']);
        $this->assertSame(['vendor/pieceofcake2/cakephp/src/Routing/Dispatcher.php'], $data['autoload']['exclude-from-classmap']);
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

        $this->assertFalse($composer->addPsr4('autoload', 'App\\', 'src/'));
        $this->assertSame([], $composer->removeClassmap('autoload', 'src/'));
        $this->assertFalse($composer->isChanged());
        $this->assertSame($json, $composer->contents());
        $this->assertNull($composer->config('vendor-dir'));
    }

    /**
     * The values of other types around the changed section, and strings with escaped quotes and brackets
     *
     * @return void
     */
    public function testOtherValues(): void
    {
        $json = "{\n    \"description\": \"A \\\"blog\\\" {app} [x]\",\n    \"prefer-stable\": true,\n    \"autoload\": {\"classmap\": [\"src/\"]},\n    \"version\": 2,\n    \"extra\": null\n}\n";
        $composer = new ComposerJson($json);
        $composer->removeClassmap('autoload', 'src/');
        $composer->addPsr4('autoload', 'App\\', 'src/');

        $this->assertSame(
            "{\n    \"description\": \"A \\\"blog\\\" {app} [x]\",\n    \"prefer-stable\": true,\n    \"autoload\": {\n        \"psr-4\": {\n            \"App\\\\\": \"src/\"\n        }\n    },\n    \"version\": 2,\n    \"extra\": null\n}\n",
            $composer->contents(),
        );
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
