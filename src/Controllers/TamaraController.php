<?php

namespace Bagisto\Tamara\Controllers;

use Bagisto\Tamara\Payment\Tamara;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Transformers\OrderResource;

class TamaraController extends Controller
{
    /**
     * OrderRepository $orderRepository
     *
     * @var \Webkul\Sales\Repositories\OrderRepository
     */
    protected $orderRepository;

    /**
     * InvoiceRepository $invoiceRepository
     *
     * @var \Webkul\Sales\Repositories\InvoiceRepository
     */
    protected $invoiceRepository;

    /**
     * Tamara $client
     *
     * @var \Bagisto\Tamara\Payment\Tamara
     */
    protected $client;

    /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Attribute\Repositories\OrderRepository $orderRepository
     * @return void
     */
    public function __construct(OrderRepository $orderRepository, InvoiceRepository $invoiceRepository, Tamara $client) {
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->client = $client;
    }
    public function init() {
        //$this->validateOrder();
        try {
            $cart    = Cart::getCart();
            $data    = (new OrderResource($cart))->jsonSerialize();
            $order   = $this->orderRepository->create($data);
            $order   = $this->orderRepository->find($order->id);
            $payload = $this->client->initiateSession($order);
            $error   = "";
            if (isset($payload['checkout_url'])) {
                return redirect($payload['checkout_url']);
            } else if (isset($payload['message'])) {
                $error = $payload['message'];
            } else {
                $error = "Something went wrong";
            }
            session()->flash('error', $error);
            return redirect()->route('shop.checkout.cart.index');
        } catch (\Exception $ex) {
            Log::debug("Tamara Checkout Session Creation Failed: " . $ex->getMessage());
            session()->flash('error', $error);
            return redirect()->route('shop.checkout.cart.index');
        }
    }
    public function callback() {
        //TODO : Implement Callback
    }
    


    /**
     * Validate order before creation.
     *
     * @return void|\Exception
     */
    protected function validateOrder()
    {
        $cart = Cart::getCart();
        Log::debug(print_r([$cart->shipping_address, $cart->items, $cart->customer], true));
        $minimumOrderAmount = (float) core()->getConfigData('sales.order_settings.minimum_order.minimum_order_amount') ?: 0;
        if (!Cart::haveMinimumOrderAmount()) {
            throw new \Exception(trans('shop::app.checkout.cart.minimum-order-message', ['amount' => core()->currency($minimumOrderAmount)]));
        }
        if ($cart->haveStockableItems() && !$cart->shipping_address) {
            throw new \Exception(trans('shop::app.checkout.cart.check-shipping-address'));
        }
        if (!$cart->billing_address) {
            throw new \Exception(trans('shop::app.checkout.cart.check-billing-address'));
        }
        if ($cart->haveStockableItems() && !$cart->selected_shipping_rate) {
            throw new \Exception(trans('shop::app.checkout.cart.specify-shipping-method'));
        }
        if (!$cart->payment) {
            throw new \Exception(trans('shop::app.checkout.cart.specify-payment-method'));
        }
    }
}