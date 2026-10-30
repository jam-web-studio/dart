import { api, prefix, t, type Lang, type PostCard } from '@/lib/api'
import NewsCard from '@/components/NewsCard'

type Home = { featured: PostCard[]; news: PostCard[]; banners: Record<string, { title: string; text: string | null; image: string | null; url: string | null }[]> }

export default async function HomePage({ params }: { params: Promise<{ lang: Lang }> }) {
  const { lang } = await params
  const [home, layout] = await Promise.all([api<Home>('home', { locale: lang }), api('layout', { locale: lang }, 300)])
  const s = layout.settings
  const hero = home.featured[0]
  return (
    <>
      <section className="hero" style={s.hero_image ? { backgroundImage: `url(${s.hero_image})` } : undefined}>
        <div className="container hero__in">
          <h1>{s.hero_title || (lang === 'en' ? s.site_name_en : s.site_name)}</h1>
          {s.hero_text && <p>{s.hero_text}</p>}
          {hero && <a className="btn" href={hero.url}>{hero.title}</a>}
        </div>
      </section>

      {!!home.banners.home?.length && (
        <section className="container banners">
          {home.banners.home.map((b) => (
            <a key={b.title} href={b.url || '#'} className="banner">
              {b.image && <img src={b.image} alt="" loading="lazy" />}
              <strong>{b.title}</strong>
              {b.text && <span>{b.text}</span>}
            </a>
          ))}
        </section>
      )}

      <section className="container section">
        <div className="section__head">
          <h2>{t(lang, 'Новини', 'News')}</h2>
          <a href={`${prefix(lang)}/news`}>{t(lang, 'Усі новини', 'All news')} →</a>
        </div>
        <div className="grid">
          {home.news.map((p) => <NewsCard key={p.slug} post={p} lang={lang} />)}
        </div>
      </section>
    </>
  )
}
