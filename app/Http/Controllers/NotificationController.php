<?php

namespace App\Http\Controllers;

use App\Enums\NotificationCategory;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The notification centre.
 *
 * Reads go through the (notifiable_id, category, read_at) index the schema
 * declares, and the list is trimmed to the 30 days the UI promises.
 */
class NotificationController extends Controller
{
    private const RETENTION_DAYS = 30;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string'],
            'unread' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $category = $filters['category'] ?? null;
        $unreadOnly = (bool) ($filters['unread'] ?? false);

        $notifications = $this->base($user)
            ->when($category, fn ($query, string $value) => $query->where('category', $value))
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return Inertia::render('notifications/index', [
            'notifications' => $notifications
                ->map(function (object $notification): array {
                    /** @var array<string, mixed> $data */
                    $data = json_decode((string) $notification->data, true) ?: [];

                    return [
                        'id' => $notification->id,
                        'category' => $notification->category,
                        'title' => $data['title'] ?? 'Notification',
                        'body' => $data['body'] ?? null,
                        'href' => $data['href'] ?? null,
                        'actor' => $data['actor'] ?? null,
                        'isRead' => $notification->read_at !== null,
                        'at' => Carbon::parse($notification->created_at)->diffForHumans(),
                    ];
                })
                ->all(),
            'filters' => [
                'category' => $category,
                'unread' => $unreadOnly,
            ],
            'counts' => $this->counts($user),
            'categories' => collect(NotificationCategory::cases())
                ->map(fn (NotificationCategory $item) => [
                    'value' => $item->value,
                    'label' => $item->label(),
                    'icon' => $item->icon(),
                ])
                ->all(),
        ]);
    }

    public function update(Request $request, string $notification): RedirectResponse
    {
        $data = $request->validate(['is_read' => ['required', 'boolean']]);

        $this->base($request->user())
            ->where('id', $notification)
            ->update(['read_at' => $data['is_read'] ? now() : null]);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->base($request->user())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $this->base($request->user())->where('id', $notification)->delete();

        return back();
    }

    public function clear(Request $request): RedirectResponse
    {
        $this->base($request->user())->delete();

        return back()->with('success', 'All notifications cleared.');
    }

    /**
     * Every query is scoped to the signed-in user and the retention window,
     * so a stale id from another account can never be touched.
     */
    private function base(User $user): Builder
    {
        return DB::table('notifications')
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id)
            ->where('created_at', '>=', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * @return array<string, int>
     */
    private function counts(User $user): array
    {
        $byCategory = $this->base($user)
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        return [
            'all' => (int) $byCategory->sum(),
            'unread' => $this->base($user)->whereNull('read_at')->count(),
            ...collect(NotificationCategory::cases())
                ->mapWithKeys(fn (NotificationCategory $item) => [
                    $item->value => (int) $byCategory->get($item->value, 0),
                ])
                ->all(),
        ];
    }
}
