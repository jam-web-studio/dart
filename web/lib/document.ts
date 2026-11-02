import type { Lang, Layout, Rendered, Script } from './api'

// Сайт зроблено у Webflow: віддаємо ту саму розмітку, стилі й скрипти, що й старий сайт,
// лише вміст сторінок і списки (новини, накази, звіти) тепер із Laravel.
export const WF_SITE = '5fe0d678b18d7e19e32c3389'
const CDN = `https://cdn.prod.website-files.com/${WF_SITE}`
const JQUERY = `https://d3e54v103j8qbb.cloudfront.net/js/jquery-3.5.1.min.dc5e7f18c8.js?site=${WF_SITE}`
const ACCESSIBILITY = `${CDN}/68d55186346c926f21c73bf4_accessibility-new.txt`
const FONTS = ['Oswald:200,300,400,500,600,700', 'Open Sans:300,300italic,400,400italic,600,600italic,700,700italic,800,800italic', 'Montserrat:100,100italic,200,200italic,300,300italic,400,400italic,500,500italic,600,600italic,700,700italic,800,800italic,900,900italic']

// змінюйте разом із public/site.css, щоб браузери не тримали старий файл
const SITE_CSS_V = '1'

const esc = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
// вміст <script>/<style> не можна обривати рядком </script>
const raw = (s: string) => s.replace(/<\/(script|style)/gi, '<\\/$1')

/** Підсвічує поточний пункт меню, як це робить Webflow (клас w--current) */
export function markCurrent(html: string, path: string): string {
  return html.replace(/<a\b([^>]*?)href="([^"]*)"([^>]*)>/g, (tag, before: string, href: string, after: string) => {
    if (href !== path) return tag
    const attrs = `${before}href="${href}"${after}`
    const withClass = /\bclass="/.test(attrs) ? attrs.replace(/\bclass="([^"]*)"/, (_m, c) => `class="${c} w--current"`) : `${attrs} class="w--current"`
    return `<a${withClass} aria-current="page">`
  })
}

function script(s: Script): string {
  if (s.src) return `<script src="${esc(s.src)}" type="text/javascript"></script>`
  return s.code ? `<script>${raw(s.code)}</script>` : ''
}

export type DocumentInput = {
  lang: Lang
  path: string
  site: string
  layout: Layout
  page: Pick<Rendered, 'title' | 'meta' | 'wf_page' | 'html' | 'scripts' | 'styles'>
  canonical?: string
}

export function renderDocument({ lang, path, site, layout, page, canonical }: DocumentInput): string {
  const siteName = (lang === 'en' ? layout.settings.site_name_en : layout.settings.site_name) || ''
  const title = page.meta.title || page.title || siteName
  const description = page.meta.description || layout.settings.meta_description || ''
  const image = page.meta.image || layout.settings.og_image || `${CDN}/5fe47fac541e6e361f91eb6d_hero.jpg`
  const url = site + (canonical ?? path)
  const webflow = page.scripts.filter((s) => s.webflow)
  const custom = page.scripts.filter((s) => !s.webflow)
  // сторінки без власного набору модулів Webflow (пошук, 404) беруть загальний
  const modules: Script[] = webflow.length ? webflow : ['js/webflow.schunk.78cb8371a037d463.js', 'js/webflow.schunk.9b5d91a31c3e1121.js', 'js/webflow.a83c20ee.39db2363340a3098.js'].map((f) => ({ src: `${CDN}/${f}` }))
  const noindex = process.env.INDEXABLE !== 'true'

  return `<!DOCTYPE html><html data-wf-domain="www.tourism.gov.ua" data-wf-page="${esc(page.wf_page || '')}" data-wf-site="${WF_SITE}" lang="${lang}"><head><meta charset="utf-8"/>
<title>${esc(title)}</title>
<meta content="${esc(description)}" name="description"/>
<meta content="${esc(title)}" property="og:title"/><meta content="${esc(description)}" property="og:description"/><meta content="${esc(image)}" property="og:image"/>
<meta content="${esc(title)}" name="twitter:title"/><meta content="${esc(description)}" name="twitter:description"/><meta content="${esc(image)}" name="twitter:image"/>
<meta property="og:type" content="website"/><meta content="summary_large_image" name="twitter:card"/><meta content="${esc(url)}" property="og:url"/>
<meta content="width=device-width, initial-scale=1" name="viewport"/>${noindex ? '<meta name="robots" content="noindex, nofollow"/>' : ''}
<link rel="canonical" href="${esc(url)}"/>
<link href="/webflow/tourismgovua.webflow.css" rel="stylesheet" type="text/css"/>
<link href="/site.css?v=${SITE_CSS_V}" rel="stylesheet" type="text/css"/>
${page.styles.map((s) => `<style>${raw(s)}</style>`).join('')}
<link href="https://fonts.googleapis.com" rel="preconnect"/><link href="https://fonts.gstatic.com" rel="preconnect" crossorigin="anonymous"/>
<script src="https://ajax.googleapis.com/ajax/libs/webfont/1.6.26/webfont.js" type="text/javascript"></script>
<script type="text/javascript">WebFont.load({google:{families:${JSON.stringify(FONTS)}}});</script>
<script type="text/javascript">!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
<link href="${CDN}/5fe4848c0db7fa605ee56f3f_fav.png" rel="shortcut icon" type="image/x-icon"/><link href="${CDN}/5fe484b4e91bfe76364c6265_wclip.png" rel="apple-touch-icon"/>
${layout.head_styles.map((s) => `<style>${raw(s)}</style>`).join('')}
</head><body>${markCurrent(layout.header, path)}${page.html}${layout.footer}${layout.accessibility}
<script src="${JQUERY}" type="text/javascript"></script>
${modules.map(script).join('')}
<script src="${ACCESSIBILITY}"></script>
<script>${raw(layout.global_script)}</script>
${custom.map(script).join('\n')}
</body></html>`
}
