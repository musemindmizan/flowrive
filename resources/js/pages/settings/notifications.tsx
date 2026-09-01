import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Field, Select } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/notification-preferences';
import { update } from '@/routes/notification-preferences';

type Preference = {
    event: string;
    label: string;
    inApp: boolean;
    email: boolean;
    push: boolean;
};

type Props = {
    preferences: Preference[];
    digest: {
        frequency: string;
        quietFrom: string | null;
        quietTo: string | null;
    };
};

const channels = [
    { key: 'inApp', label: 'In-app' },
    { key: 'email', label: 'Email' },
    { key: 'push', label: 'Push' },
] as const;

export default function NotificationSettings({ preferences, digest }: Props) {
    const form = useForm({
        preferences,
        digest: {
            frequency: digest.frequency,
            quietFrom: digest.quietFrom ?? '',
            quietTo: digest.quietTo ?? '',
        },
    });

    function toggle(index: number, channel: (typeof channels)[number]['key']) {
        form.setData(
            'preferences',
            form.data.preferences.map((preference, i) =>
                i === index
                    ? { ...preference, [channel]: !preference[channel] }
                    : preference,
            ),
        );
    }

    return (
        <>
            <Head title="Notification settings" />

            <h1 className="sr-only">Notification settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Notification settings"
                    description="Choose what reaches you, and where."
                />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(update().url, { preserveScroll: true });
                    }}
                    className="space-y-6"
                >
                    <div className="border-border overflow-x-auto rounded-xl border">
                        <table className="w-full text-[13px]">
                            <thead>
                                <tr className="bg-secondary text-muted-foreground border-border border-b text-[11px] tracking-wide uppercase">
                                    <th className="px-4 py-2.5 text-left font-semibold">
                                        Event
                                    </th>
                                    {channels.map((channel) => (
                                        <th
                                            key={channel.key}
                                            className="w-24 px-4 py-2.5 text-center font-semibold"
                                        >
                                            {channel.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {form.data.preferences.map(
                                    (preference, index) => (
                                        <tr
                                            key={preference.event}
                                            className="border-border border-b last:border-b-0"
                                        >
                                            <td className="text-foreground px-4 py-3 font-medium">
                                                {preference.label}
                                            </td>
                                            {channels.map((channel) => (
                                                <td
                                                    key={channel.key}
                                                    className="px-4 py-3 text-center"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        className="accent-brand-600 size-4"
                                                        checked={
                                                            preference[
                                                                channel.key
                                                            ]
                                                        }
                                                        onChange={() =>
                                                            toggle(
                                                                index,
                                                                channel.key,
                                                            )
                                                        }
                                                        aria-label={`${channel.label} notifications for ${preference.label}`}
                                                    />
                                                </td>
                                            ))}
                                        </tr>
                                    ),
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field
                            label="Email digest"
                            htmlFor="digest"
                            hint="A summary of what you missed, instead of individual emails."
                        >
                            <Select
                                id="digest"
                                value={form.data.digest.frequency}
                                onChange={(e) =>
                                    form.setData('digest', {
                                        ...form.data.digest,
                                        frequency: e.target.value,
                                    })
                                }
                            >
                                <option value="off">Off</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                            </Select>
                        </Field>

                        <div>
                            <Label className="mb-1.5 text-[13px]">
                                Quiet hours
                            </Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    type="time"
                                    value={form.data.digest.quietFrom}
                                    onChange={(e) =>
                                        form.setData('digest', {
                                            ...form.data.digest,
                                            quietFrom: e.target.value,
                                        })
                                    }
                                    aria-label="Quiet hours start"
                                />
                                <span className="text-muted-foreground text-xs">
                                    to
                                </span>
                                <Input
                                    type="time"
                                    value={form.data.digest.quietTo}
                                    onChange={(e) =>
                                        form.setData('digest', {
                                            ...form.data.digest,
                                            quietTo: e.target.value,
                                        })
                                    }
                                    aria-label="Quiet hours end"
                                />
                            </div>
                            <p className="text-muted-foreground mt-1.5 text-xs">
                                Push notifications pause between these times.
                            </p>
                        </div>
                    </div>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Saving…' : 'Save preferences'}
                    </Button>
                </form>
            </div>
        </>
    );
}

NotificationSettings.layout = {
    breadcrumbs: [{ title: 'Notification settings', href: edit() }],
};
