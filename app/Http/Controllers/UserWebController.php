<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PartnerOnboardingInvitationNotification;
use App\Services\PartnerOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserWebController extends Controller
{
    public function __construct(
        private readonly PartnerOnboardingService $partnerOnboardingService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $query = User::query()
            ->with(['roles', 'manager', 'salesPartnerProfile', 'partnerOnboardingInvitations'])
            ->withCount('assignedLeads')
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->role($request->string('role'));
        }

        $users = $query->paginate(20)->withQueryString();
        $users->getCollection()->transform(function (User $user): User {
            $user->setAttribute('partner_onboarding_status', $user->partnerOnboardingStatus());

            return $user;
        });

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role']),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('Users/Create', [
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'managers' => $this->managerOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $isSalesPartner = $request->string('role')->toString() === 'Sales Partner';
        $skipOnboarding = $request->boolean('skip_onboarding');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'manager_id' => ['nullable', 'exists:users,id'],
            'skip_onboarding' => ['sometimes', 'boolean'],
        ];

        if (! $isSalesPartner || $skipOnboarding) {
            $rules['password'] = ['required', 'confirmed', Password::defaults()];
        } else {
            $rules['password'] = ['nullable'];
        }

        $data = $request->validate($rules);

        $requiresOnboarding = $isSalesPartner && ! $skipOnboarding;

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($requiresOnboarding ? Str::random(32) : $data['password']),
            'manager_id' => $data['manager_id'] ?? null,
            'email_verified_at' => $requiresOnboarding ? null : now(),
        ]);

        $user->assignRole($data['role']);

        if ($requiresOnboarding) {
            $result = $this->partnerOnboardingService->createInvitation($user, $request->user()->id);

            Notification::route('mail', $user->email)
                ->notify(new PartnerOnboardingInvitationNotification($user->name, $result['url']));
        }

        activity('user')->performedOn($user)->causedBy($request->user())->log('CRM user created');

        $message = $requiresOnboarding
            ? 'Sales Partner created. An onboarding email has been sent.'
            : 'User created successfully.';

        return redirect()->route('users.index')->with('success', $message);
    }

    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        $user->load(['roles', 'manager', 'salesPartnerProfile', 'partnerOnboardingInvitations']);

        $profile = $user->salesPartnerProfile;

        return Inertia::render('Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'manager_id' => $user->manager_id,
                'role' => $user->roles->first()?->name,
            ],
            'partnerOnboardingStatus' => $user->isSalesPartner() ? $user->partnerOnboardingStatus() : null,
            'salesPartnerProfile' => $profile === null ? null : [
                'legal_name' => $profile->legal_name,
                'mobile' => $profile->mobile,
                'working_location' => $profile->working_location,
                'current_company_name' => $profile->current_company_name,
                'pan_number' => $profile->pan_number,
                'aadhar_number' => $profile->aadhar_number,
                'bank_name' => $profile->bank_name,
                'bank_account_number' => $profile->bank_account_number,
                'ifsc_code' => $profile->ifsc_code,
                'onboarding_completed_at' => $profile->onboarding_completed_at?->toIso8601String(),
            ],
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'managers' => $this->managerOptions($user->id),
            'canDelete' => request()->user()?->can('delete', $user) ?? false,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'manager_id' => ['nullable', 'exists:users,id'],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'manager_id' => $data['manager_id'] ?? null,
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        $user->syncRoles([$data['role']]);

        activity('user')->performedOn($user)->causedBy($request->user())->log('CRM user updated');

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        activity('user')->performedOn($user)->causedBy(request()->user())->log('CRM user deleted');

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted.');
    }

    private function managerOptions(?int $excludeId = null): array
    {
        return User::query()
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->role(['Super Admin', 'Admin', 'Sales Manager'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }
}
