import type { Metadata } from 'next'
import { api, t, type Lang, type PostCard } from '@/lib/api'
import NewsCard from '@/components/NewsCard'
import Breadcrumbs from '@/components/Breadcrumbs'

type Props = { params: Promise<{ lang: Lang }>; searchParams: Promise<{ q?: string }> }

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { lang } = await params
  return { title: t(lang, 'Пошук', 'Search'), robots: { index: false } }
}

export default async function SearchPage({ params, searchParams }: Props) {
  const { lang } = await params
  const { q = '' } = await searchParams
  const res = await api<{ posts: PostCard[]; pages: { title: string; url: string }[] }>('search', { locale: lang, q }, 0)
  const empty = q.trim().length >= 2 && !res.posts.length && !res.pages.length
  return (
    <div className="container page">
      <Breadcrumbs lang={lang} items={[{ title: t(lang, 'Пошук', 'Search') }]} />
      <h1>{t(lang, 'Пошук', 'Search')}</h1>
      <form className="search search--big">
        <input name="q" type="search" defaultValue={q} aria-label={t(lang, 'Пошук', 'Search')} />
        <button className="btn">{t(lang, 'Знайти', 'Search')}</button>
      </form>
      {empty && <p>{t(lang, 'Нічого не знайдено. Спробуйте інше слово.', 'Nothing found. Try another word.')}</p>}
      {!!res.pages.length && <ul className="links">{res.pages.map((p) => <li key={p.url}><a href={p.url}>{p.title}</a></li>)}</ul>}
      <div className="grid">{res.posts.map((p) => <NewsCard key={p.slug} post={p} lang={lang} />)}</div>
    </div>
  )
}
