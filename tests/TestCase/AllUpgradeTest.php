<?php

use PHPUnit\Framework\TestSuite;

/**
 * AllUpgradeTest file
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @package       Upgrade.Test.Case
 * @since         CakePHP(tm) v 2.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

/**
 * AllUpgradeTest class
 *
 * This test group will run all Upgrade plugin tests.
 *
 * @package       Upgrade.Test.Case
 */
class AllUpgradeTest extends TestSuite
{
    /**
     * suite method, defines tests for this suite.
     *
     * @return CakeTestSuite
     */
    public static function suite(): CakeTestSuite
    {
        $suite = new CakeTestSuite('All Upgrade plugin tests');

        $suite->addTestFile(__DIR__ . DS . 'AllConsoleTest.php');
        $suite->addTestFile(__DIR__ . DS . 'AllMigrationTest.php');

        return $suite;
    }
}
