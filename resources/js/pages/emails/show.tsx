import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, FileText, Shield } from 'lucide-react';

type Email = {
    id: number;
    message_id: string;
    from_address: string | null;
    from_name: string | null;
    to_addresses: string[] | null;
    cc_addresses: string[] | null;
    subject: string;
    body_text: string | null;
    body_html: string | null;
    received_at: string;
    size_bytes: number;
    hash: string;
    is_verified: boolean;
    is_compressed: boolean;
    has_attachments: boolean;
    attachments: Array<{
        id: number;
        filename: string;
        mime_type: string;
        size_bytes: number;
        is_compressed: boolean;
        hash: string;
    }>;
    audit_logs: Array<{
        id: number;
        action: string;
        created_at: string;
        user: {
            name: string;
            email: string;
        } | null;
    }>;
};

type Props = {
    email: Email;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Emails',
        href: '/emails',
    },
    {
        title: 'Email Details',
        href: '#',
    },
];

export default function EmailShow({ email }: Props) {
    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString('de-DE', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    };

    const formatBytes = (bytes: number) => {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Email: ${email.subject}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <Link href="/emails">
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="mr-2 size-4" />
                            Back to List
                        </Button>
                    </Link>
                    <div className="flex gap-2">
                        <a href={`/emails/${email.id}/download`}>
                            <Button variant="outline" size="sm">
                                <Download className="mr-2 size-4" />
                                Download .eml
                            </Button>
                        </a>
                        {email.is_verified && (
                            <Badge variant="outline" className="gap-1">
                                <Shield className="size-3" />
                                GoBD Verified
                            </Badge>
                        )}
                        {email.is_compressed && (
                            <Badge variant="secondary">Compressed</Badge>
                        )}
                    </div>
                </div>

                <Card className="p-6">
                    <div className="space-y-4">
                        <div>
                            <h1 className="text-2xl font-semibold">{email.subject}</h1>
                        </div>

                        <Separator />

                        <div className="grid gap-3 text-sm">
                            {email.from_address && (
                                <div className="grid grid-cols-[100px_1fr] gap-2">
                                    <span className="font-medium text-neutral-500">From:</span>
                                    <span>
                                        {email.from_name ? `${email.from_name} <${email.from_address}>` : email.from_address}
                                    </span>
                                </div>
                            )}

                            {email.to_addresses && email.to_addresses.length > 0 && (
                                <div className="grid grid-cols-[100px_1fr] gap-2">
                                    <span className="font-medium text-neutral-500">To:</span>
                                    <span>{email.to_addresses.join(', ')}</span>
                                </div>
                            )}

                            {email.cc_addresses && email.cc_addresses.length > 0 && (
                                <div className="grid grid-cols-[100px_1fr] gap-2">
                                    <span className="font-medium text-neutral-500">CC:</span>
                                    <span>{email.cc_addresses.join(', ')}</span>
                                </div>
                            )}

                            <div className="grid grid-cols-[100px_1fr] gap-2">
                                <span className="font-medium text-neutral-500">Date:</span>
                                <span>{formatDate(email.received_at)}</span>
                            </div>

                            <div className="grid grid-cols-[100px_1fr] gap-2">
                                <span className="font-medium text-neutral-500">Size:</span>
                                <span>{formatBytes(email.size_bytes)}</span>
                            </div>

                            <div className="grid grid-cols-[100px_1fr] gap-2">
                                <span className="font-medium text-neutral-500">Message ID:</span>
                                <span className="font-mono text-xs">{email.message_id}</span>
                            </div>

                            <div className="grid grid-cols-[100px_1fr] gap-2">
                                <span className="font-medium text-neutral-500">Hash:</span>
                                <span className="font-mono text-xs">{email.hash}</span>
                            </div>
                        </div>

                        {email.has_attachments && email.attachments.length > 0 && (
                            <>
                                <Separator />
                                <div>
                                    <h3 className="mb-3 flex items-center gap-2 font-medium">
                                        <FileText className="size-4" />
                                        Attachments ({email.attachments.length})
                                    </h3>
                                    <div className="space-y-2">
                                        {email.attachments.map((attachment) => (
                                            <div
                                                key={attachment.id}
                                                className="flex items-center justify-between rounded-md border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                                            >
                                                <div className="flex items-center gap-3">
                                                    <FileText className="size-5 text-neutral-500" />
                                                    <div>
                                                        <div className="font-medium">{attachment.filename}</div>
                                                        <div className="text-xs text-neutral-500">
                                                            {attachment.mime_type} • {formatBytes(attachment.size_bytes)}
                                                            {attachment.is_compressed && ' • Compressed'}
                                                        </div>
                                                    </div>
                                                </div>
                                                <Button variant="outline" size="sm">
                                                    <Download className="mr-2 size-4" />
                                                    Download
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </>
                        )}

                        <Separator />

                        <div>
                            <h3 className="mb-3 font-medium">Email Content</h3>
                            <div className="overflow-hidden rounded-lg border border-sidebar-border/70 bg-white dark:border-sidebar-border dark:bg-neutral-900">
                                {email.body_html ? (
                                    <div
                                        className="prose prose-sm max-w-none p-6 text-neutral-900 dark:text-neutral-100 dark:prose-invert prose-headings:font-semibold prose-a:text-blue-600 hover:prose-a:text-blue-700 dark:prose-a:text-blue-400 dark:hover:prose-a:text-blue-300 prose-img:rounded-lg prose-pre:bg-neutral-100 dark:prose-pre:bg-neutral-800 [&_*]:!text-inherit"
                                        dangerouslySetInnerHTML={{ __html: email.body_html }}
                                    />
                                ) : email.body_text ? (
                                    <pre className="whitespace-pre-wrap p-6 text-sm leading-relaxed text-neutral-900 dark:text-neutral-100">
                                        {email.body_text}
                                    </pre>
                                ) : (
                                    <div className="p-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                        No email content available
                                    </div>
                                )}
                            </div>
                        </div>

                        {email.audit_logs && email.audit_logs.length > 0 && (
                            <>
                                <Separator />
                                <div>
                                    <h3 className="mb-3 font-medium">Audit Trail</h3>
                                    <div className="space-y-2 text-sm">
                                        {email.audit_logs.slice(0, 5).map((log) => (
                                            <div key={log.id} className="flex items-center justify-between text-neutral-600 dark:text-neutral-400">
                                                <span>
                                                    {log.action} by {log.user?.name || 'System'}
                                                </span>
                                                <span className="text-xs">{formatDate(log.created_at)}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </>
                        )}
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
