<?php

namespace Braumye\Pay\Tests;

use Mockery;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCase extends PHPUnitTestCase
{
    public function tearDown()
    {
        Mockery::close();
    }
}
