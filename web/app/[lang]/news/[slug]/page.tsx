import type { Metadata } from 'next'
import { notFound } from 'next/navigation'
import { api, formatDate, NotFound, prefix, t, type Lang, type PostCard } from '@/lib/api'
import NewsCard from '@/components/NewsCard'
import Breadcrumbs from '@/components/Breadcrumbs'

type Post = PostCard & { body: string | null; gallery: string[]; meta: { title: string | null; description: string | null } }
type Props = { params: Promise<{ lang: Lang; slug: string }> }

async function load(lang: Lang, slug: string) {
  try {
    return await api<{ data: Post; related: PostCard[] }>(`news/${encodeURIComponent(slug)}`, { locale: lang })
  } catch (e) {
    if (e instanceof NotFound) notFound()
    throw e
  }
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { lang, slug } = await params
  const { data } = await load(lang, slug)
  return {
    title: data.meta.title || data.title,
    description: data.meta.description || data.excerpt || undefined,
    openGraph: { title: data.title, images: data.cover ? [data.cover] : undefined, type: 'article' },
  }
}

export default async function PostPage({ params }: Props) {
  const { lang, slug } = await params
  const { data, related } = await load(lang, slug)
  return (
    <div className="container page">
      <Breadcrumbs lang={lang} items={[{ title: t(lang, 'Новини', 'News'), url: `${prefix(lang)}/news` }, { title: data.title }]} />
      <article className="post">
        <h1>{data.title}</h1>
        <div className="card__meta">
          {data.date && <time dateTime={data.date}>{formatDate(data.date, lang)}</time>}
          {data.category && <a href={`${prefix(lang)}/news?category=${data.category.slug}`}>{data.category.name}</a>}
        </div>
        {data.cover && <img className="post__cover" src={data.cover} alt="" />}
        {data.body && <div className="prose" dangerouslySetInnerHTML={{ __html: data.body }} />}
        {!!data.gallery.length && (
          <div className="gallery">{data.gallery.map((g) => <a key={g} href={g} target="_blank" rel="noopener"><img src={g} alt="" loading="lazy" /></a>)}</div>
        )}
      </article>
      {!!related.length && (
        <section className="section">
          <h2>{t(lang, 'Інші новини', 'More news')}</h2>
          <div className="grid">{related.map((p) => <NewsCard key={p.slug} post={p} lang={lang} />)}</div>
        </section>
      )}
    </div>
  )
}
