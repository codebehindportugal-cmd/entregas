<?php

return [
    'url' => env('WOOCOMMERCE_URL'),
    'key' => env('WOOCOMMERCE_KEY'),
    'secret' => env('WOOCOMMERCE_SECRET'),
    'statuses' => env('WOOCOMMERCE_STATUSES', 'processing,on-hold,pending,subscricao,wc-subscricao'),
    'subscription_statuses' => env('WOOCOMMERCE_SUBSCRIPTION_STATUSES', 'active,on-hold,pending'),
    'sync_subscriptions' => env('WOOCOMMERCE_SYNC_SUBSCRIPTIONS', true),
    'per_page' => (int) env('WOOCOMMERCE_PER_PAGE', 50),
];
