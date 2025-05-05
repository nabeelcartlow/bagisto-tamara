<?php

namespace Bagisto\Tamara\Payment;

use Carbon\Carbon;
use Dotenv\Util\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Webkul\Checkout\Facades\Cart;
use Webkul\Payment\Payment\Payment;
use Webkul\Sales\Transformers\OrderResource;

class Tamara extends Payment
{
    protected $baseUrl;
    protected $bearerToken;
    protected $webhookToken;
    public $cancelUrl;
    public $failureUrl;
    public $successUrl;
    public $notifyUrl;
    public $country;
    public $platform;
    public $isMobile;
    public $tamaraLocale;
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
        $this->country = core()->getConfigData('sales.shipping.origin.country') != ''
        ? core()->getConfigData('sales.shipping.origin.country')
        : strtoupper(config('app.default_country'));
        $this->platform = "web";
        $this->isMobile = false;
        $this->tamaraLocale = core()->getDefaultLocaleCodeFromDefaultChannel() . "_" . $this->country;
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
    /**
     * Abstract method to get the redirect URL.
     *
     * @return string The redirect URL.
     */
    public function getRedirectUrl() {
        return route('tamara.process');
    }
    /**
     * Get payment method image.
     *
     * @return string
     */
    public function getImage() {
        $url = $this->getConfigData('image');

        return $url ? Storage::url($url) : "tamara/image/tamara.png";
    }
    public function initiateSession($order) {
        $cart    = $this->getCart();
        $payload = [
            'total_amount'       => [
                'amount'   => round($cart->grand_total, 2),
                'currency' => $order->order_currency_code
            ],
            'shipping_amount'    => [
                'amount'   => round($cart->shipping_amount, 2),
                'currency' => $order->order_currency_code
            ],
            'tax_amount'         => [
                'amount'   => round($cart->tax_total, 2),
                'currency' => $order->order_currency_code
            ],
            'order_reference_id' => $order->id,
            'order_number'       => $order->id,
            'items'              => $this->getItemsPayload($cart, $order),
            'consumer'           => $this->getConsumerPayload($cart->customer, $cart->billing_address),
            'country_code'       => $this->country,
            'description'        => $this->getOrderDescription($cart),
            'merchant_url'       => $this->getMerchantUrl(),
            'payment_type'       => 'PAY_BY_INSTALMENTS',
            'instalments'        => 4,
            'billing_address'    => $this->getAddressPayload($cart->billing_address),
            'shipping_address'   => $this->getAddressPayload($cart->billing_address),
            'risk_assessment'    => $this->getRiskAssessment(),
            'platform'           => $this->platform,
            'is_mobile'          => $this->isMobile,
            'locale'             => $this->tamaraLocale,
        ];
        $response = $this->sdkRequest("/checkout", "POST", json_encode($payload));
        $result   = json_decode($response, true);
        Log::debug(print_r([$payload, $response, $result], true));
        return $result;
    }
    public function getConsumerPayload($customer, $billingAddress) {
        return [
            'uuid'         => $customer->id,
            'email'        => $customer->email,
            'first_name'   => ($customer->first_name == "") ? "-" : $customer->first_name,
            'last_name'    => ($customer->last_name == "") ? "-" : $customer->last_name,
            'phone_number' => !empty($billingAddress->phone) ? $billingAddress->phone : 'NA'
        ];
    }
    public function getAddressPayload($billingAddress) {
        return [
            'city'         => !empty($billingAddress->city) ? $billingAddress->city : 'NA',
            'country_code' => !empty($billingAddress->country) ? $billingAddress->country : 'NA',
            'first_name'   => $billingAddress->first_name,
            'last_name'    => $billingAddress->last_name,
            'line1'        => !empty($billingAddress->address) ? $billingAddress->address : 'NA',
            'line2'        => !empty($billingAddress->address) ? $billingAddress->address : 'NA',
            'phone_number' => !empty($billingAddress->phone) ? $billingAddress->phone : 'NA',
            'region'       => !empty($billingAddress->state) ? $billingAddress->state : 'NA',
        ];
    }
    public function getItemsPayload($cart, $order) {
        $items = [];
        foreach($cart->items as $item) {
            $items[] = [
                'name'         => $item->name,
                'type'         => 'Physical',
                'reference_id' => $item->sku,
                'sku'          => $item->sku,
                'quantity'     => $item->quantity,
                'item_url'     => config("app.url") . "/" . $item->product->url_key,
                'unit_price'   => [
                    'amount'   => round($item->base_price, 2),
                    'currency' => $order->order_currency_code,
                ],
                'total_amount' => [
                    'amount'   => round(($item->quantity*$item->base_price), 2),
                    'currency' => $order->order_currency_code
                ]
            ];
        }
        return $items;
    }
    public function getMerchantUrl() {
        return [
            'cancel'       => $this->cancelUrl,
            'failure'      => $this->failureUrl,
            'success'      => $this->successUrl,
            'notification' => $this->notifyUrl
        ];
    }
    public function getRiskAssessment() {
        return [
            "is_premium_customer"       => true,
            "is_existing_customer"      => true,
            "account_creation_date"     => Carbon::now()->format('d-m-Y'),
            "date_of_first_transaction" => Carbon::now()->format('d-m-Y'),
            "is_card_on_file"           => false,
            "has_delivered_order"       => false,
            "is_phone_verified"         => true,
            "is_email_verified"         => true,
            "is_fraudulent_customer"    => false,
            "total_order_count"         => 0,
            "order_amount_last3months"  => 0,
            "order_count_last3months"   => 0,
        ];
    }
    public function getOrderDescription($cart) {
        $description = '';
        foreach($cart->items as $item) {
            $description = $item->name . ', ';
        }
        return $description;
    }
}
