import type { Metadata } from 'next'
import { api, prefix, t, type Lang, type PostCard } from '@/lib/api'
import NewsCard from '@/components/NewsCard'
import Breadcrumbs from '@/components/Breadcrumbs'
import Pager from '@/components/Pager'

type Props = { params: Promise<{ lang: Lang }>; searchParams: Promise<{ page?: string; category?: string }> }

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { lang } = await params
  return { title: t(lang, 'Новини', 'News') }
}

export default async function NewsPage({ params, searchParams }: Props) {
  const { lang } = await params
  const { page = '1', category } = await searchParams
  const res = await api<{ data: PostCard[]; meta: { page: number; pages: number }; categories: { slug: string; name: string }[] }>('news', { locale: lang, page, category })
  const base = `${prefix(lang)}/news`
  const href = (p: number) => `${base}?${new URLSearchParams({ ...(category ? { category } : {}), page: String(p) })}`
  return (
    <div className="container page">
      <Breadcrumbs lang={lang} items={[{ title: t(lang, 'Новини', 'News') }]} />
      <h1>{t(lang, 'Новини', 'News')}</h1>
      {res.categories.length > 1 && (
        <nav className="chips">
          <a href={base} aria-current={!category ? 'true' : undefined}>{t(lang, 'Усі', 'All')}</a>
          {res.categories.map((c) => (
            <a key={c.slug} href={`${base}?category=${c.slug}`} aria-current={category === c.slug ? 'true' : undefined}>{c.name}</a>
          ))}
        </nav>
      )}
      <div className="grid">{res.data.map((p) => <NewsCard key={p.slug} post={p} lang={lang} />)}</div>
      <Pager page={res.meta.page} pages={res.meta.pages} href={href} />
    </div>
  )
}
