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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        $stats = Cache::remember('crm.dashboard.stats', 300, function () {
            $byStatus = Lead::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
            $byProduct = Lead::query()->selectRaw('product, count(*) as total')->whereNotNull('product')->groupBy('product')->pluck('total', 'product');
            $bySource = Lead::query()->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source');

            return [
                'total' => Lead::count(),
                'new' => (int) ($byStatus['new'] ?? 0),
                'contacted' => (int) ($byStatus['contacted'] ?? 0),
                'qualified' => (int) ($byStatus['qualified'] ?? 0),
                'demo_scheduled' => (int) ($byStatus['demo_scheduled'] ?? 0),
                'proposal_sent' => (int) ($byStatus['proposal_sent'] ?? 0),
                'converted' => (int) ($byStatus['converted'] ?? 0),
                'lost' => (int) ($byStatus['lost'] ?? 0),
                'by_product' => $byProduct,
                'by_source' => $bySource,
            ];
        });

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

        $sort = $request->string('sort', 'created_at');
        $direction = $request->string('direction', 'desc') === 'asc' ? 'asc' : 'desc';
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

        $activities = Activity::forSubject($lead)->latest()->limit(30)->get();

        return Inertia::render('Leads/Show', [
            'lead' => $lead->load(['assignee', 'notes.author', 'invitations']),
            'activities' => $activities,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => collect(LeadStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'products' => collect(Product::cases())->map(fn ($p) => ['value' => $p->value, 'label' => $p->label()]),
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
        if (array_key_exists('assigned_to', $data)) {
            $this->leadService->assign($lead, $data['assigned_to'], $request->user());
            unset($data['assigned_to']);
        }
        if ($data !== []) {
            $lead->update($data);
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

        activity('lead')->performedOn($lead)->causedBy($request->user())->log('Note added');

        return back()->with('success', 'Note added.');
    }

    public function invite(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('invite', $lead);

        $data = $request->validate([
            'product' => ['required', Rule::in(['ownfleet', 'parchun', 'truckbill'])],
        ]);

        $result = $this->invitationService->createForLead($lead, $data['product'], $request->user()->id);

        Notification::route('mail', $lead->email)
            ->notify(new OnboardingInvitationNotification($result['url']));

        activity('lead')->performedOn($lead)->causedBy($request->user())
            ->withProperties(['url' => $result['url']])
            ->log('Onboarding invitation sent');

        return back()->with('success', 'Invitation sent: '.$result['url']);
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        Gate::authorize('delete', $lead);

        activity('lead')->performedOn($lead)->causedBy(request()->user())->log('Lead deleted');

        $lead->delete();

        return redirect()->route('leads.index')->with('success', 'Lead deleted.');
    }
}
