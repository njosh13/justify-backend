import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types/billing';

export function Pagination({ page }: { page: Paginated<unknown> }) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center gap-1">
            {page.links.map((link, i) => (
                <Button
                    key={i}
                    asChild={!!link.url}
                    variant={link.active ? 'default' : 'outline'}
                    size="sm"
                    disabled={!link.url}
                >
                    {link.url ? (
                        <Link
                            href={link.url}
                            preserveScroll
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ) : (
                        <span
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    )}
                </Button>
            ))}
        </div>
    );
}
