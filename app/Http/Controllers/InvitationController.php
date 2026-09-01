<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Seat invitations. An invite is only an offer — no user row exists until it
 * is accepted, which is why the Team page lists them separately.
 */
class InvitationController extends Controller
{
    /** Invitations are valid for a week, as the Team page states. */
    private const VALID_DAYS = 7;

    public function store(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace();
        abort_if($workspace === null, 403);

        abort_unless(
            $request->user()->roleIn($workspace)?->canCreateProjects() ?? false,
            403,
        );

        $data = $request->validate([
            'email' => [
                'required', 'email', 'max:180',
                // Someone already holding a seat cannot be invited again
                Rule::unique('invitations', 'email')
                    ->where('workspace_id', $workspace->id)
                    ->whereNull('accepted_at')
                    ->whereNull('revoked_at'),
            ],
            'role' => ['required', Rule::enum(WorkspaceRole::class)],
            'message' => ['nullable', 'string', 'max:500'],
        ], [
            'email.unique' => 'That address already has a pending invitation.',
        ]);

        Invitation::create([
            'workspace_id' => $workspace->id,
            'email' => $data['email'],
            'role' => $data['role'],
            'message' => $data['message'] ?? null,
            'token' => Invitation::freshToken(),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(self::VALID_DAYS),
        ]);

        return back()->with('success', "Invitation sent to {$data['email']}.");
    }

    public function destroy(Request $request, Invitation $invitation): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace();
        abort_if($workspace?->id !== $invitation->workspace_id, 403);

        abort_unless(
            $request->user()->roleIn($workspace)?->canCreateProjects() ?? false,
            403,
        );

        $invitation->forceFill(['revoked_at' => now()])->save();

        return back()->with('success', 'Invitation revoked.');
    }
}
