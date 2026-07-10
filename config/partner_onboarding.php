<?php

return [
    'invitation_ttl_days' => (int) env('PARTNER_ONBOARDING_TTL_DAYS', 7),
    'documents_reply_to' => env('PARTNER_DOCUMENTS_EMAIL', 'salespartnersupport@truckbill.in'),
];
