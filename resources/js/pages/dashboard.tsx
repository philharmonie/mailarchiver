import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Database, HardDrive, Mail, Server } from 'lucide-react';

type Account = {
    id: number;
    name: string;
    total_emails: number;
    total_size_bytes: number;
    formatted_size: string;
    last_fetch_at: string | null;
    is_active: boolean;
};

type Stats = {
    total_emails: number;
    total_size_bytes: number;
    total_accounts: number;
    active_accounts: number;
};

type Props = {
    accounts: Account[];
    stats: Stats;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const formatBytes = (bytes: number) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
    if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
};

export default function Dashboard({ accounts, stats }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Email Archive Dashboard</h1>
                    <p className="text-sm text-neutral-500 dark:text-neutral-400">
                        Overview of your archived emails and IMAP accounts
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-4">
                    <Card className="p-6">
                        <div className="flex items-center gap-4">
                            <div className="rounded-lg bg-blue-100 p-3 dark:bg-blue-900">
                                <Mail className="size-6 text-blue-600 dark:text-blue-300" />
                            </div>
                            <div>
                                <p className="text-sm text-neutral-500 dark:text-neutral-400">Total Emails</p>
                                <p className="text-2xl font-semibold">{stats.total_emails.toLocaleString()}</p>
                            </div>
                        </div>
                    </Card>

                    <Card className="p-6">
                        <div className="flex items-center gap-4">
                            <div className="rounded-lg bg-green-100 p-3 dark:bg-green-900">
                                <HardDrive className="size-6 text-green-600 dark:text-green-300" />
                            </div>
                            <div>
                                <p className="text-sm text-neutral-500 dark:text-neutral-400">Total Size</p>
                                <p className="text-2xl font-semibold">{formatBytes(stats.total_size_bytes)}</p>
                            </div>
                        </div>
                    </Card>

                    <Card className="p-6">
                        <div className="flex items-center gap-4">
                            <div className="rounded-lg bg-purple-100 p-3 dark:bg-purple-900">
                                <Server className="size-6 text-purple-600 dark:text-purple-300" />
                            </div>
                            <div>
                                <p className="text-sm text-neutral-500 dark:text-neutral-400">Total Accounts</p>
                                <p className="text-2xl font-semibold">{stats.total_accounts}</p>
                            </div>
                        </div>
                    </Card>

                    <Card className="p-6">
                        <div className="flex items-center gap-4">
                            <div className="rounded-lg bg-orange-100 p-3 dark:bg-orange-900">
                                <Database className="size-6 text-orange-600 dark:text-orange-300" />
                            </div>
                            <div>
                                <p className="text-sm text-neutral-500 dark:text-neutral-400">Active Accounts</p>
                                <p className="text-2xl font-semibold">{stats.active_accounts}</p>
                            </div>
                        </div>
                    </Card>
                </div>

                <Card className="p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-semibold">IMAP Accounts</h2>
                        <Link
                            href="/imap-accounts/create"
                            className="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground hover:bg-primary/90"
                        >
                            Add Account
                        </Link>
                    </div>

                    {accounts.length === 0 ? (
                        <p className="text-center text-sm text-neutral-500 dark:text-neutral-400">
                            No IMAP accounts configured yet.
                        </p>
                    ) : (
                        <div className="space-y-4">
                            {accounts.map((account) => (
                                <div
                                    key={account.id}
                                    className="flex items-center justify-between rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                                >
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2">
                                            <h3 className="font-medium">{account.name}</h3>
                                            {account.is_active ? (
                                                <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700 dark:bg-green-900 dark:text-green-300">
                                                    Active
                                                </span>
                                            ) : (
                                                <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                                    Inactive
                                                </span>
                                            )}
                                        </div>
                                        <div className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                                            {account.total_emails.toLocaleString()} emails • {account.formatted_size}
                                            {account.last_fetch_at && (
                                                <span className="ml-2">
                                                    • Last fetch: {new Date(account.last_fetch_at).toLocaleString('de-DE')}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <Link
                                        href={`/imap-accounts/${account.id}/edit`}
                                        className="text-sm text-primary hover:underline"
                                    >
                                        Manage
                                    </Link>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
