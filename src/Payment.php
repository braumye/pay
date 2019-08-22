<?php

namespace Braumye\Pay;

use Yansongda\Pay\Pay;
use Yansongda\Supports\Str;
use Yansongda\Pay\Contracts\GatewayApplicationInterface;

class Payment extends Pay
{
    /**
     * Create a instance.
     *
     * @param  string $method
     * @return \Yansongda\Pay\Contracts\GatewayApplicationInterface
     * @throws \Yansongda\Pay\Exceptions\InvalidGatewayException
     */
    protected function create($method): GatewayApplicationInterface
    {
        $gateway = __NAMESPACE__.'\\Gateways\\'.Str::studly($method);

        if (class_exists($gateway)) {
            return $this->make($gateway);
        }

        return parent::create($method);
    }

    /**
     * Magic static call.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param string $method
     * @param array  $params
     *
     * @throws InvalidGatewayException
     * @throws Exception
     *
     * @return GatewayApplicationInterface
     */
    public static function __callStatic($method, $params): GatewayApplicationInterface
    {
        $app = new static(...$params);

        return $app->create($method);
    }
}
