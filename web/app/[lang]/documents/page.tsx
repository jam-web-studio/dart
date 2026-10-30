import type { Metadata } from 'next'
import { api, formatDate, t, type Lang } from '@/lib/api'
import Breadcrumbs from '@/components/Breadcrumbs'

type Doc = { id: number; group: string | null; title: string; number: string | null; date: string | null; url: string | null; description: string | null }
type Props = { params: Promise<{ lang: Lang }> }

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { lang } = await params
  return { title: t(lang, 'Документи', 'Documents') }
}

export default async function DocumentsPage({ params }: Props) {
  const { lang } = await params
  const { data } = await api<{ data: Doc[] }>('documents', { locale: lang })
  const groups = Object.entries(Object.groupBy(data, (d) => d.group || ''))
  return (
    <div className="container page">
      <Breadcrumbs lang={lang} items={[{ title: t(lang, 'Документи', 'Documents') }]} />
      <h1>{t(lang, 'Документи', 'Documents')}</h1>
      {groups.map(([group, docs]) => (
        <section key={group} className="docs">
          {group && <h2>{group}</h2>}
          <ul>
            {docs!.map((d) => (
              <li key={d.id}>
                <a href={d.url || '#'} target="_blank" rel="noopener">{d.title}</a>
                <span className="docs__meta">{[d.number && `№ ${d.number}`, d.date && formatDate(d.date, lang)].filter(Boolean).join(' · ')}</span>
                {d.description && <p>{d.description}</p>}
              </li>
            ))}
          </ul>
        </section>
      ))}
    </div>
  )
}
