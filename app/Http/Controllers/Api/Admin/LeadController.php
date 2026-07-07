<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\LeadStatus;
use App\Enums\Product;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use App\Services\LeadService;
use App\Services\OnboardingInvitationService;
use App\Support\LeadActivityPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class LeadController extends Controller
{
    public function __construct(
        private readonly LeadService $leadService,
        private readonly OnboardingInvitationService $invitationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Lead::class);

        $query = Lead::query()->with('assignee')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('product')) {
            $query->where('product', $request->string('product'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        if ($request->user()?->hasRole('Sales Executive')) {
            $query->where('assigned_to', $request->user()->id);
        } elseif ($request->user()?->hasRole('Sales Manager')) {
            $teamIds = User::query()->where('manager_id', $request->user()->id)->pluck('id')->push($request->user()->id);
            $query->whereIn('assigned_to', $teamIds);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Lead::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email'],
            'product' => ['nullable', Rule::enum(Product::class)],
            'source' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
        ]);

        $lead = $this->leadService->create([
            ...$data,
            'source' => $data['source'] ?? 'manual',
            'status' => LeadStatus::New,
        ], $request->user());

        return response()->json($lead->load('assignee'), 201);
    }

    public function show(Lead $lead): JsonResponse
    {
        Gate::authorize('view', $lead);

        return response()->json($lead->load(['assignee', 'notes.author', 'invitations']));
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['sometimes', 'email'],
            'product' => ['nullable', Rule::enum(Product::class)],
            'remarks' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(LeadStatus::class)],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'rejection_reason' => ['nullable', 'string'],
        ]);

        if (isset($data['status'])) {
            $this->leadService->updateStatus($lead, LeadStatus::from($data['status']), $request->user(), $data['rejection_reason'] ?? null);
            unset($data['status'], $data['rejection_reason']);
        }

        if (array_key_exists('assigned_to', $data)) {
            $newAssignee = $data['assigned_to'];
            unset($data['assigned_to']);

            if ((int) $newAssignee !== (int) $lead->assigned_to) {
                Gate::authorize('assign', $lead);
                $this->leadService->assign($lead, $newAssignee, $request->user());
            }
        }

        if ($data !== []) {
            $this->leadService->updateDetails($lead, $data, $request->user());
        }

        return response()->json($lead->fresh(['assignee', 'notes.author']));
    }

    public function addNote(Request $request, Lead $lead): JsonResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $note = LeadNote::create([
            'lead_id' => $lead->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        activity('lead')
            ->performedOn($lead)
            ->causedBy($request->user())
            ->event('note_added')
            ->log('Note added');

        return response()->json($note->load('author'), 201);
    }

    public function activities(Lead $lead): JsonResponse
    {
        Gate::authorize('view', $lead);

        return response()->json(
            LeadActivityPresenter::collection(
                Activity::forSubject($lead)->with('causer')->latest()->limit(50)->get()
            )
        );
    }
}
