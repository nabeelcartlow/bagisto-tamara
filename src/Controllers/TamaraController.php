<?php

namespace Bagisto\Tamara\Controllers;

use Bagisto\Tamara\Payment\Tamara;
use Illuminate\Routing\Controller;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\InvoiceRepository;

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
        //TODO : Implement Checkout 
        return redirect("https://tamara.co/en-SA");
    }
    public function callback() {
        //TODO : Implement Callback
    }
}