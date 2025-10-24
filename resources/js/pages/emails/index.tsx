import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FileText, Search } from 'lucide-react';
import { useState } from 'react';

type Email = {
    id: number;
    message_id: string;
    from_address: string | null;
    from_name: string | null;
    subject: string;
    received_at: string;
    has_attachments: boolean;
    attachments: Array<{
        id: number;
        filename: string;
        size_bytes: number;
    }>;
};

type Props = {
    emails: {
        data: Email[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        current_page: number;
        last_page: number;
    };
    filters: {
        search?: string;
        from?: string;
        date_from?: string;
        date_to?: string;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Emails',
        href: '/emails',
    },
];

export default function EmailIndex({ emails, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [from, setFrom] = useState(filters.from ?? '');

    const handleSearch = () => {
        router.get(
            '/emails',
            { search, from },
            { preserveState: true, preserveScroll: true }
        );
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString('de-DE', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const formatBytes = (bytes: number) => {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Email Archive" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-neutral-950">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-semibold">Email Archive</h1>
                            <p className="text-sm text-neutral-500 dark:text-neutral-400">
                                GoBD-compliant email archiving system
                            </p>
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-neutral-500" />
                            <Input
                                type="text"
                                placeholder="Search emails..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                                className="pl-10"
                            />
                        </div>
                        <Input
                            type="text"
                            placeholder="From address..."
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                            className="w-64"
                        />
                        <Button onClick={handleSearch}>Search</Button>
                    </div>

                    <div className="rounded-md border border-sidebar-border/70 dark:border-sidebar-border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[200px]">From</TableHead>
                                    <TableHead>Subject</TableHead>
                                    <TableHead className="w-[150px]">Received</TableHead>
                                    <TableHead className="w-[100px]">Attachments</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {emails.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={4} className="h-24 text-center">
                                            No emails found.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    emails.data.map((email) => (
                                        <TableRow key={email.id} className="cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-900">
                                            <TableCell>
                                                <Link href={`/emails/${email.id}`} className="block">
                                                    <div className="font-medium">
                                                        {email.from_name || email.from_address || 'Unknown'}
                                                    </div>
                                                    {email.from_name && email.from_address && (
                                                        <div className="text-xs text-neutral-500">{email.from_address}</div>
                                                    )}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <Link href={`/emails/${email.id}`} className="block">
                                                    <div className="truncate">{email.subject}</div>
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <Link href={`/emails/${email.id}`} className="block">
                                                    <div className="text-sm">{formatDate(email.received_at)}</div>
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <Link href={`/emails/${email.id}`} className="block">
                                                    {email.has_attachments && email.attachments.length > 0 ? (
                                                        <div className="flex items-center gap-1 text-sm">
                                                            <FileText className="size-4" />
                                                            <span>{email.attachments.length}</span>
                                                            <span className="text-neutral-500">
                                                                ({formatBytes(email.attachments.reduce((sum, att) => sum + att.size_bytes, 0))})
                                                            </span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-neutral-400">-</span>
                                                    )}
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    {emails.last_page > 1 && (
                        <div className="flex items-center justify-between">
                            <div className="text-sm text-neutral-500">
                                Page {emails.current_page} of {emails.last_page}
                            </div>
                            <div className="flex gap-2">
                                {emails.links.map((link, index) => {
                                    if (!link.url) {
                                        return (
                                            <Button key={index} disabled variant="outline" size="sm">
                                                <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                            </Button>
                                        );
                                    }

                                    return (
                                        <Link key={index} href={link.url}>
                                            <Button variant={link.active ? 'default' : 'outline'} size="sm">
                                                <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                            </Button>
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
