<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Product;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Notifications\OnboardingInvitationNotification;
use App\Services\OnboardingInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function __construct(private readonly OnboardingInvitationService $invitationService) {}

    public function store(Request $request, Lead $lead): JsonResponse
    {
        Gate::authorize('invite', $lead);

        $data = $request->validate([
            'product' => ['required', Rule::enum(Product::class)],
        ]);

        $result = $this->invitationService->createForLead(
            $lead,
            $data['product'],
            $request->user()->id,
        );

        Notification::route('mail', $lead->email)
            ->notify(new OnboardingInvitationNotification($lead->name, $result['url']));

        activity('lead')
            ->performedOn($lead)
            ->causedBy($request->user())
            ->event('invite_sent')
            ->withProperties(['url' => $result['url'], 'product' => $data['product']])
            ->log('Onboarding invitation sent for '.Product::from($data['product'])->label());

        return response()->json([
            'success' => true,
            'url' => $result['url'],
            'invitation_id' => $result['invitation']->id,
        ], 201);
    }
}
