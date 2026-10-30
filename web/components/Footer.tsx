import { type Lang, type Layout } from '@/lib/api'

const SOCIAL: [string, string][] = [['facebook', 'Facebook'], ['instagram', 'Instagram'], ['youtube', 'YouTube'], ['telegram', 'Telegram'], ['twitter', 'X']]

export default function Footer({ lang, layout }: { lang: Lang; layout: Layout }) {
  const s = layout.settings
  const name = (lang === 'en' ? s.site_name_en : s.site_name) || s.site_name
  const address = (lang === 'en' ? s.address_en : s.address) || s.address
  return (
    <footer className="site-footer">
      <div className="container footer__in">
        <div className="footer__about">
          <img src="/logo-white.svg" alt="" width={56} height={56} />
          <p className="footer__name">{name}</p>
          {address && <p>{address}</p>}
          {s.phone && <p><a href={`tel:${s.phone.replace(/[^\d+]/g, '')}`}>{s.phone}</a></p>}
          {s.email && <p><a href={`mailto:${s.email}`}>{s.email}</a></p>}
        </div>
        <nav className="footer__menu">
          {(layout.menus.footer || []).map((i) => (
            <a key={i.url + i.label} href={i.url} target={i.new_tab ? '_blank' : undefined}>{i.label}</a>
          ))}
        </nav>
        <div className="footer__social">
          {SOCIAL.filter(([k]) => s[k]).map(([k, label]) => (
            <a key={k} href={s[k]} target="_blank" rel="noopener">{label}</a>
          ))}
        </div>
      </div>
      <div className="container footer__bottom">
        <span>© {new Date().getFullYear()} {name}</span>
        {s.footer_text && <span>{s.footer_text}</span>}
      </div>
    </footer>
  )
}
