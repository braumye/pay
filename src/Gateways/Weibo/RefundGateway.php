<?php

namespace Braumye\Pay\Gateways\Weibo;

class RefundGateway
{
    /**
     * Find.
     *
     * @param  string|array $order
     * @return array
     */
    public function find($order): array
    {
        $config = [
            'endpoint' => 'pay/refund/query',
        ];

        if (is_string($order)) {
            $order = ['out_refund_id' => $order];
        }

        foreach (['pay_id', 'out_refund_id', 'refund_id'] as $value) {
            if (isset($order[$value])) {
                $config[$value] = $order[$value];
            }
        }

        return $config;
    }
}
