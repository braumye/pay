<?php

namespace Braumye\Pay\Gateways;

use Yansongda\Pay\Events;
use Yansongda\Supports\Str;
use Yansongda\Supports\Config;
use Yansongda\Supports\Collection;
use Yansongda\Pay\Events\SignFailed;
use Yansongda\Pay\Events\PayStarting;
use Yansongda\Pay\Events\MethodCalled;
use Braumye\Pay\Gateways\Weibo\Support;
use Yansongda\Pay\Events\RequestReceived;
use Symfony\Component\HttpFoundation\Request;
use Yansongda\Pay\Contracts\GatewayInterface;
use Symfony\Component\HttpFoundation\Response;
use Yansongda\Pay\Exceptions\GatewayException;
use Yansongda\Pay\Exceptions\InvalidSignException;
use Yansongda\Pay\Exceptions\InvalidGatewayException;
use Yansongda\Pay\Contracts\GatewayApplicationInterface;

class Weibo implements GatewayApplicationInterface
{
    /**
     * Wechat payload.
     *
     * @var array
     */
    protected $payload;

    /**
     * Wechat gateway.
     *
     * @var string
     */
    protected $gateway;

    /**
     * Bootstrap.
     *
     * @param  Config $config
     */
    public function __construct(Config $config)
    {
        $this->gateway = Support::create($config)->getBaseUri();

        $this->payload = [
            'sign_type' => 'rsa',
            'sign' => '',
            'appkey' => $config->get('appkey', ''),
            'seller_id' => $config->get('seller_id', ''),
            'notify_url' => $config->get('notify_url', ''),
            'return_url' => $config->get('return_url', ''),
        ];
    }

    /**
     * To pay.
     *
     * @param  string $gateway
     * @param  array  $params
     * @return Collection|Response
     * @throws \Yansongda\Pay\Exceptions\InvalidGatewayException
     */
    public function pay($gateway, $params)
    {
        Events::dispatch(new PayStarting('Weibo', $gateway, (array) $params));

        $this->payload = array_merge($this->payload, $params);

        $gateway = get_class($this).'\\'.Str::studly($gateway).'Gateway';

        if (class_exists($gateway)) {
            return $this->makePay($gateway);
        }

        throw new InvalidGatewayException("Pay Gateway [{$gateway}] Not Exists");
    }

    /**
     * Query an order.
     *
     * @param  string|array $order
     * @param  string       $type
     * @return Collection
     */
    public function find($order, $type = 'web'): Collection
    {
        $gateway = get_class($this).'\\'.Str::studly($type).'Gateway';

        if (!class_exists($gateway) || !is_callable([new $gateway(), 'find'])) {
            throw new GatewayException("{$gateway} Done Not Exist Or Done Not Has FIND Method");
        }

        $config = call_user_func([new $gateway(), 'find'], $order);
        $config['sign_type'] = $this->payload['sign_type'];
        $config['seller_id'] = $this->payload['seller_id'];
        $config['sign'] = Support::generateSign($config);

        Events::dispatch(new Events\MethodCalled('Weibo', 'Find', $this->gateway.$config['endpoint'], $config));

        return Support::requestApi($config['endpoint'], $config);
    }

    /**
     * Refund an order.
     *
     * @param  array $order
     * @return Collection
     */
    public function refund($order): Collection
    {
        $payload = [
            'sign_type' => 'rsa',
            'seller_id' => $this->payload['seller_id'],
            'pay_id' => $order['pay_id'] ?? '',
            'out_refund_id' => $order['out_refund_id'] ?? '',
            'notify_url' => $order['notify_url'] ?? '',
            'detail_data' => $order['detail_data'] ?? '',
        ];

        $payload['sign'] = Support::generateSign($payload);

        Events::dispatch(new MethodCalled('Weibo', 'Refund', $this->gateway, $payload));

        return Support::requestApi('refund/apply', $payload);
    }

    /**
     * Cancel an order.
     *
     * @param  string|array $order
     * @return Collection
     * @throws \Yansongda\Pay\Exceptions\GatewayException
     */
    public function cancel($order)
    {
        throw new GatewayException("Weibo Pay does not support cancel orders.");
    }

    /**
     * Close an order.
     *
     * @param  string|array $order
     * @return Collection
     * @throws \Yansongda\Pay\Exceptions\GatewayException
     */
    public function close($order)
    {
        throw new GatewayException("Weibo Pay does not support close orders.");
    }

    /**
     * Verify a request.
     *
     * @param  array $content
     * @param  bool  $refund
     * @return \Yansongda\Supports\Collection
     */
    public function verify($content = null, $refund = false): Collection
    {
        if (is_null($content)) {
            $request = Request::createFromGlobals();

            $content = $request->request->count() > 0 ? $request->request->all() : $request->query->all();
        }

        Events::dispatch(new RequestReceived('Weibo', '', $content));

        if (Support::verifySign($content)) {
            return new Collection($content);
        }

        Events::dispatch(new SignFailed('Weibo', '', $content));

        throw new InvalidSignException('Weibo Sign Verify FAILED', $content);
    }

    /**
     * Echo success to server.
     *
     * @return Response
     */
    public function success(): Response
    {
        return Response::create('success');
    }

    /**
     * Make pay gateway.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param string $gateway
     *
     * @throws InvalidGatewayException
     *
     * @return Response|Collection
     */
    protected function makePay($gateway)
    {
        $app = new $gateway();

        if ($app instanceof GatewayInterface) {
            $payload = array_filter($this->payload, function ($value) {
                return $value !== '' && !is_null($value);
            });

            return $app->pay($this->gateway, $payload);
        }

        throw new InvalidGatewayException("Pay Gateway [{$gateway}] Must Be An Instance Of GatewayInterface");
    }

    /**
     * Magic pay.
     *
     * @param  string $method
     * @param  string $params
     * @return Response|Collection
     * @throws InvalidGatewayException
     */
    public function __call($method, $params)
    {
        return $this->pay($method, ...$params);
    }
}
