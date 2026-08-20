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
        $data = $request->validated();
        $product = $data['product_interested_in'] ?? null;
        unset($data['product_interested_in']);

        $lead = $this->leadService->create([
            ...$data,
            'product' => $product,
            'status' => LeadStatus::New,
            'ip_address' => $data['ip_address'] ?? $request->ip(),
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
