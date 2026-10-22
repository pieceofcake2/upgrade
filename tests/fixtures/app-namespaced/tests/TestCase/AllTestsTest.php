<?php

namespace App\Test\TestCase;

use Cake\TestSuite\CakeTestSuite;

class AllTestsTest extends CakeTestSuite
{
    public static function suite()
    {
        $suite = new CakeTestSuite('All tests');
        $suite->addTestDirectoryRecursive(TESTS . 'TestCase');

        return $suite;
    }
}
