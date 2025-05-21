<?php

namespace Bagisto\Tamara\Payment;

use Webkul\Payment\Payment\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Checkout\Facades\Cart;
use Illuminate\Http\Client\Response;

class Tamara extends Payment
{
    protected string $baseUrl;
    protected string $bearerToken;
    protected string $webhookToken;
    public string $cancelUrl;
    public string $failureUrl;
    public string $successUrl;
    public string $notifyUrl;
    public string $country;
    public string $platform = 'web';
    public bool $isMobile = false;
    public string $tamaraLocale;

    protected string $code = 'tamara';

    public function __construct()
    {
        $this->baseUrl      = (string) core()->getConfigData('sales.payment_methods.tamara.base-url');
        $this->bearerToken  = (string) core()->getConfigData('sales.payment_methods.tamara.bearer-token');
        $this->webhookToken = (string) core()->getConfigData('sales.payment_methods.tamara.webhook-token');
        $this->cancelUrl    = (string) core()->getConfigData('sales.payment_methods.tamara.cancel-url');
        $this->failureUrl   = (string) core()->getConfigData('sales.payment_methods.tamara.failure-url');
        $this->successUrl   = (string) core()->getConfigData('sales.payment_methods.tamara.success-url');
        $this->notifyUrl    = (string) core()->getConfigData('sales.payment_methods.tamara.notify-url');

        $this->country = core()->getConfigData('sales.shipping.origin.country') 
            ?: strtoupper(config('app.default_country', 'SA'));

        $this->tamaraLocale = core()->getDefaultLocaleCodeFromDefaultChannel() . "_{$this->country}";
    }

    public function getRedirectUrl(): string
    {
        return route('tamara.process');
    }

    public function getImage(): string
    {
        $url = $this->getConfigData('image');

        return $url ? Storage::url($url) : "tamara/image/tamara.png";
    }

    public function initiateSession(object $order): array|string
    {
        return $this->sdkRequest("/checkout", "POST", $this->getPayload($order));
    }

    public function authorizeOrder(string $orderId): bool
    {
        $result = $this->sdkRequest("/orders/{$orderId}/authorise", "POST");
        return ($result['status'] ?? null) === "authorised";
    }

    public function captureOrder(string $orderId, object $order): array|string
    {
        $payload = $this->getPayload($order);
        $payload['order_id'] = $orderId;

        return $this->sdkRequest("/payments/capture", "POST", $payload);
    }

    public function cancelOrder($orderId, $order) {
        $payload = $this->getPayload($order);
        $payload['order_id'] = $orderId;

        return $this->sdkRequest("/orders/{$orderId}/cancel", "POST", $payload);
    }

    private function sdkRequest(string $endpoint, string $method = 'POST', ?array $data = null): array
    {
        $url = rtrim($this->baseUrl, '/') . $endpoint;

        try {
            $http = Http::withToken($this->bearerToken)
                ->acceptJson()
                ->timeout(30);

            /** @var Response $response */
            $response = $method === 'POST' 
                ? $http->post($url, $data ?? []) 
                : $http->get($url);

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Tamara SDK Request Error", [
                'url'     => $url,
                'method'  => $method,
                'data'    => $data,
                'message' => $e->getMessage(),
            ]);

            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    private function getConsumerPayload(object $customer, object $billingAddress): array
    {
        return [
            'uuid'         => $customer->id ?? '',
            'email'        => $customer->email ?? '',
            'first_name'   => $customer->first_name ?: '-',
            'last_name'    => $customer->last_name ?: '-',
            'phone_number' => $billingAddress->phone ?? 'NA',
        ];
    }

    private function getAddressPayload(object $address): array
    {
        return [
            'city'         => $address->city ?? 'NA',
            'country_code' => $address->country ?? 'NA',
            'first_name'   => $address->first_name ?? '-',
            'last_name'    => $address->last_name ?? '-',
            'line1'        => $address->address ?? 'NA',
            'line2'        => $address->address ?? 'NA',
            'phone_number' => $address->phone ?? 'NA',
            'region'       => $address->state ?? 'NA',
        ];
    }

    private function getItemsPayload(object $cart, object $order): array
    {
        return collect($cart->items)->map(function ($item) use ($order) {
            return [
                'name'         => $item->name,
                'type'         => 'Physical',
                'reference_id' => $item->sku,
                'sku'          => $item->sku,
                'quantity'     => $item->quantity,
                'item_url'     => config("app.url") . '/' . $item->product->url_key,
                'unit_price'   => [
                    'amount'   => round($item->base_price, 2),
                    'currency' => $order->order_currency_code,
                ],
                'total_amount' => [
                    'amount'   => round($item->quantity * $item->base_price, 2),
                    'currency' => $order->order_currency_code,
                ],
            ];
        })->toArray();
    }

    private function getMerchantUrl(): array
    {
        return [
            'cancel'       => $this->cancelUrl,
            'failure'      => $this->failureUrl,
            'success'      => $this->successUrl,
            'notification' => $this->notifyUrl,
        ];
    }

    private function getRiskAssessment(): array
    {
        $now = Carbon::now()->format('d-m-Y');

        return [
            "is_premium_customer"       => true,
            "is_existing_customer"      => true,
            "account_creation_date"     => $now,
            "date_of_first_transaction" => $now,
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

    private function getOrderDescription(object $cart): string
    {
        return collect($cart->items)->pluck('name')->implode(', ');
    }

    private function getPayload(object $order): array
    {
        $cart = Cart::getCart();

        return [
            'total_amount' => [
                'amount'   => round($cart->grand_total, 2),
                'currency' => $order->order_currency_code,
            ],
            'shipping_amount' => [
                'amount'   => round($cart->shipping_amount, 2),
                'currency' => $order->order_currency_code,
            ],
            'tax_amount' => [
                'amount'   => round($cart->tax_total, 2),
                'currency' => $order->order_currency_code,
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
    }
}
