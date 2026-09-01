import { Link, usePage, router } from '@inertiajs/react';
import { Building2, LayoutDashboard, Users, LogOut } from 'lucide-react';
import PageFlashMessage from '@/Components/PageFlashMessage';

interface SuperAdminLayoutProps {
  children: React.ReactNode;
}

const NAV_ITEMS = [
  { href: '/super-admin', label: 'ダッシュボード', icon: LayoutDashboard },
  { href: '/super-admin/companies', label: '事業所管理', icon: Building2 },
  { href: '/super-admin/users', label: 'ユーザー一覧', icon: Users },
];

/**
 * スーパー管理画面のレイアウト
 *
 * SaaS運営者が全事業所を横断して操作する画面。
 * 各事業所の管理画面（AdminLayout）とは意図的に配色を変え、
 * どちらを操作しているか取り違えないようにしている。
 */
export default function SuperAdminLayout({ children }: SuperAdminLayoutProps) {
  const { url, props } = usePage();
  const auth = props.auth as { user: { name: string; email: string } | null } | undefined;
  const user = auth?.user;

  const isActive = (href: string) =>
    href === '/super-admin' ? url === '/super-admin' : url.startsWith(href);

  return (
    <div className="min-h-screen bg-gray-100">
      <header className="bg-slate-800 text-white">
        <div className="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <span className="font-bold">運営管理</span>
            <span className="text-xs text-slate-300">スーパー管理者</span>
          </div>
          <div className="flex items-center gap-4">
            {user && <span className="text-sm text-slate-200">{user.name}</span>}
            <button
              onClick={() => router.post('/admin/logout')}
              className="flex items-center gap-1 text-sm text-slate-200 hover:text-white"
            >
              <LogOut className="h-4 w-4" />
              ログアウト
            </button>
          </div>
        </div>

        <nav className="max-w-7xl mx-auto px-4 flex gap-1">
          {NAV_ITEMS.map(({ href, label, icon: Icon }) => (
            <Link
              key={href}
              href={href}
              className={`flex items-center gap-2 px-4 py-2 text-sm rounded-t-lg transition-colors ${
                isActive(href)
                  ? 'bg-gray-100 text-slate-900 font-medium'
                  : 'text-slate-200 hover:bg-slate-700'
              }`}
            >
              <Icon className="h-4 w-4" />
              {label}
            </Link>
          ))}
        </nav>
      </header>

      <main className="max-w-7xl mx-auto px-4 py-6">
        <PageFlashMessage />
        {children}
      </main>
    </div>
  );
}
