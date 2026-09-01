<x-mail::message>
# You've been invited to {{ $workspace }}

**{{ $inviter }}** has invited you to join **{{ $workspace }}** on FlowRive as
a **{{ $role }}**.

@if ($note)
<x-mail::panel>
{{ $note }}
</x-mail::panel>
@endif

<x-mail::button :url="$url">
Accept the invitation
</x-mail::button>

This invitation expires on **{{ $expires }}**. If you weren't expecting it,
you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
