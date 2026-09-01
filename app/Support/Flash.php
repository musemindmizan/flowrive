<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;

/**
 * The React side listens for a flash key named `toast` shaped
 * {type, message} — see resources/js/hooks/use-flash-toast.ts. Anything
 * flashed under another key is silently dropped, so redirects go through
 * here rather than hand-rolling ->with('success', '…').
 */
final class Flash
{
    public static function success(RedirectResponse $response, string $message): RedirectResponse
    {
        return self::toast($response, 'success', $message);
    }

    public static function info(RedirectResponse $response, string $message): RedirectResponse
    {
        return self::toast($response, 'info', $message);
    }

    public static function error(RedirectResponse $response, string $message): RedirectResponse
    {
        return self::toast($response, 'error', $message);
    }

    private static function toast(
        RedirectResponse $response,
        string $type,
        string $message,
    ): RedirectResponse {
        return $response->with('toast', ['type' => $type, 'message' => $message]);
    }
}
