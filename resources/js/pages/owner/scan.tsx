import { Form, Head, Link } from '@inertiajs/react';
import { Search, Users } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { scan } from '@/routes/owner';

type Result = {
    token: string;
    full_name: string;
    phone: string;
    quantity: number;
    status: string;
    event_title_en: string;
};

type Props = { phone: string; results: Result[] };

export default function ScanPage({ phone, results }: Props) {
    return (
        <>
            <Head title="Verify tickets" />

            <div className="mx-auto w-full max-w-xl space-y-6 p-4">
                <Heading
                    variant="small"
                    title="Verify tickets"
                    description="Scan an attendee's QR code, or look them up by mobile number."
                />

                <Form
                    action={scan().url}
                    method="get"
                    className="flex items-end gap-2"
                >
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="phone">Mobile number</Label>
                        <Input
                            id="phone"
                            name="phone"
                            type="tel"
                            dir="ltr"
                            defaultValue={phone}
                            placeholder="09XXXXXXXX"
                        />
                    </div>
                    <Button type="submit">
                        <Search />
                        Search
                    </Button>
                </Form>

                {phone && results.length === 0 && (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        No tickets found for that number in your events.
                    </p>
                )}

                <ul className="space-y-3">
                    {results.map((result) => (
                        <li key={result.token}>
                            <Link
                                href={`/verify/${result.token}`}
                                className="flex items-center justify-between gap-4 rounded-xl border p-4 transition hover:bg-muted/50"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {result.full_name}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {result.event_title_en}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-3 text-sm">
                                    <span className="inline-flex items-center gap-1.5">
                                        <Users className="size-4" />
                                        {result.quantity}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {result.status}
                                    </span>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}
