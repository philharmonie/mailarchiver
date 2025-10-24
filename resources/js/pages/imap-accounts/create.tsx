import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'IMAP Accounts', href: '/imap-accounts' },
    { title: 'Create', href: '#' },
];

export default function CreateImapAccount() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        host: '',
        port: 993,
        encryption: 'ssl',
        validate_cert: true,
        username: '',
        password: '',
        folder: 'INBOX',
        is_active: true,
        delete_after_archive: false,
        sync_interval: null,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/imap-accounts');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create IMAP Account" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Create IMAP Account</h1>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400">
                            Add a new mailbox to archive emails from
                        </p>
                    </div>
                    <Button variant="outline" onClick={() => window.history.back()}>
                        <ArrowLeft className="mr-2 size-4" />
                        Back
                    </Button>
                </div>

                <Card className="p-6">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid gap-6 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="name">Account Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. Main Mailbox"
                                    required
                                />
                                {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="host">IMAP Host</Label>
                                <Input
                                    id="host"
                                    value={data.host}
                                    onChange={(e) => setData('host', e.target.value)}
                                    placeholder="mail.example.com"
                                    required
                                />
                                {errors.host && <p className="text-sm text-red-600">{errors.host}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="port">Port</Label>
                                <Input
                                    id="port"
                                    type="number"
                                    value={data.port}
                                    onChange={(e) => setData('port', parseInt(e.target.value))}
                                    required
                                />
                                {errors.port && <p className="text-sm text-red-600">{errors.port}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="encryption">Encryption</Label>
                                <select
                                    id="encryption"
                                    value={data.encryption}
                                    onChange={(e) => setData('encryption', e.target.value)}
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    required
                                >
                                    <option value="ssl">SSL</option>
                                    <option value="tls">TLS</option>
                                    <option value="none">None</option>
                                </select>
                                {errors.encryption && <p className="text-sm text-red-600">{errors.encryption}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="username">Username</Label>
                                <Input
                                    id="username"
                                    value={data.username}
                                    onChange={(e) => setData('username', e.target.value)}
                                    placeholder="user@example.com"
                                    required
                                />
                                {errors.username && <p className="text-sm text-red-600">{errors.username}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    required
                                />
                                {errors.password && <p className="text-sm text-red-600">{errors.password}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="folder">Folder</Label>
                                <Input
                                    id="folder"
                                    value={data.folder}
                                    onChange={(e) => setData('folder', e.target.value)}
                                    placeholder="INBOX"
                                    required
                                />
                                {errors.folder && <p className="text-sm text-red-600">{errors.folder}</p>}
                            </div>

                            <div className="flex items-center space-x-2">
                                <input
                                    id="validate_cert"
                                    type="checkbox"
                                    checked={data.validate_cert}
                                    onChange={(e) => setData('validate_cert', e.target.checked)}
                                    className="size-4 rounded border-neutral-300"
                                />
                                <Label htmlFor="validate_cert" className="font-normal">
                                    Validate SSL Certificate
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2">
                                <input
                                    id="is_active"
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="size-4 rounded border-neutral-300"
                                />
                                <Label htmlFor="is_active" className="font-normal">
                                    Active
                                </Label>
                            </div>
                        </div>

                        <div className="rounded-lg border p-4">
                            <label htmlFor="delete_after_archive" className="flex cursor-pointer items-start gap-3">
                                <input
                                    id="delete_after_archive"
                                    type="checkbox"
                                    checked={data.delete_after_archive}
                                    onChange={(e) => setData('delete_after_archive', e.target.checked)}
                                    className="mt-0.5 size-4 rounded border-neutral-300"
                                />
                                <div className="flex-1 space-y-1">
                                    <div className="text-base font-bold text-red-600 dark:text-red-500">
                                        Delete emails from server after archival
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        When enabled, emails will be permanently deleted from the IMAP server after successful archival.
                                        This saves server storage space, as archived emails are safely stored in the GoBD-compliant archive.
                                    </p>
                                </div>
                            </label>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="sync_interval">Automatic Sync Interval</Label>
                            <select
                                id="sync_interval"
                                value={data.sync_interval || ''}
                                onChange={(e) => setData('sync_interval', e.target.value || null)}
                                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="">Manual only (no automatic sync)</option>
                                <option value="every_15_minutes">Every 15 minutes</option>
                                <option value="hourly">Every hour</option>
                                <option value="every_6_hours">Every 6 hours</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                            </select>
                            <p className="text-sm text-muted-foreground">
                                Automatically sync emails at the selected interval. Leave as "Manual only" to sync manually via command line.
                            </p>
                            {errors.sync_interval && <p className="text-sm text-red-600">{errors.sync_interval}</p>}
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => window.history.back()}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Creating...' : 'Create Account'}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
