<?php

namespace Braumye\Pay\Tests;

use Braumye\Pay\Payment;
use Braumye\Pay\Tests\TestCase;
use Yansongda\Pay\Contracts\GatewayApplicationInterface;

class PayTest extends TestCase
{
    public function testGateway()
    {
        $alipay = Payment::alipay(['foo' => 'bar']);
        $this->assertInstanceOf(GatewayApplicationInterface::class, $alipay);

        $wechat = Payment::wechat(['foo' => 'bar']);
        $this->assertInstanceOf(GatewayApplicationInterface::class, $wechat);

        $weibo = Payment::weibo(['foo' => 'bar']);
        $this->assertInstanceOf(GatewayApplicationInterface::class, $weibo);
    }
}
