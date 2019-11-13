<?php

namespace Braumye\Pay\Tests;

use Mockery;
use GuzzleHttp\Client;
use Braumye\Pay\Payment;
use GuzzleHttp\Psr7\Response;
use Yansongda\Supports\Collection;
use Braumye\Pay\Gateways\Weibo\Support;

class WeiboGatewayTest extends TestCase
{
    public function testUnsupportedGateway()
    {
        try {
            Payment::weibo($this->config())->app([]);
        } catch (\Exception $e) {
            $this->assertEquals('INVALID_GATEWAY: Pay Gateway [Braumye\Pay\Gateways\Weibo\AppGateway] Not Exists', $e->getMessage());
        }
    }

    public function testWebGateway()
    {
        $order = [
            'out_trade_no' => time(),
            'total_fee' => '1',
            'body' => 'test',
            'openid' => 'openid',
        ];

        $response = Payment::weibo($this->config())->web($order);

        $this->assertTrue($response->isRedirect());

        $this->assertContains(
            'https://pay.sc.weibo.com/api/merchant/pay/cashier?',
            $response->getTargetUrl()
        );
    }

    public function testQueryOrder()
    {
        $response = ['code' => '100000', 'data' => ['status' => 'PAY_STATUS_SUCCESS']];

        Mockery::mock('overload:'.Client::class)->shouldReceive('post')->once()
            ->andReturn(new Response(200, [], json_encode($response)));

        $order = [
            'out_pay_id' => 'out_pay_id',
        ];

        $app = Payment::weibo($this->config())->find($order);

        $this->assertInstanceof(Collection::class, $app);
        $this->assertEquals('PAY_STATUS_SUCCESS', $app->get('data.status'));
    }

    public function testQueryOrderForUnsupportGateway()
    {
        try {
            Payment::weibo($this->config())->find('order_id', 'app');
        } catch (\Exception $e) {
            $want = 'ERROR_GATEWAY: Braumye\Pay\Gateways\Weibo\AppGateway Done Not Exist Or Done Not Has FIND Method';
            $this->assertEquals($want, $e->getMessage());
        }
    }

    public function testRefund()
    {
        Mockery::mock('overload:'.Client::class)->shouldReceive('post')->once()
            ->andReturn(new Response(200, [], json_encode(['code' => '100000', 'data' => ['is_success' => 'T']])));

        $order = [
            'pay_id' => 'pay_id',
            'out_refund_id' => 'out_refund_id',
            'detail_data' => 'detail_data',
        ];

        $response = Payment::weibo($this->config())->refund($order);

        $this->assertEquals('T', $response->get('data.is_success'));
    }

    public function testRefundQuery()
    {
        $response = ['code' => '100000', 'data' => ['status' => 'REFUND_STATUS_SUCCESS']];

        Mockery::mock('overload:'.Client::class)->shouldReceive('post')->once()
            ->andReturn(new Response(200, [], json_encode($response)));

        $app = Payment::weibo($this->config())->find(['pay_id' => 'pay_id'], 'refund');

        $this->assertInstanceof(Collection::class, $app);
        $this->assertEquals('REFUND_STATUS_SUCCESS', $app->get('data.status'));
    }

    public function testRefundQueryForString()
    {
        $response = ['code' => '100000', 'data' => ['status' => 'REFUND_STATUS_SUCCESS']];

        Mockery::mock('overload:'.Client::class)->shouldReceive('post')->once()
            ->andReturn(new Response(200, [], json_encode($response)));

        $app = Payment::weibo($this->config())->find('out_refund_id', 'refund');

        $this->assertInstanceof(Collection::class, $app);
        $this->assertEquals('REFUND_STATUS_SUCCESS', $app->get('data.status'));
    }

    public function testVerify()
    {
        $app = Payment::weibo($this->config());

        $data = ['foo' => 'bar', 'body' => null];
        $data['sign'] = Support::generateSign($data);

        $this->assertInstanceof(Collection::class, $app->verify($data));
    }

    public function testVerifyForRequest()
    {
        $app = Payment::weibo($this->config());

        $data = ['foo' => 'bar'];
        $data['sign'] = Support::generateSign($data);
        $_GET = $data;

        $this->assertInstanceof(Collection::class, $app->verify());
    }

    public function testVerifyFailed()
    {
        $app = Payment::weibo($this->config());
        $data = ['foo' => 'bar', 'sign' => ''];

        try {
            $app->verify($data);
        } catch (\Exception $e) {
            $this->assertEquals('INVALID_SIGN: Weibo Sign Verify FAILED', $e->getMessage());
        }
    }

    public function testSuccess()
    {
        $app = Payment::weibo($this->config())->success();

        $this->assertEquals('success', $app->getContent());
    }

    public function testUnsupportClose()
    {
        try {
            Payment::weibo($this->config())->close('order');
        } catch (\Exception $e) {
            $this->assertEquals('ERROR_GATEWAY: Weibo Pay does not support close orders.', $e->getMessage());
        }
    }

