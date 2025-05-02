<?php

return [
    [
        'key' => 'sales.payment_methods.tamara',
        'name' => 'tamara::app.system.title',
        'info' => 'tamara::app.system.info',
        'sort' => 5,
        'fields' => [
            [
                'name' => 'title',
                'title' => 'tamara::app.system.p-title',
                'type' => 'text',
                'depend' => 'active:1',
                'validation' => 'required_if:active,1',
                'channel_based' => false,
                'locale_based' => true,
            ],
            [
                'name' => 'description',
                'title' => 'tamara::app.system.p-description',
                'type' => 'textarea',
                'channel_based' => false,
                'locale_based' => true,
            ],
            [
                'name' => 'base-url',
                'title' => 'tamara::app.system.base-url',
                'type' => 'text',
                'depend' => 'active:1',
                'validation' => 'required_if:active,1',
                'channel_based' => false,
                'locale_based' => true,
            ],
            [
                'name' => 'bearer-token',
                'title' => 'tamara::app.system.bearer-token',
                'type' => 'text',
                'depend' => 'active:1',
                'validation' => 'required_if:active,1',
                'channel_based' => false,
                'locale_based' => true,
            ],
            [
                'name' => 'webhook-token',
                'title' => 'tamara::app.system.webhook-token',
                'type' => 'text',
                'depend' => 'active:1',
                'validation' => 'required_if:active,1',
                'channel_based' => false,
                'locale_based' => true,
            ],
            [
                'name' => 'public-key',
                'title' => 'tamara::app.system.public-key',
                'type' => 'text',
                'depend' => 'active:1',
                'validation' => 'required_if:active,1',
                'channel_based' => false,
                'locale_based' => true,
            ],
            [
                'name' => 'cancel-url',
                'title' => 'tamara::app.system.cancel-url',
                'type' => 'hidden',
                'channel_based' => false,
                'locale_based' => true,
                'info' => ' Order Cancelled Callback URL: ' . config('app.url') . "/tamara/order-cancel",
            ],
            [
                'name' => 'failure-url',
                'title' => 'tamara::app.system.failure-url',
                'type' => 'hidden',
                'channel_based' => false,
                'locale_based' => true,
                'info' => ' Order Failed Callback URL: ' . config('app.url') . "/tamara/order-failed",
            ],
            [
                'name' => 'success-url',
                'title' => 'tamara::app.system.success-url',
                'type' => 'hidden',
                'channel_based' => false,
                'locale_based' => true,
                'info' => ' Order Completed Callback URL: ' . config('app.url') . "/tamara/order-success",
            ],
            [
                'name' => 'notify-url',
                'title' => 'tamara::app.system.notify-url',
                'type' => 'hidden',
                'channel_based' => false,
                'locale_based' => true,
                'info' => ' Webhook URL: ' . config('app.url') . "/tamara/webhook",
            ],
            [
                'name' => 'active',
                'title' => 'admin::app.configuration.index.sales.payment-methods.status',
                'type' => 'boolean',
                'channel_based' => true,
                'locale_based' => false,
            ],
            [
                'name' => 'sort',
                'title' => 'tamara::app.system.sort-order',
                'type' => 'select',
                'options' => [
                    [
                        'title' => '1',
                        'value' => 1,
                    ],
                    [
                        'title' => '2',
                        'value' => 2,
                    ],
                    [
                        'title' => '3',
                        'value' => 3,
                    ],
                    [
                        'title' => '4',
                        'value' => 4,
                    ],
                    [
                        'title' => '5',
                        'value' => 5,
                    ],
                ],
            ],
        ]
    ]
];