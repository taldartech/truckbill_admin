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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class LeadWebController extends Controller
{
    /** @var list<string> */
    private const DEFAULT_STATUS_FILTER = [
        'new',
        'contacted',
        'qualified',
        'demo_scheduled',
        'proposal_sent',
    ];

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

        $query = Lead::query()->with('assignee')->withLastActionAt();

        $statuses = $this->resolveStatusFilter($request);

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        $product = (string) $request->input('product', '');
        $allowedProducts = collect(Product::cases())->map->value->all();

        if ($product !== '' && in_array($product, $allowedProducts, true)) {
            $query->where('product', $product);
        } else {
            $product = '';
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('state', 'like', "%{$search}%");
            });
        }
        if ($request->user()?->hasAssignedLeadScope()) {
            $query->where('assigned_to', $request->user()->id);
        } elseif ($request->user()?->hasRole('Sales Manager')) {
            $teamIds = User::query()->where('manager_id', $request->user()->id)->pluck('id')->push($request->user()->id);
            $query->whereIn('assigned_to', $teamIds);
        }

        $this->applyAssignedToFilter($request, $query);

        $followUpFilter = (string) $request->input('follow_up', '');

        if ($followUpFilter !== '' && in_array($followUpFilter, ['overdue', 'due_today', 'upcoming'], true)) {
            $query->followUpFilter($followUpFilter);
        }

        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        if ($sort === 'last_action_at') {
            $query->orderByRaw('COALESCE(last_action_at, leads.created_at) '.$direction);
        } elseif ($sort === 'next_follow_up_at') {
            $query->orderByRaw('next_follow_up_at IS NULL')->orderBy('next_follow_up_at', $direction);
        } elseif (in_array($sort, ['name', 'status', 'created_at', 'company_name'], true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->latest();
        }

        return Inertia::render('Leads/Index', [
            'leads' => $query->paginate(20)->withQueryString()->through(function (Lead $lead) {
                $lead->setAttribute('last_action_ago', $lead->lastActionAgo());
                $lead->setAttribute('last_action_at', $lead->lastActionAt()->toIso8601String());
                $lead->setAttribute('follow_up_status', $lead->followUpStatus());

                return $lead;
            }),
            'filters' => [
                'search' => $request->input('search'),
                'status' => $statuses,
                'product' => $product !== '' ? $product : null,
                'assigned_to' => $request->input('assigned_to'),
                'follow_up' => $request->input('follow_up'),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'canCreate' => $request->user()?->can('create', Lead::class) ?? false,
            'canFilterByAssignee' => ! $request->user()?->hasAssignedLeadScope(),
            'assignees' => $this->assigneeFilterOptions($request),
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
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'product' => ['nullable', Rule::enum(Product::class)],
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'source' => ['required', 'string', 'max:100'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! empty($data['assigned_to'])) {
            abort_unless($request->user()?->can('leads.assign'), 403);
        } elseif (! $request->user()?->can('leads.assign')) {
            $data['assigned_to'] = $request->user()->id;
        } else {
            unset($data['assigned_to']);
        }

        $lead = $this->leadService->create([
            ...$data,
            'status' => $data['status'] ?? LeadStatus::New,
        ], $request->user());

        return redirect()->route('leads.show', $lead)->with('success', 'Lead created successfully.');
    }

    public function show(Request $request, Lead $lead): Response
    {
        Gate::authorize('view', $lead);

        $activities = LeadActivityPresenter::collection(
            Activity::forSubject($lead)->with('causer')->latest()->limit(10)->get()
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
            'next_follow_up_at' => ['nullable', 'date'],
        ]);

        if (isset($data['status'])) {
            $this->leadService->ensureLostHasRemarks($data['status'], $data['remarks'] ?? null, $lead);

            $reason = $data['status'] === LeadStatus::Lost->value
                ? trim((string) ($data['remarks'] ?? $lead->remarks))
                : null;

            $this->leadService->updateStatus($lead, LeadStatus::from($data['status']), $request->user(), $reason);
            unset($data['status']);
        }

        $this->applyAssignment($request, $lead, $data);

        if (array_key_exists('next_follow_up_at', $data)) {
            $followUpAt = filled($data['next_follow_up_at'])
                ? Carbon::parse($data['next_follow_up_at'])->startOfDay()
                : null;
            $this->leadService->scheduleFollowUp($lead->fresh(), $followUpAt, $request->user());
            unset($data['next_follow_up_at']);
        }

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

    /** @return list<array{id: int, name: string}> */
    private function assigneeFilterOptions(Request $request): array
    {
        $user = $request->user();

        if ($user?->hasAssignedLeadScope()) {
            return [];
        }

        if ($user?->hasRole('Sales Manager')) {
            $teamIds = User::query()
                ->where('manager_id', $user->id)
                ->pluck('id')
                ->push($user->id);

            return User::query()
                ->whereIn('id', $teamIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
                ->all();
        }

        return User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }

    /**
     * Resolve status filter values.
     * Missing status → active pipeline defaults.
     * status=all (or empty after explicit clear) → no status filter.
     *
     * @return list<string>
     */
    private function resolveStatusFilter(Request $request): array
    {
        if (! $request->exists('status')) {
            return self::DEFAULT_STATUS_FILTER;
        }

        $raw = $request->input('status');

        if ($raw === 'all' || $raw === '' || $raw === []) {
            return [];
        }

        $allowedStatuses = collect(LeadStatus::cases())->map->value->all();

        return array_values(array_intersect($this->filterValues($request, 'status'), $allowedStatuses));
    }

    /**
     * Normalize a request filter that may be a single string or an array of values.
     *
     * @return list<string>
     */
    private function filterValues(Request $request, string $key): array
    {
        $value = $request->input($key, []);

        if (is_string($value)) {
            return $value !== '' ? [$value] : [];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($item) => is_scalar($item) ? trim((string) $item) : '', $value),
            static fn (string $item) => $item !== '',
        ));
    }

    /** @param Builder<Lead> $query */
    private function applyAssignedToFilter(Request $request, Builder $query): void
    {
        if ($request->user()?->hasAssignedLeadScope() || ! $request->filled('assigned_to')) {
            return;
        }

        $value = (string) $request->input('assigned_to');

        if ($value === 'unassigned') {
            $query->whereNull('assigned_to');

            return;
        }

        $allowedIds = collect($this->assigneeFilterOptions($request))->pluck('id')->map(fn ($id) => (int) $id);

        if ($allowedIds->contains((int) $value)) {
            $query->where('assigned_to', (int) $value);
        }
    }
}
