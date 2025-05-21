<?php

namespace Bagisto\Tamara\Controllers;

use Bagisto\Tamara\Payment\Tamara;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Transformers\OrderResource;

class TamaraController extends Controller
{
    protected OrderRepository $orderRepository;
    protected InvoiceRepository $invoiceRepository;
    protected Tamara $client;

    public function __construct(OrderRepository $orderRepository, InvoiceRepository $invoiceRepository, Tamara $client)
    {
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->client = $client;
    }

    public function init()
    {
        try {
            $cart = Cart::getCart();
            $this->validateOrder();

            $data = (new OrderResource($cart))->jsonSerialize();
            $order = $this->orderRepository->create($data);
            $order = $this->orderRepository->find($order->id);

            $payload = $this->client->initiateSession($order);

            if (isset($payload['checkout_url'])) {
                $order->tamara_order_id = $payload['order_id'];
                $order->tamara_checkout_id = $payload['checkout_id'];
                $order->save();
                return redirect($payload['checkout_url']);
            }

            $error = $payload['message'] ?? 'Something went wrong';
            session()->flash('error', $error);
            return redirect()->route('shop.checkout.onepage.index');
        } catch (\Exception $ex) {
            Log::error("Tamara Init Exception", ['message' => $ex->getMessage()]);
            session()->flash('error', $ex->getMessage());
            return redirect()->route('shop.checkout.onepage.index');
        }
    }

    public function callback(Request $request)
    {
        Log::debug("Tamara Callback", $request->all());
        // TODO: Implement Callback Logic
    }

    public function cancel(Request $request)
    {
        session()->flash('error', "Payment was not successful. You may have cancelled the process.");
        return redirect()->route('shop.checkout.onepage.index');
    }

    public function failed(Request $request)
    {
        session()->flash('error', "Payment was not successful. You may have cancelled the process.");
        return redirect()->route('shop.checkout.onepage.index');
    }

    public function success(Request $request)
    {
        try {
            $paymentStatus = $request->get('paymentStatus');
            $orderId = $request->get('orderId');

            $order = $this->orderRepository->findOneWhere([
                'tamara_order_id' => $orderId,
            ]);
            
            if ($order && $order->status != "pending") {
                return redirect()->route('shop.customers.account.orders.index');
            }

            if ($paymentStatus === 'approved' && $this->client->authorizeOrder($orderId)) {
                $response = $this->client->captureOrder($orderId, $order);

                if (isset($response['status']) && in_array($response['status'], ['fully_captured', 'partially_captured'])) {
                    $this->orderRepository->update(['status' => 'processing'], $order->id);

                    if ($order->canInvoice()) {
                        $this->invoiceRepository->create($this->prepareInvoiceData($order));
                    }

                    Cart::deActivateCart();
                    return view('shop::checkout.success', compact('order'));
                }
            }
            session()->flash('error', 'Something went wrong');
            return redirect()->route('shop.checkout.onepage.index');
        } catch (\Exception $ex) {
            Log::error("Tamara Success Exception", ['message' => $ex->getMessage()]);
            session()->flash('error', 'Something went wrong');
            return redirect()->route('shop.checkout.onepage.index');
        }
    }

    public function webhook(Request $request)
    {
        $payloadData = $request->all();
        try {
            if (!isset($payloadData['event_type']) || !isset($payloadData['order_id'])) {
                return response()->json([
                    'status' => 'success'
                ]);
            }
            $paymentStatus = $payloadData['event_type'];
            $orderId       = $payloadData['order_id'];
            $order         = $this->orderRepository->findOneWhere([
                'tamara_order_id' => $orderId,
            ]);
            
            if ($order && $order->status != "pending") {
                return response()->json([
                    'status' => 'success'
                ]);
            }
            switch ($paymentStatus) {
                case 'order_approved':
                    if ($this->client->authorizeOrder($orderId)) {
                        $response = $this->client->captureOrder($orderId, $order);
                        if (isset($response['status']) && in_array($response['status'], ['fully_captured', 'partially_captured'])) {
                            $this->orderRepository->update(['status' => 'processing'], $order->id);

                            if ($order->canInvoice()) {
                                $this->invoiceRepository->create($this->prepareInvoiceData($order));
                            }

                            Cart::deActivateCart();
                            return response()->json([
                                'status' => 'success'
                            ]);
                        }
                    }
                    break;
                case 'order_expired':
                case 'order_declined':
                case 'order_canceled':
                    $this->client->cancelOrder($orderId, $order);
                    break;
                default:
                    Log::error("Tamara Webhook Start");
                    Log::debug(print_r($payloadData, true));
                    Log::error("Tamara Webhook End");
                    break;
            }
            return response()->json([
                'status' => 'success'
            ]);
        } catch (\Exception $ex) {
            Log::debug("Tamara Webhook Error Message : " . $ex->getMessage());
            Log::debug(print_r($payloadData, true));
            Log::debug("Tamara Webhook Error Trace : " . $ex->getTraceAsString());
            return response()->json([
                'status' => 'failure',
                'message' => 'Something went wrong',
            ]);
        }
    }

    protected function validateOrder(): void
    {
        $cart = Cart::getCart();

        $minimumOrderAmount = (float) core()->getConfigData('sales.order_settings.minimum_order.minimum_order_amount') ?: 0;

        if (!Cart::haveMinimumOrderAmount()) {
            throw new \Exception(trans('shop::app.checkout.cart.minimum-order-message', [
                'amount' => core()->currency($minimumOrderAmount)
            ]));
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
    protected function prepareInvoiceData($order): array
    {
        $invoiceData = ["order_id" => $order->id];

        foreach ($order->items as $item) {
            $invoiceData['invoice']['items'][$item->id] = $item->qty_to_invoice;
        }

        return $invoiceData;
    }
}