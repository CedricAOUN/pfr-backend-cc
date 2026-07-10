<?php

return [
  'premium' => [
    'monthly' => env('STRIPE_PRICE_PREMIUM_MONTHLY'),
    '6_months' => env('STRIPE_PRICE_PREMIUM_6_MONTHS'),
    'annual' => env('STRIPE_PRICE_PREMIUM_YEARLY'),
  ],
  'chef' => [
    'monthly' => env('STRIPE_PRICE_CHEF_MONTHLY'),
    '6_months' => env('STRIPE_PRICE_CHEF_6_MONTHS'),
    'annual' => env('STRIPE_PRICE_CHEF_YEARLY'),
  ]
];
