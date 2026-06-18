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
            'key' => 'refund_policy',
            'label' => 'Refund Policy',
            'url' => 'https://truckbill.in/policies/refund-policy.html',
        ],
        [
            'key' => 'acceptable_use_policy',
            'label' => 'Acceptable Use Policy',
            'url' => 'https://truckbill.in/policies/acceptable-use-policy.html',
        ],
    ],
];
