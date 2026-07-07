<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Enums\Product;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use App\Notifications\OnboardingInvitationNotification;
use App\Services\LeadService;
use App\Services\OnboardingInvitationService;
use App\Support\CrmDashboardStats;
use App\Support\LeadActivityPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class LeadWebController extends Controller
{
    public function __construct(
        private readonly LeadService $leadService,
        private readonly OnboardingInvitationService $invitationService,
    ) {}

    public function dashboard(): Response
    {
        Gate::authorize('viewAny', Lead::class);

        $stats = CrmDashboardStats::build();

        return Inertia::render('Dashboard', ['stats' => $stats]);
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Lead::class);

        $query = Lead::query()->with('assignee');

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
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }
        if ($request->user()?->hasRole('Sales Executive')) {
            $query->where('assigned_to', $request->user()->id);
        } elseif ($request->user()?->hasRole('Sales Manager')) {
            $teamIds = User::query()->where('manager_id', $request->user()->id)->pluck('id')->push($request->user()->id);
            $query->whereIn('assigned_to', $teamIds);
        }

        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, ['name', 'status', 'created_at', 'company_name'], true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->latest();
        }

        return Inertia::render('Leads/Index', [
            'leads' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['status', 'search', 'product', 'sort', 'direction']),
            'canCreate' => $request->user()?->can('create', Lead::class) ?? false,
            'statuses' => collect(LeadStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'products' => collect(Product::cases())->map(fn ($p) => ['value' => $p->value, 'label' => $p->label()]),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Lead::class);

        return Inertia::render('Leads/Create', [
            'statuses' => collect(LeadStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'products' => collect(Product::cases())->map(fn ($p) => ['value' => $p->value, 'label' => $p->label()]),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'canAssign' => $request->user()?->can('leads.assign') ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Lead::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'product' => ['nullable', Rule::enum(Product::class)],
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! empty($data['assigned_to'])) {
            abort_unless($request->user()?->can('leads.assign'), 403);
        } elseif (array_key_exists('assigned_to', $data)) {
            unset($data['assigned_to']);
        }

        $lead = $this->leadService->create([
            ...$data,
            'source' => 'manual',
            'status' => $data['status'] ?? LeadStatus::New,
        ], $request->user());

        return redirect()->route('leads.show', $lead)->with('success', 'Lead created successfully.');
    }

    public function show(Request $request, Lead $lead): Response
    {
        Gate::authorize('view', $lead);

        $activities = LeadActivityPresenter::collection(
            Activity::forSubject($lead)->with('causer')->latest()->limit(30)->get()
        );

        return Inertia::render('Leads/Show', [
            'lead' => $lead->load(['assignee', 'notes.author', 'invitations']),
            'activities' => $activities,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => collect(LeadStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'products' => collect(Product::cases())->map(fn ($p) => ['value' => $p->value, 'label' => $p->label()]),
            'canUpdate' => $request->user()?->can('update', $lead) ?? false,
            'canAssign' => $request->user()?->can('assign', $lead) ?? false,
            'canDelete' => $request->user()?->can('delete', $lead) ?? false,
            'canInvite' => $request->user()?->can('invite', $lead) ?? false,
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate([
            'status' => ['sometimes', Rule::enum(LeadStatus::class)],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'product' => ['nullable', Rule::enum(Product::class)],
            'remarks' => ['nullable', 'string'],
        ]);

        if (isset($data['status'])) {
            $this->leadService->updateStatus($lead, LeadStatus::from($data['status']), $request->user());
            unset($data['status']);
        }

        $this->applyAssignment($request, $lead, $data);

        if ($data !== []) {
            $this->leadService->updateDetails($lead, $data, $request->user());
        }

        return back()->with('success', 'Lead updated.');
    }

    public function storeNote(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        LeadNote::create([
            'lead_id' => $lead->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        activity('lead')
            ->performedOn($lead)
            ->causedBy($request->user())
            ->event('note_added')
            ->log('Note added');

        return back()->with('success', 'Note added.');
    }

    public function invite(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('invite', $lead);

        $data = $request->validate([
            'product' => ['required', Rule::enum(Product::class)],
        ]);

        $result = $this->invitationService->createForLead($lead, $data['product'], $request->user()->id);

        Notification::route('mail', $lead->email)
            ->notify(new OnboardingInvitationNotification($lead->name, $result['url']));

        activity('lead')
            ->performedOn($lead)
            ->causedBy($request->user())
            ->event('invite_sent')
            ->withProperties(['product' => $data['product'], 'url' => $result['url']])
            ->log('Onboarding invitation sent for '.Product::from($data['product'])->label());

        return back()->with('success', 'Invitation sent successfully.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        Gate::authorize('delete', $lead);

        activity('lead')
            ->performedOn($lead)
            ->causedBy(request()->user())
            ->event('deleted')
            ->log('Lead deleted');

        $lead->delete();

        return redirect()->route('leads.index')->with('success', 'Lead deleted.');
    }

    private function applyAssignment(Request $request, Lead $lead, array &$data): void
    {
        if (! array_key_exists('assigned_to', $data)) {
            return;
        }

        $newAssignee = $data['assigned_to'];
        unset($data['assigned_to']);

        if ((int) $newAssignee === (int) $lead->assigned_to) {
            return;
        }

        Gate::authorize('assign', $lead);
        $this->leadService->assign($lead, $newAssignee, $request->user());
    }
}
