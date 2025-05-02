<?php

namespace Bagisto\Tamara\Payment;

use Webkul\Payment\Payment\Payment;
class Tamara extends Payment
{
    protected $baseUrl;
    protected $bearerToken;
    protected $webhookToken;
    protected $cancelUrl;
    protected $failureUrl;
    protected $successUrl;
    protected $notifyUrl;
    /**
     * Payment method code
     *
     * @var string
     */
    protected $code  = 'tamara';
    public function __construct() {
        $this->baseUrl = core()->getConfigData('sales.payment_methods.tamara.base-url');
        $this->bearerToken = core()->getConfigData('sales.payment_methods.tamara.bearer-token');
        $this->webhookToken = core()->getConfigData('sales.payment_methods.tamara.webhook-token');
        $this->cancelUrl = core()->getConfigData('sales.payment_methods.tamara.cancel-url');
        $this->failureUrl = core()->getConfigData('sales.payment_methods.tamara.failure-url');
        $this->successUrl = core()->getConfigData('sales.payment_methods.tamara.success-url');
        $this->notifyUrl = core()->getConfigData('sales.payment_methods.tamara.notify-url');
    }
    public function sdkRequest($endpoint, $method = 'POST', $data = []) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->baseUrl . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_HTTPHEADER     => [
                "accept: application/json",
                "authorization: Bearer {$this->bearerToken}",
                "content-type: application/json"
            ],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
    public function getRedirectUrl()
    {
        return route('tamara.process');
    }
    /**
     * Get payment method image.
     *
     * @return array
     */
    public function getImage()
    {
        $url = $this->getConfigData('image');

        return $url ? Storage::url($url) : "tamara/image/tamara.png";
    }
}
