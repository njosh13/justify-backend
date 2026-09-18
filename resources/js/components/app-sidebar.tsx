import { Link, usePage } from '@inertiajs/react';
import {
    BookOpenCheck,
    Briefcase,
    Calculator,
    FileText,
    LayoutGrid,
    Scale,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as aroIndex } from '@/routes/admin/aro';
import { index as billsIndex } from '@/routes/bills';
import { index as calculatorIndex } from '@/routes/calculator';
import { index as clientsIndex } from '@/routes/clients';
import { index as mattersIndex } from '@/routes/matters';
import type { Auth, NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Fee calculator', href: calculatorIndex(), icon: Calculator },
    { title: 'Clients', href: clientsIndex(), icon: Users },
    { title: 'Matters', href: mattersIndex(), icon: Briefcase },
    { title: 'Bills', href: billsIndex(), icon: FileText },
];

const adminNavItems: NavItem[] = [
    { title: 'ARO catalogue', href: aroIndex(), icon: BookOpenCheck },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Advocates (Remuneration) Order',
        href: 'https://new.kenyalaw.org/akn/ke/act/ln/1962/64/eng@2022-12-31',
        icon: Scale,
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain
                    items={auth.firm ? [...mainNavItems, ...adminNavItems] : []}
                />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
