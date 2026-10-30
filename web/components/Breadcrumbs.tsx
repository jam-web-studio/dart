import { prefix, t, type Lang } from '@/lib/api'

export default function Breadcrumbs({ lang, items }: { lang: Lang; items: { title: string; url?: string }[] }) {
  return (
    <nav className="crumbs" aria-label={t(lang, 'Навігація', 'Breadcrumbs')}>
      <a href={prefix(lang) || '/'}>{t(lang, 'Головна', 'Home')}</a>
      {items.map((i, n) => (
        <span key={n}>{i.url ? <a href={i.url}>{i.title}</a> : i.title}</span>
      ))}
    </nav>
  )
}
