import { api, NotFound, type Lang, type Layout, type Rendered, type SearchHit } from '@/lib/api'
import { renderDocument } from '@/lib/document'

// Усі сторінки сайту: та сама адреса, що й на tourism.gov.ua. Англійська версія під /en/…
export const dynamic = 'force-dynamic'

const site = () => (process.env.SITE_URL || 'http://localhost:3000').replace(/\/$/, '')
const esc = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

function html(body: string, status = 200) {
  const headers: Record<string, string> = { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'public, max-age=0, s-maxage=60, stale-while-revalidate=300' }
  if (process.env.INDEXABLE !== 'true') headers['X-Robots-Tag'] = 'noindex, nofollow'
  return new Response(body, { status, headers })
}

// обгортка як у сторінки «Контакти»: відступ під фіксовану шапку дає клас header-trigger
const container = (inner: string) => `<section class="reports-wrap header-trigger"><div class="o-container w-container" style="padding-bottom:80px">${inner}</div></section>`

function searchPage(lang: Lang, query: string, hits: SearchHit[] | null) {
  const t = (uk: string, en: string) => (lang === 'en' ? en : uk)
  const form = `<form action="${lang === 'en' ? '/en' : ''}/search" method="get" class="w-form" style="display:flex;gap:12px;margin:24px 0 32px;flex-wrap:wrap">
<input class="search-input w-input" style="flex:1;min-width:220px" maxlength="256" name="query" type="search" value="${esc(query)}" placeholder="${t('Введіть назву чи ключове слово', 'Enter a title or keyword')}" required/>
<input type="submit" class="search-button w-button" value="${t('Пошук', 'Search')}"/></form>`
  const list = hits === null ? '' : hits.length === 0
    ? `<p>${t('Нічого не знайдено. Спробуйте інше слово.', 'Nothing found. Try another word.')}</p>`
    : `<p>${t('Знайдено', 'Found')}: ${hits.length}</p><div role="list">${hits.map((h) => `<div role="listitem" style="padding:20px 0;border-bottom:1px solid #c2c5cb">
${h.date ? `<div class="news-date">${esc(h.date)}</div>` : ''}<a href="${esc(h.url)}" class="all-news-head" style="display:block;margin:6px 0">${esc(h.title)}</a>${h.text ? `<p style="margin:0">${esc(h.text)}</p>` : ''}</div>`).join('')}</div>`
  return container(`<h1 class="all-news-head">${t('Результати пошуку', 'Search results')}</h1>${form}${list}`)
}

export async function GET(req: Request, ctx: { params: Promise<{ path?: string[] }> }) {
  const { path: parts = [] } = await ctx.params
  const url = new URL(req.url)
  let segs = parts.map(decodeURIComponent)
  let lang: Lang = 'uk'
  if (segs[0] === 'en') { lang = 'en'; segs = segs.slice(1) }
  if (segs[0] === 'uk') return Response.redirect(`${site()}/${segs.slice(1).join('/')}${url.search}`, 301)
  const path = segs.join('/')
  const pub = (lang === 'en' ? '/en' : '') + '/' + path
  const query: Record<string, string> = {}
  url.searchParams.forEach((v, k) => { query[k] = v })

  const layout = await api<Layout>('layout', {}, 60)

  if (path === 'search') {
    const q = (query.query || '').trim()
    const hits = q.length >= 2 ? (await api<{ data: SearchHit[] }>('search', { query: q, locale: lang }, 60)).data : null
    return html(renderDocument({ lang, path: pub, site: site(), layout, canonical: pub, page: { title: lang === 'en' ? 'Search' : 'Пошук', meta: { title: null, description: null, image: null }, wf_page: null, html: searchPage(lang, q, hits), scripts: [], styles: [] } }))
  }

  try {
    const page = await api<Rendered>('render', { ...query, path: path || '/', locale: lang }, 60)
    // канонічна адреса без номерів сторінок списків, крім самих сторінок списку
    const canonical = pub === '/' || !url.search ? pub : pub + url.search
    return html(renderDocument({ lang, path: pub === '/home' ? '/' : pub, site: site(), layout, page, canonical: pub === '/home' ? '/' : canonical }))
  } catch (e) {
    if (!(e instanceof NotFound)) throw e
    const t = (uk: string, en: string) => (lang === 'en' ? en : uk)
    const body = container(`<h1 class="all-news-head">${t('Сторінку не знайдено', 'Page not found')}</h1><p>${t('Можливо, її перенесли або видалили.', 'It may have been moved or deleted.')}</p><p><a href="${lang === 'en' ? '/en/home' : '/'}" class="download-button" style="display:inline-block;color:inherit;text-decoration:none">${t('На головну', 'Go to the home page')}</a></p>`)
    return html(renderDocument({ lang, path: pub, site: site(), layout, page: { title: t('Сторінку не знайдено', 'Page not found'), meta: { title: null, description: null, image: null }, wf_page: null, html: body, scripts: [], styles: [] } }), 404)
  }
}
