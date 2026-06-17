<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePublicLeadRequest;
use App\Models\Lead;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;

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

        return response()->json([
            'success' => true,
            'message' => 'Lead created successfully.',
            'lead_id' => $lead->id,
        ], 201);
    }
}
