<?php

namespace PclZip\Tests;

abstract class PclZipTestCase extends \PHPUnit\Framework\TestCase
{
    use PclZipTestCaseTrait;

    protected function setUp(): void
    {
        $this->pclzipSetUp();
    }

    protected function tearDown(): void
    {
        $this->pclzipTearDown();
    }
}
