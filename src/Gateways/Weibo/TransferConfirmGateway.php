<?php

namespace Braumye\Pay\Gateways\Weibo;

use Braumye\Pay\Gateways\Weibo\Support;
use Yansongda\Pay\Contracts\GatewayInterface;
use Yansongda\Pay\Events;
use Yansongda\Pay\Exceptions\GatewayException;
use Yansongda\Pay\Exceptions\InvalidConfigException;
use Yansongda\Pay\Exceptions\InvalidSignException;
use Yansongda\Supports\Collection;

class TransferConfirmGateway implements GatewayInterface
{
    /**
     * Pay an order.
     *
     * @param string $endpoint
     *
     * @throws GatewayException
     * @throws InvalidConfigException
     * @throws InvalidSignException
     */
    public function pay($endpoint, array $payload): Collection
    {
        $payload['sign_type'] = $payload['sign_type'] ?? 'rsa';
        $payload['sign'] = Support::generateSign($payload);

        $endpoint = $endpoint.'transfer/confirm';

        Events::dispatch(new Events\PayStarted('Weibo', 'TransferConfirm', $endpoint, $payload));

        return Support::requestApi($endpoint, $payload);
    }

    /**
     * Find.
     *
     * @param $order
     */
    public function find($order): array
    {
        return [
            'endpoint' => 'transfer/query',
            'transfer_id' => $order['transfer_id'] ?? $order,
        ];
    }
}
