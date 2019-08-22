<?php

namespace Braumye\Pay\Gateways\Weibo;

use Yansongda\Pay\Events;
use Yansongda\Pay\Events\PayStarted;
use Braumye\Pay\Gateways\Weibo\Support;
use Yansongda\Pay\Contracts\GatewayInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class WebGateway implements GatewayInterface
{
    /**
     * Pay an order.
     *
     * @param  string $endpoint
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function pay($endpoint, array $payload): Response
    {
        $payload['sign'] = Support::generateSign($payload);

        Events::dispatch(new PayStarted('Weibo', 'Web', $endpoint, $payload));

        return RedirectResponse::create($endpoint.'cashier'.'&'.http_build_query($payload));
    }

    /**
     * Find.
     *
     * @param  array|string $order
     * @return array
     */
    public function find($order): array
    {
        return [
            'endpoint' => 'query',
            'out_pay_id' => $order['out_pay_id'] ?? $order,
        ];
    }
}
