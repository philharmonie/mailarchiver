import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Settings, Trash2 } from 'lucide-react';

type Account = {
    id: number;
    name: string;
    host: string;
    username: string;
    folder: string;
    is_active: boolean;
    last_fetch_at: string | null;
    total_emails: number;
    total_size_bytes: number;
    formatted_size: string;
    emails_count: number;
};

type Props = {
    accounts: Account[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'IMAP Accounts', href: '#' },
];

export default function ImapAccountIndex({ accounts }: Props) {
    const handleDelete = (account: Account) => {
        if (confirm(`Are you sure you want to delete "${account.name}"? This will also delete all associated emails.`)) {
            router.delete(`/imap-accounts/${account.id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="IMAP Accounts" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">IMAP Accounts</h1>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400">
                            Manage mailboxes for email archiving
                        </p>
                    </div>
                    <Link href="/imap-accounts/create">
                        <Button>
                            <Plus className="mr-2 size-4" />
                            Add Account
                        </Button>
                    </Link>
                </div>

                {accounts.length === 0 ? (
                    <Card className="p-12">
                        <div className="text-center">
                            <h3 className="text-lg font-medium">No IMAP accounts yet</h3>
                            <p className="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                                Get started by adding your first mailbox to archive emails from.
                            </p>
                            <Link href="/imap-accounts/create">
                                <Button className="mt-4">
                                    <Plus className="mr-2 size-4" />
                                    Add Your First Account
                                </Button>
                            </Link>
                        </div>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {accounts.map((account) => (
                            <Card key={account.id} className="p-6">
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2">
                                            <h3 className="font-semibold">{account.name}</h3>
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
                                        <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                                            {account.host}
                                        </p>
                                        <p className="text-sm text-neutral-500 dark:text-neutral-400">
                                            {account.username}
                                        </p>
                                    </div>
                                </div>

                                <div className="mt-4 space-y-2 border-t border-sidebar-border/70 pt-4 dark:border-sidebar-border">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-neutral-500 dark:text-neutral-400">Total Emails:</span>
                                        <span className="font-medium">{account.total_emails.toLocaleString()}</span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-neutral-500 dark:text-neutral-400">Total Size:</span>
                                        <span className="font-medium">{account.formatted_size}</span>
                                    </div>
                                    {account.last_fetch_at && (
                                        <div className="flex justify-between text-sm">
                                            <span className="text-neutral-500 dark:text-neutral-400">Last Fetch:</span>
                                            <span className="font-medium">
                                                {new Date(account.last_fetch_at).toLocaleString('de-DE', {
                                                    dateStyle: 'short',
                                                    timeStyle: 'short'
                                                })}
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex justify-between text-sm">
                                        <span className="text-neutral-500 dark:text-neutral-400">Folder:</span>
                                        <span className="font-medium">{account.folder}</span>
                                    </div>
                                </div>

                                <div className="mt-4 flex gap-2">
                                    <Link href={`/imap-accounts/${account.id}/edit`} className="flex-1">
                                        <Button variant="outline" className="w-full">
                                            <Settings className="mr-2 size-4" />
                                            Edit
                                        </Button>
                                    </Link>
                                    <Button
                                        variant="outline"
                                        className="text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950"
                                        onClick={() => handleDelete(account)}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
