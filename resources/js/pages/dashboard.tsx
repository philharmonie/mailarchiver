import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Calendar, Clock, Database, HardDrive, Mail, Paperclip, Server } from 'lucide-react';

type RecentEmail = {
    id: number;
    from_address: string | null;
    from_name: string | null;
    subject: string;
    received_at: string;
    has_attachments: boolean;
};

type AdminStats = {
    total_emails: number;
    total_size_bytes: number;
    total_accounts: number;
    active_accounts: number;
};

type AccountStat = {
    name: string;
    username: string;
    is_active: boolean;
    emails_count: number;
    total_size: number;
};

type UserStats = {
    total_emails: number;
    total_size_bytes: number;
    emails_this_month: number;
    last_archive_at: string | null;
};

type Props = {
    stats: AdminStats | UserStats;
    recent_emails?: RecentEmail[];
    account_stats?: AccountStat[];
    is_admin: boolean;
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

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

export default function Dashboard({ stats, recent_emails = [], account_stats = [], is_admin }: Props) {
    // Admin Dashboard
    if (is_admin) {
        const adminStats = stats as AdminStats;
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Dashboard" />
                <div className="flex h-full flex-1 flex-col gap-4 p-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Email Archive Dashboard</h1>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400">
                            Overview of all archived emails
                        </p>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <Card className="p-6">
                            <div className="flex items-center gap-4">
                                <div className="flex-shrink-0 rounded-lg bg-blue-100 p-3 dark:bg-blue-900">
                                    <Mail className="size-6 text-blue-600 dark:text-blue-300" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm text-neutral-500 dark:text-neutral-400 whitespace-nowrap">Total Emails</p>
                                    <p className="text-2xl font-semibold">{adminStats.total_emails.toLocaleString()}</p>
                                </div>
                            </div>
                        </Card>

                        <Card className="p-6">
                            <div className="flex items-center gap-4">
                                <div className="flex-shrink-0 rounded-lg bg-green-100 p-3 dark:bg-green-900">
                                    <HardDrive className="size-6 text-green-600 dark:text-green-300" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm text-neutral-500 dark:text-neutral-400 whitespace-nowrap">Total Size</p>
                                    <p className="text-2xl font-semibold truncate">{formatBytes(adminStats.total_size_bytes)}</p>
                                </div>
                            </div>
                        </Card>

                        <Card className="p-6">
                            <div className="flex items-center gap-4">
                                <div className="flex-shrink-0 rounded-lg bg-purple-100 p-3 dark:bg-purple-900">
                                    <Server className="size-6 text-purple-600 dark:text-purple-300" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm text-neutral-500 dark:text-neutral-400 whitespace-nowrap">Total Accounts</p>
                                    <p className="text-2xl font-semibold">{adminStats.total_accounts}</p>
                                </div>
                            </div>
                        </Card>

                        <Card className="p-6">
                            <div className="flex items-center gap-4">
                                <div className="flex-shrink-0 rounded-lg bg-orange-100 p-3 dark:bg-orange-900">
                                    <Database className="size-6 text-orange-600 dark:text-orange-300" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm text-neutral-500 dark:text-neutral-400 whitespace-nowrap">Active Accounts</p>
                                    <p className="text-2xl font-semibold">{adminStats.active_accounts}</p>
                                </div>
                            </div>
                        </Card>
                    </div>

                    {account_stats.length > 0 && (
                        <Card className="p-6">
                            <div className="mb-4">
                                <h2 className="text-lg font-semibold">Top Accounts by Email Count</h2>
                                <p className="text-sm text-neutral-500 dark:text-neutral-400">
                                    Top 10 IMAP accounts with the most archived emails
                                </p>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                        <tr className="text-left">
                                            <th className="pb-3 font-medium text-neutral-500 dark:text-neutral-400">Account</th>
                                            <th className="pb-3 font-medium text-neutral-500 dark:text-neutral-400">Username</th>
                                            <th className="pb-3 text-right font-medium text-neutral-500 dark:text-neutral-400">Emails</th>
                                            <th className="pb-3 text-right font-medium text-neutral-500 dark:text-neutral-400">Size</th>
                                            <th className="pb-3 text-center font-medium text-neutral-500 dark:text-neutral-400">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                        {account_stats.map((account, index) => (
                                            <tr key={index} className="hover:bg-neutral-50 dark:hover:bg-neutral-900/50">
                                                <td className="py-3 font-medium">{account.name}</td>
                                                <td className="py-3 text-neutral-600 dark:text-neutral-400">{account.username}</td>
                                                <td className="py-3 text-right font-medium">{account.emails_count.toLocaleString()}</td>
                                                <td className="py-3 text-right text-neutral-600 dark:text-neutral-400">{formatBytes(account.total_size)}</td>
                                                <td className="py-3 text-center">
                                                    <span className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${
                                                        account.is_active
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                            : 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300'
                                                    }`}>
                                                        {account.is_active ? 'Active' : 'Inactive'}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    )}
                </div>
            </AppLayout>
        );
    }

    // Regular User Dashboard
    const userStats = stats as UserStats;
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Email Archive</h1>
                    <p className="text-sm text-neutral-500 dark:text-neutral-400">
                        Overview of your archived emails
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card className="p-6">
                        <div className="flex items-center gap-4">
                            <div className="flex-shrink-0 rounded-lg bg-blue-100 p-3 dark:bg-blue-900">
                                <Mail className="size-6 text-blue-600 dark:text-blue-300" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm text-neutral-500 dark:text-neutral-400 whitespace-nowrap">Total Emails</p>
                                <p className="text-2xl font-semibold">{userStats.total_emails.toLocaleString()}</p>
                            </div>
                        </div>
                    </Card>

                    <Card className="p-6">
                        <div className="flex items-center gap-4">
                            <div className="flex-shrink-0 rounded-lg bg-green-100 p-3 dark:bg-green-900">
                                <HardDrive className="size-6 text-green-600 dark:text-green-300" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm text-neutral-500 dark:text-neutral-400 whitespace-nowrap">Total Size</p>
                                <p className="text-2xl font-semibold truncate">{formatBytes(userStats.total_size_bytes)}</p>
                            </div>
                        </div>
                    </Card>

                    <Card className="p-6">
                        <div className="flex items-center gap-4">
                            <div className="flex-shrink-0 rounded-lg bg-purple-100 p-3 dark:bg-purple-900">
                                <Calendar className="size-6 text-purple-600 dark:text-purple-300" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm text-neutral-500 dark:text-neutral-400 whitespace-nowrap">This Month</p>
                                <p className="text-2xl font-semibold">{userStats.emails_this_month.toLocaleString()}</p>
                            </div>
                        </div>
                    </Card>

                    <Card className="p-6">
                        <div className="flex items-center gap-4">
                            <div className="flex-shrink-0 rounded-lg bg-orange-100 p-3 dark:bg-orange-900">
                                <Clock className="size-6 text-orange-600 dark:text-orange-300" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm text-neutral-500 dark:text-neutral-400 whitespace-nowrap">Last Archive</p>
                                <p className="text-lg font-semibold">
                                    {userStats.last_archive_at
                                        ? new Date(userStats.last_archive_at).toLocaleDateString('de-DE', {
                                            day: '2-digit',
                                            month: '2-digit',
                                        })
                                        : 'Never'}
                                </p>
                            </div>
                        </div>
                    </Card>
                </div>

                {recent_emails.length > 0 && (
                    <Card className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-lg font-semibold">Recent Emails</h2>
                            <Link
                                href="/emails"
                                className="text-sm text-primary hover:underline"
                            >
                                View All
                            </Link>
                        </div>

                        <div className="space-y-3">
                            {recent_emails.map((email) => (
                                <Link
                                    key={email.id}
                                    href={`/emails/${email.id}`}
                                    className="block rounded-lg border border-sidebar-border/70 p-4 transition-colors hover:bg-neutral-50 dark:border-sidebar-border dark:hover:bg-neutral-900/50"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <p className="font-medium text-neutral-900 dark:text-neutral-100">
                                                    {email.from_name || email.from_address || 'Unknown'}
                                                </p>
                                                {email.has_attachments && (
                                                    <Paperclip className="size-4 text-neutral-400" />
                                                )}
                                            </div>
                                            <p className="mt-1 truncate text-sm text-neutral-600 dark:text-neutral-400">
                                                {email.subject || '(No Subject)'}
                                            </p>
                                        </div>
                                        <div className="flex-shrink-0 text-right">
                                            <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                                {formatDate(email.received_at)}
                                            </p>
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </Card>
                )}

                {recent_emails.length === 0 && (
                    <Card className="p-12">
                        <div className="text-center">
                            <Mail className="mx-auto size-12 text-neutral-400" />
                            <h3 className="mt-4 text-lg font-semibold">No emails archived yet</h3>
                            <p className="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                                Your emails will appear here once they are archived.
                            </p>
                        </div>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
