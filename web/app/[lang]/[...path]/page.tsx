import type { Metadata } from 'next'
import { notFound } from 'next/navigation'
import { api, NotFound, type Lang } from '@/lib/api'
import Breadcrumbs from '@/components/Breadcrumbs'

type Link = { title: string; url: string }
type Page = Link & { excerpt: string | null; body: string | null; cover: string | null; breadcrumbs: Link[]; section: Link[]; meta: { title: string | null; description: string | null } }
type Props = { params: Promise<{ lang: Lang; path: string[] }> }

async function load(lang: Lang, path: string[]) {
  try {
    return (await api<{ data: Page }>('pages', { locale: lang, path: path.map(decodeURIComponent).join('/') })).data
  } catch (e) {
    if (e instanceof NotFound) notFound()
    throw e
  }
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { lang, path } = await params
  const p = await load(lang, path)
  return { title: p.meta.title || p.title, description: p.meta.description || p.excerpt || undefined }
}

export default async function StaticPage({ params }: Props) {
  const { lang, path } = await params
  const p = await load(lang, path)
  return (
    <div className="container page">
      <Breadcrumbs lang={lang} items={[...p.breadcrumbs, { title: p.title }]} />
      <div className={p.section.length ? 'with-aside' : undefined}>
        {!!p.section.length && (
          <aside className="aside">
            <ul>{p.section.map((s) => <li key={s.url}><a href={s.url} aria-current={s.url === p.url ? 'page' : undefined}>{s.title}</a></li>)}</ul>
          </aside>
        )}
        <article>
          <h1>{p.title}</h1>
          {p.cover && <img className="post__cover" src={p.cover} alt="" />}
          {p.body && <div className="prose" dangerouslySetInnerHTML={{ __html: p.body }} />}
        </article>
      </div>
    </div>
  )
}
