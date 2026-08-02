<?php
return [
    'default_provider' => 'unconfigured',
    'providers' => [
        'vision' => 'unconfigured',
        'llm' => 'unconfigured',
        'image_generation' => 'unconfigured',
        'search' => 'unconfigured',
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 86400,
    ],
    'review' => [
        'require_human_review' => true,
        'default_status' => 'draft',
    ],
    'limits' => [
        'monthly_vendor_cost' => 0.0,
        'monthly_vendor_requests' => 0,
    ],
];
