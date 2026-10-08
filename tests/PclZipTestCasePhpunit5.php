<?php

namespace PclZip\Tests;

abstract class PclZipTestCase extends \PHPUnit_Framework_TestCase
{
    use PclZipTestCaseTrait;

    protected function setUp()
    {
        $this->pclzipSetUp();
    }

    protected function tearDown()
    {
        $this->pclzipTearDown();
    }
}
