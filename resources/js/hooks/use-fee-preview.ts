import { useEffect, useRef, useState } from 'react';
import { preview } from '@/routes/aro';
import type { PreviewResponse } from '@/types/billing';

/**
 * Debounced call to `POST /aro/preview` — the same engine a bill uses. Returns
 * the computed figure, the engine's message when the inputs are incomplete,
 * and a loading flag.
 */
export function useFeePreview(
    payload: Record<string, unknown> | null,
    delay = 250,
) {
    const [result, setResult] = useState<PreviewResponse | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const controller = useRef<AbortController | null>(null);
    const key = JSON.stringify(payload);

    useEffect(() => {
        if (payload === null) {
            setResult(null);
            setError(null);

            return;
        }

        const timer = setTimeout(async () => {
            controller.current?.abort();
            controller.current = new AbortController();
            setLoading(true);

            try {
                const token =
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? '';
                const response = await fetch(preview.url(), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                        'X-XSRF-TOKEN': readXsrfCookie(),
                    },
                    body: key,
                    signal: controller.current.signal,
                });
                const body = await response.json();

                if (response.ok) {
                    setResult(body as PreviewResponse);
                    setError(null);
                } else {
                    setResult(null);
                    setError(
                        (body as { message?: string }).message ??
                            'Could not compute.',
                    );
                }
            } catch (e) {
                if ((e as Error).name !== 'AbortError') {
                    setError('Network error while computing.');
                }
            } finally {
                setLoading(false);
            }
        }, delay);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key, delay]);

    return { result, error, loading };
}

function readXsrfCookie(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}
