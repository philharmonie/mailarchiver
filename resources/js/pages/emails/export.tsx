import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Download, FileArchive } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

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
    const { data, setData, post, processing } = useForm({
        from: '',
        to: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('export.gobd'));
    };

    const handleExportAll = () => {
        post(route('export.gobd'));
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
                                disabled={processing || emailCount === 0}
                                size="lg"
                                className="w-full"
                            >
                                <Download className="mr-2 size-4" />
                                {processing ? 'Exportiere...' : 'Alle Emails exportieren'}
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
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <Label htmlFor="from">Von (Datum)</Label>
                                    <Input
                                        id="from"
                                        type="date"
                                        value={data.from}
                                        onChange={(e) => setData('from', e.target.value)}
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="to">Bis (Datum)</Label>
                                    <Input
                                        id="to"
                                        type="date"
                                        value={data.to}
                                        onChange={(e) => setData('to', e.target.value)}
                                        className="mt-1"
                                    />
                                </div>

                                <Button type="submit" disabled={processing} size="lg" className="w-full">
                                    <Download className="mr-2 size-4" />
                                    {processing ? 'Exportiere...' : 'Zeitraum exportieren'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
