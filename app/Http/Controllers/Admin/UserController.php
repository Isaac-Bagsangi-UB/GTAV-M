<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Administrators create staff accounts and assign roles. */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::with('chapter')
            ->when(
                $request->filled('role') && array_key_exists($request->input('role'), User::ROLES),
                fn ($q) => $q->where('role', $request->input('role'))
            )
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->input('q').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('company', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users]);
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'user' => new User(['role' => User::ROLE_MANAGER]),
            'chapters' => Chapter::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = User::create($this->validated($request));
        AuditLog::record('user.created', 'Created account '.$user->email.' with role '.$user->roleLabel());

        return redirect()->route('admin.users.index')->with('success', 'Account created.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user,
            'chapters' => Chapter::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if ($user->is($request->user()) && $data['role'] !== User::ROLE_ADMIN) {
            return back()->withInput()->with('error', 'You cannot remove your own administrator role.');
        }

        $oldRole = $user->roleLabel();
        $user->update($data);
        AuditLog::record(
            'user.updated',
            'Updated account '.$user->email.($oldRole !== $user->roleLabel() ? ': role changed from '.$oldRole.' to '.$user->roleLabel() : '')
        );

        return redirect()->route('admin.users.index')->with('success', 'Account updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();
        AuditLog::record('user.deleted', 'Deleted account '.$user->email);

        return back()->with('success', 'Account deleted.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'chapter_id' => [
                Rule::requiredIf(in_array($request->input('role'), User::CHAPTER_ROLES, true)),
                'nullable', 'integer', 'exists:chapters,id',
            ],
            'company' => ['nullable', 'string', 'max:160'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => [$user ? 'nullable' : 'required', Password::defaults()],
        ]);

        // Only chapter-scoped roles keep a chapter.
        if (! in_array($data['role'], User::CHAPTER_ROLES, true)) {
            $data['chapter_id'] = null;
        }

        // Leaving the password blank on edit keeps the current one.
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
