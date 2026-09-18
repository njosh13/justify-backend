import { usePage } from '@inertiajs/react';
import AppLogoIcon from './app-logo-icon';
import type { Auth } from '@/types';

export default function AppLogo() {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-md">
                <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    Justify
                </span>
                {auth.firm && (
                    <span className="text-muted-foreground truncate text-xs leading-tight">
                        {auth.firm.name}
                    </span>
                )}
            </div>
        </>
    );
}
