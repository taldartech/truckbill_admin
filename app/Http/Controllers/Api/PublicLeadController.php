<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePublicLeadRequest;
use App\Models\User;
use App\Notifications\LeadCapturedNotification;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class PublicLeadController extends Controller
{
    public function __construct(private readonly LeadService $leadService) {}

    public function store(StorePublicLeadRequest $request): JsonResponse
    {
        $lead = $this->leadService->create([
            ...$request->validated(),
            'product' => $request->input('product_interested_in'),
            'status' => LeadStatus::New,
            'ip_address' => $request->input('ip_address', $request->ip()),
        ]);

        $recipients = User::role(['Super Admin', 'Admin'])->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new LeadCapturedNotification($lead));
        }

        return response()->json([
            'success' => true,
            'message' => 'Lead created successfully.',
            'lead_id' => $lead->id,
        ], 201);
    }
}
