import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Download, FileArchive, Info } from 'lucide-react';
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
    const { data, setData, post, processing, errors } = useForm({
        from: '',
        to: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/export');
    };

    const handleExportAll = () => {
        post('/export');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Export Emails" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">GoBD-konformer Email-Export</h1>
                    <p className="text-muted-foreground">
                        Exportieren Sie Ihre E-Mails gemäß BMF-Schreiben vom 14.11.2014 für Steuerprüfungen
                    </p>
                </div>

                <Alert>
                    <Info className="size-4" />
                    <AlertTitle>Verfügbare E-Mails</AlertTitle>
                    <AlertDescription>
                        Sie haben <strong>{emailCount}</strong> E-Mails zum Export verfügbar.
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
                                    {errors.from && (
                                        <p className="mt-1 text-sm text-destructive">{errors.from}</p>
                                    )}
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
                                    {errors.to && (
                                        <p className="mt-1 text-sm text-destructive">{errors.to}</p>
                                    )}
                                </div>

                                <Button type="submit" disabled={processing} size="lg" className="w-full">
                                    <Download className="mr-2 size-4" />
                                    {processing ? 'Exportiere...' : 'Zeitraum exportieren'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Export-Informationen</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div>
                            <h3 className="font-semibold">Der Export enthält:</h3>
                            <ul className="mt-2 list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                <li><strong>/emails/</strong> - Alle E-Mails als .eml-Dateien</li>
                                <li><strong>index.xml</strong> - Strukturierte Metadaten (XML-Format)</li>
                                <li><strong>index.csv</strong> - Alternative Metadaten (CSV für Excel)</li>
                                <li><strong>hashes.txt</strong> - SHA256-Prüfsummen zur Integritätsprüfung</li>
                                <li><strong>readme.txt</strong> - Dokumentation und Verifikationsanleitung</li>
                            </ul>
                        </div>

                        <div>
                            <h3 className="font-semibold">GoBD-Anforderungen:</h3>
                            <ul className="mt-2 list-inside space-y-1 text-sm text-muted-foreground">
                                <li>✓ Vollständigkeit - Alle E-Mails des Zeitraums</li>
                                <li>✓ Unveränderbarkeit - SHA256-Prüfsummen zur Verifikation</li>
                                <li>✓ Nachvollziehbarkeit - Zeitliche Sortierung, vollständige Metadaten</li>
                                <li>✓ Maschinelle Auswertung - XML/CSV-Index, Standard .eml-Format</li>
                                <li>✓ Lesbarkeit - .eml-Dateien mit jedem E-Mail-Client lesbar</li>
                            </ul>
                        </div>

                        <div>
                            <h3 className="font-semibold">Rechtliche Grundlagen:</h3>
                            <ul className="mt-2 list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                <li>BMF-Schreiben vom 14.11.2014 (GoBD)</li>
                                <li>§ 147 AO (Aufbewahrungspflichten)</li>
                                <li>§ 257 HGB (Aufbewahrungspflichten)</li>
                            </ul>
                        </div>

                        <Alert>
                            <Info className="size-4" />
                            <AlertTitle>Hinweis</AlertTitle>
                            <AlertDescription>
                                E-Mails mit steuerlicher Relevanz müssen 6-10 Jahre aufbewahrt werden.
                                Dieser Export erfüllt alle Anforderungen für Betriebsprüfungen durch das Finanzamt.
                            </AlertDescription>
                        </Alert>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
