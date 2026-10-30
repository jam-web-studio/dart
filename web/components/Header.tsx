import Link from 'next/link'
import { prefix, t, type Lang, type Layout } from '@/lib/api'
import MobileMenu from './MobileMenu'

export default function Header({ lang, layout }: { lang: Lang; layout: Layout }) {
  const s = layout.settings
  const name = (lang === 'en' ? s.site_name_en : s.site_name) || s.site_name
  const menu = layout.menus.header || []
  return (
    <header className="site-header">
      <div className="topbar">
        <div className="container topbar__in">
          <nav className="topbar__links">
            {(layout.menus.top || []).map((i) => (
              <a key={i.url} href={i.url} target={i.new_tab ? '_blank' : undefined} rel={i.new_tab ? 'noopener' : undefined}>{i.label}</a>
            ))}
          </nav>
          <div className="topbar__right">
            <form action={`${prefix(lang)}/search`} className="search">
              <input name="q" type="search" placeholder={t(lang, 'Пошук', 'Search')} aria-label={t(lang, 'Пошук', 'Search')} />
            </form>
            <nav className="langs" aria-label="Language">
              <a href="/" aria-current={lang === 'uk' ? 'true' : undefined}>UA</a>
              <a href="/en" aria-current={lang === 'en' ? 'true' : undefined}>EN</a>
            </nav>
          </div>
        </div>
      </div>
      <div className="container header__in">
        <Link href={prefix(lang) || '/'} className="logo">
          <img src="/logo.svg" alt="" width={56} height={56} />
          <span>{name}</span>
        </Link>
        <nav className="mainnav" aria-label={t(lang, 'Головне меню', 'Main menu')}>
          <ul>
            {menu.map((i) => (
              <li key={i.label} className={i.children?.length ? 'has-sub' : undefined}>
                <a href={i.url} target={i.new_tab ? '_blank' : undefined}>{i.label}</a>
                {!!i.children?.length && (
                  <ul className="sub">
                    {i.children.map((c) => (
                      <li key={c.label}><a href={c.url} target={c.new_tab ? '_blank' : undefined}>{c.label}</a></li>
                    ))}
                  </ul>
                )}
              </li>
            ))}
          </ul>
        </nav>
        <MobileMenu menu={menu} label={t(lang, 'Меню', 'Menu')} />
      </div>
    </header>
  )
}
