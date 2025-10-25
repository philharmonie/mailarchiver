import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Download, FileArchive } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useState } from 'react';

type Props = {
    emailCount: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Emails',
        href: '/emails',
    },
    {
        title: 'Export',
        href: '/export',
    },
];

export default function EmailExport({ emailCount }: Props) {
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [isExporting, setIsExporting] = useState(false);

    // Get CSRF token from cookie
    const getCsrfToken = () => {
        const name = 'XSRF-TOKEN=';
        const decodedCookie = decodeURIComponent(document.cookie);
        const cookies = decodedCookie.split(';');
        for (let cookie of cookies) {
            cookie = cookie.trim();
            if (cookie.indexOf(name) === 0) {
                return decodeURIComponent(cookie.substring(name.length));
            }
        }
        return '';
    };

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        setIsExporting(true);
        // Form will submit normally, reset state after a delay
        setTimeout(() => setIsExporting(false), 2000);
    };

    const handleExportAll = () => {
        setIsExporting(true);
        const form = document.getElementById('export-all-form') as HTMLFormElement;
        form.submit();
        setTimeout(() => setIsExporting(false), 2000);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Export Emails" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">GoBD-konformer Email-Export</h1>
                    <p className="text-muted-foreground">
                        Exportieren Sie Ihre E-Mails gemäß BMF-Schreiben vom 14.11.2014
                    </p>
                </div>

                <Alert>
                    <FileArchive className="size-4" />
                    <AlertTitle>Verfügbare E-Mails</AlertTitle>
                    <AlertDescription>
                        Sie haben <strong className="whitespace-nowrap">{emailCount} E-Mails</strong> zum Export verfügbar.
                    </AlertDescription>
                </Alert>

                {/* Hidden form for "Export All" */}
                <form id="export-all-form" method="POST" action="/export" style={{ display: 'none' }}>
                    <input type="hidden" name="_token" value={getCsrfToken()} />
                </form>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileArchive className="size-5" />
                                Alle Emails exportieren
                            </CardTitle>
                            <CardDescription>
                                Exportiert alle Ihre archivierten E-Mails
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button
                                onClick={handleExportAll}
                                disabled={isExporting || emailCount === 0}
                                size="lg"
                                className="w-full"
                            >
                                <Download className="mr-2 size-4" />
                                {isExporting ? 'Exportiere...' : 'Alle Emails exportieren'}
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileArchive className="size-5" />
                                Zeitraum exportieren
                            </CardTitle>
                            <CardDescription>
                                Exportiert E-Mails in einem bestimmten Zeitraum
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form method="POST" action="/export" onSubmit={handleSubmit} className="space-y-4">
                                <input type="hidden" name="_token" value={getCsrfToken()} />

                                <div>
                                    <Label htmlFor="from">Von (Datum)</Label>
                                    <Input
                                        id="from"
                                        name="from"
                                        type="date"
                                        value={from}
                                        onChange={(e) => setFrom(e.target.value)}
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="to">Bis (Datum)</Label>
                                    <Input
                                        id="to"
                                        name="to"
                                        type="date"
                                        value={to}
                                        onChange={(e) => setTo(e.target.value)}
                                        className="mt-1"
                                    />
                                </div>

                                <Button type="submit" disabled={isExporting} size="lg" className="w-full">
                                    <Download className="mr-2 size-4" />
                                    {isExporting ? 'Exportiere...' : 'Zeitraum exportieren'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
