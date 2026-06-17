<?php

return [
    'otp_length' => 6,
    'otp_ttl_minutes' => 15,
    'otp_max_attempts' => 5,
    'invitation_ttl_days' => 7,

    'policies' => [
        [
            'key' => 'terms_and_conditions',
            'label' => 'Terms & Conditions',
            'url' => 'https://truckbill.in/policies/terms-and-conditions.html',
        ],
        [
            'key' => 'privacy_policy',
            'label' => 'Privacy Policy',
            'url' => 'https://truckbill.in/policies/privacy-policy.html',
        ],
        [
            'key' => 'data_processing_consent',
            'label' => 'Data Processing Consent',
            'url' => 'https://truckbill.in/policies/data-processing-consent.html',
        ],
        [
            'key' => 'product_usage_agreement',
            'label' => 'Product Usage Agreement',
            'url' => 'https://truckbill.in/policies/product-usage-agreement.html',
        ],
    ],
];
