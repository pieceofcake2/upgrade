<?php

use PHPUnit\Framework\TestSuite;

/**
 * AllMigrationTest file
 *
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

/**
 * AllMigrationTest class
 *
 * The classes of `upgrade layout` and `upgrade namespace` that move files and edit config/core.php and composer.json.
 */
class AllMigrationTest extends TestSuite
{
    /**
     * suite method, defines tests for this suite.
     *
     * @return CakeTestSuite
     */
    public static function suite(): CakeTestSuite
    {
        $suite = new CakeTestSuite('All Upgrade migration classes');
        $suite->addTestDirectoryRecursive(__DIR__ . DS . 'Migration');

        return $suite;
    }
}
