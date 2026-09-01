<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The per-event notification matrix.
 *
 * A row per (user, event) rather than a JSON blob, so adding a channel is a
 * migration instead of a data rewrite — and a user who predates an event
 * simply gets the shipped default the first time they open this page.
 */
class NotificationPreferenceController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        // Backfill any event added since this account was created
        $user->applyDefaultNotificationPreferences();

        return Inertia::render('settings/notifications', [
            'preferences' => $user->notificationPreferences()
                ->orderByRaw('FIELD(event, ?, ?, ?, ?, ?, ?, ?)', array_keys(NotificationPreference::DEFAULTS))
                ->get()
                ->map(fn (NotificationPreference $preference) => [
                    'event' => $preference->event,
                    'label' => $preference->label(),
                    'inApp' => $preference->in_app,
                    'email' => $preference->email,
                    'push' => $preference->push,
                ])
                ->all(),
            'digest' => [
                'frequency' => $user->digest_frequency,
                'quietFrom' => $user->quiet_hours_start?->format('H:i'),
                'quietTo' => $user->quiet_hours_end?->format('H:i'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.event' => ['required', 'string', 'max:60'],
            'preferences.*.inApp' => ['required', 'boolean'],
            'preferences.*.email' => ['required', 'boolean'],
            'preferences.*.push' => ['required', 'boolean'],
            'digest.frequency' => ['required', 'in:off,daily,weekly'],
            'digest.quietFrom' => ['nullable', 'date_format:H:i'],
            'digest.quietTo' => ['nullable', 'date_format:H:i'],
        ]);

        $user = $request->user();
        $known = array_keys(NotificationPreference::DEFAULTS);

        DB::transaction(function () use ($user, $data, $known) {
            foreach ($data['preferences'] as $preference) {
                // Ignore anything the client invented
                if (! in_array($preference['event'], $known, true)) {
                    continue;
                }

                $user->notificationPreferences()->updateOrCreate(
                    ['event' => $preference['event']],
                    [
                        'in_app' => $preference['inApp'],
                        'email' => $preference['email'],
                        'push' => $preference['push'],
                    ],
                );
            }

            $user->forceFill([
                'digest_frequency' => $data['digest']['frequency'],
                'quiet_hours_start' => $data['digest']['quietFrom'] ?? null,
                'quiet_hours_end' => $data['digest']['quietTo'] ?? null,
            ])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Notification preferences saved.']);

        return to_route('notification-preferences.edit');
    }
}
