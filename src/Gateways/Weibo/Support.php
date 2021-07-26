<?php

namespace Braumye\Pay\Gateways\Weibo;

use Exception;
use Yansongda\Pay\Log;
use Yansongda\Pay\Events;
use Yansongda\Supports\Str;
use Yansongda\Supports\Config;
use Yansongda\Pay\Gateways\Wechat;
use Yansongda\Supports\Collection;
use Yansongda\Supports\Traits\HasHttpRequest;
use Yansongda\Pay\Exceptions\GatewayException;
use Yansongda\Pay\Exceptions\BusinessException;
use Yansongda\Pay\Exceptions\InvalidSignException;
use Yansongda\Pay\Exceptions\InvalidConfigException;
use Yansongda\Pay\Exceptions\InvalidArgumentException;

/**
 * @property string appKey
 * @property string seller_id
 * @property string public_key
 * @property string private_key
 * @property string notify_url
 * @property string return_url
 */
class Support
{
    use HasHttpRequest;

    /**
     * Wechat gateway.
     *
     * @var string
     */
    protected $baseUri;

    /**
     * Config.
     *
     * @var \Yansongda\Supports\Config
     */
    protected $config;

    /**
     * Instance.
     *
     * @var \Braumye\Pay\Gateways\Weibo\Support
     */
    private static $instance;

    /**
     * Bootstrap.
     *
     * @param \Yansongda\Supports\Config $config
     */
    private function __construct(Config $config)
    {
        $this->baseUri = 'https://pay.sc.weibo.com/api/merchant/';
        $this->config = $config;

        $this->setHttpOptions();
    }

    /**
     * __get.
     *
     * @param  string $key
     * @return mixed|null|Config
     */
    public function __get($key)
    {
        return $this->getConfig($key);
    }

    /**
     * create.
     *
     * @param  \Yansongda\Supports\Config $config
     * @return \Braumye\Pay\Gateways\Weibo\Support
     * @throws \Yansongda\Pay\Exceptions\GatewayException
     * @throws \Yansongda\Pay\Exceptions\InvalidArgumentException
     * @throws \Yansongda\Pay\Exceptions\InvalidSignException
     *
     */
    public static function create(Config $config)
    {
        if (php_sapi_name() === 'cli' || !(self::$instance instanceof self)) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * Request wechat api.
     *
     * @param  string $endpoint
     * @param  array  $data
     * @return \Yansongda\Supports\Collection
     * @throws \Yansongda\Pay\Exceptions\GatewayException
     * @throws \Yansongda\Pay\Exceptions\InvalidArgumentException
     * @throws \Yansongda\Pay\Exceptions\InvalidSignException
     *
     */
    public static function requestApi($endpoint, $data): Collection
    {
        Events::dispatch(new Events\ApiRequesting('Weibo', '', self::$instance->getBaseUri().$endpoint, $data));

        $result = self::$instance->post($endpoint, $data);

        $result = is_array($result) ? $result : json_decode($result, true);

        Events::dispatch(new Events\ApiRequested('Weibo', '', self::$instance->getBaseUri().$endpoint, $result));

        return self::processingApiResult($endpoint, $result);
    }

    /**
     * Generate weibo sign.
     *
     * @param  array $data
     * @return string
     * @throws \Yansongda\Pay\Exceptions\InvalidArgumentException
     */
    public static function generateSign($params): string
    {
        $privateKey = self::$instance->private_key;

        if (is_null($privateKey)) {
            throw new InvalidConfigException('Missing Weibo Config -- [private_key]');
        }

        if (Str::endsWith($privateKey, '.pem')) {
            $privateKey = openssl_pkey_get_private(
                Str::startsWith($privateKey, 'file://') ? $privateKey : 'file://'.$privateKey
            );
        } else {
            $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n".
                wordwrap($privateKey, 64, "\n", true).
                "\n-----END RSA PRIVATE KEY-----";
        }

        openssl_sign(self::getSignContent($params), $sign, $privateKey);

        $sign = base64_encode($sign);

        Log::debug('Weibo Generate Sign', [$params, $sign]);

        if (is_resource($privateKey)) {
            openssl_free_key($privateKey);
        }

        return $sign;
    }

    /**
     * Generate sign content.
     *
     * @param  array $data
     * @return string
     */
    public static function getSignContent($data): string
    {
        ksort($data);
        reset($data);

        $stringToBeSigned = '';
        foreach($data as $k => $v) {
            if (! (in_array($k, ['sign', 'sign_type', 'endpoint']) || $v === '' || is_null($v))) {
                $stringToBeSigned .= $k.'='.$v.'&';
            }
        }

        Log::debug('Weibo Generate Sign Content Before Trim', [$data, $stringToBeSigned]);

        return trim($stringToBeSigned, '&');
    }

    /**
     * Verify sign.
     *
     * @param  array  $data
     * @param  bool   $sync
     * @param  string $sign
     * @return bool
     * @throws \Yansongda\Pay\Exceptions\InvalidConfigException
     */
    public static function verifySign(array $data, $sync = false, $sign = null): bool
    {
        $publicKey = self::$instance->public_key;

        if (is_null($publicKey)) {
            throw new InvalidConfigException('Missing Weibo Config -- [public_key]');
        }

        if (Str::endsWith($publicKey, '.pem')) {
            $publicKey = openssl_pkey_get_public(
                Str::startsWith($publicKey, 'file://') ? $publicKey : 'file://'.$publicKey
            );
        } else {
            $publicKey = "-----BEGIN PUBLIC KEY-----\n".
                wordwrap($publicKey, 64, "\n", true).
                "\n-----END PUBLIC KEY-----";
        }

        $sign = $sign ?? $data['sign'];

        $toVerify = self::getSignContent($data);

        $isVerify = openssl_verify($toVerify, base64_decode($sign), $publicKey) === 1;

        if (is_resource($publicKey)) {
            openssl_free_key($publicKey);
        }

        return $isVerify;
    }

    /**
     * Get service config.
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function getConfig($key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->config->all();
        }

        if ($this->config->has($key)) {
            return $this->config[$key];
        }

        return $default;
    }

    /**
     * Get Base Uri.
     *
     * @return string
     */
    public function getBaseUri(): string
    {
        return $this->baseUri;
    }

    /**
     * processingApiResult.
     *
     * @param  string $endpoint
     * @param  array $result
     * @return \Yansongda\Supports\Collection
     * @throws \Yansongda\Pay\Exceptions\GatewayException
     */
    protected static function processingApiResult(string $endpoint, array $result): Collection
    {
        if (!isset($result['code']) || $result['code'] != '100000') {
            throw new GatewayException('Get Weibo API Error:'.$endpoint.' '.($result['msg'] ?? 'Unknown'), $result);
        }

        return new Collection($result);
    }

    /**
     * Set Http options.
     *
     * @return self
     */
    private function setHttpOptions(): self
    {
        if ($this->config->has('http') && is_array($this->config->get('http'))) {
            $this->config->forget('http.base_uri');
            $this->httpOptions = $this->config->get('http');
        }

        return $this;
    }
}