    public function testUnsupportCancel()
    {
        try {
            Payment::weibo($this->config())->cancel('order');
        } catch (\Exception $e) {
            $this->assertEquals('ERROR_GATEWAY: Weibo Pay does not support cancel orders.', $e->getMessage());
        }
    }

    private function config()
    {
        return [
            'appkey' => 'appkey',
            'seller_id' => 'seller_id',
            'return_url' => '',
            'notify_url' => '',
            'private_key' => $this->privateKey(),
            'public_key' => $this->publicKey(),
        ];
    }

    private function publicKey()
    {
        return 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAm87oCUuKpg5hnC+295ZwezibKsUHtuFcqOIvUKzaxNcI8nN6OsIAikpMgqO1mgHWGcl1harjZPgC9GRQmseYmVcgFs2tqL2HFUipoDQ0BZULp5AMUitJMn/o9ddy+FdzE3fa2FkOhEWeV4LQJfCcyi036F+jan1JIbArSGWR68COLryqJILyb/XPHoeBH6MHIS18ESObeOf5Yp8V+pYqIA+XTWu5ZI5WBqCeWYPa+a2YK/CuWn+L8I6/AFqKGZKAbb2jzSwGCA2aG3LzqHDzz94Q7CwO3rg8vqaFQfBcCZU7XTKETQZapigyLC0y+sC9ZCIY8axsiXVXHfvWoALw/QIDAQAB';
    }

    private function privateKey()
    {
        return 'MIIEpQIBAAKCAQEAm87oCUuKpg5hnC+295ZwezibKsUHtuFcqOIvUKzaxNcI8nN6OsIAikpMgqO1mgHWGcl1harjZPgC9GRQmseYmVcgFs2tqL2HFUipoDQ0BZULp5AMUitJMn/o9ddy+FdzE3fa2FkOhEWeV4LQJfCcyi036F+jan1JIbArSGWR68COLryqJILyb/XPHoeBH6MHIS18ESObeOf5Yp8V+pYqIA+XTWu5ZI5WBqCeWYPa+a2YK/CuWn+L8I6/AFqKGZKAbb2jzSwGCA2aG3LzqHDzz94Q7CwO3rg8vqaFQfBcCZU7XTKETQZapigyLC0y+sC9ZCIY8axsiXVXHfvWoALw/QIDAQABAoIBAG4beCspcWAMhbqElb6+V9sck7tT5jG9bWgD///5R9kXRcFhDh37/7m66/reinW9mno6voypyW0PP7dKNRRMvXCP+6Nh0rmOxqmp4gXPHnxbahMOX0aqRF4lupIRobQSyMYXS1bdCL89G7soPMSzF3phHkNfYvLcexQNlWjiLo71OJ7P2xX6OiRpWX8ddSI1RZyssOxifXAK+3kkh4pYBTHXHG8b8pTVqfpkPrgUbjGIApVm8ID1Vn12iBqh/xiVbq9LMRP3NpoBCLKsG3O2qw2hLjo4mg3GZ6bT/X+gzBlSmU2YMPOXg9/kT4Df9D/Zky89Sw3qa95445gXHM+yKlkCgYEAzbSmoqrxsJVqp0vCjeiGPZlGwJhYvOq6XWz/T5jnOlcSWk53QOvN+Poyh1CoodFNl1kPkQhLzUlRWHJNm0qHzLqL31aHcrQ742Lvg6Sh+AaY2dhk7E2cgGx3luflylaVme4gvXrD/yQ73RP3nSFpCn2aUHbO80l134OEHxJtOkMCgYEAweceXX6Z6aGoUa+ZI0i+LgVXJDkKqyhqMXGxriIY+n+G+HyuWcGzqvmJLfjMVKmmlq1ZsQ9xKH05rW7Uqh76NkQosHKSskv2DC1kqOpyoEWBPpNp+/ue1ve+nFkZmCeXbEfOEq6G5rcKkjMs5qafkpI+oXioM27SlWnJ596wk78CgYEAv5GScgEyzhmIVr62NAQtKCabfncihOUwpBsC9bTW+jzmiZVzd9FiY5FWBzB1qyVJ1j4Jwi5wsYCrMexZG9rf9yhvZJGn2InDEDMuDXH/qQUzygS9kFeC/RKGmNHj7XiPH+hlMzCmYPD0kyGqQvo/YZaxZGgQuP9bt8k5/NnjQRMCgYEAhZNMdMXRC4QkqtkX2pmzAYsGQ9rTwaLevN8Ast+ka3RMq4NgrE5nEgJDARtiz3PrYeNbZUEpB+Z0REiUUflzDs0XZq4W5qKzhiIDNOYFPumCpnPwz/i/rIEQmy46Fno3sw0oJfB62BcCPkLozJ++T79VS/ENlhjjErDEuWnMJIsCgYEAl8olW/kJ+EvmP0eMVVCBI9Q5l4AZy1JJfwohYnTgb5mwDn49ZGS21BLyKB02PCUouQLYs4F8jqoqGnQ2aAgpSgDidzVhFVfOOd0R9CGaTJJpTjay+FtMQkR9U/FuP4wIg0rxx5ghv8UESQiLKJZyyrgzzYHut14M/tpBg/ROy2I=';
    }
}
