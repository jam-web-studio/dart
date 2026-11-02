"""Знімок tourism.gov.ua (Webflow), зібраний у браузері, → api/database/seed-data/tourism-gov.json.

Знімок: {css, lists: {url: html}, pages: {path: {s, html}}}. Сторінки зберігаються як HTML між шапкою й підвалом
(розмітка Webflow як є), динамічні списки (новини, накази, звіти) замінюються маркерами з шаблоном одного елемента,
які Laravel заповнює з бази. Новини, накази й звіти стають окремими записами.

    python3 -I tools/convert_webflow.py export.json.gz api/database/seed-data/tourism-gov.json
"""
import gzip, json, re, sys
from bs4 import BeautifulSoup, Comment, NavigableString

SITE = 'https://www.tourism.gov.ua'
src, dst = sys.argv[1], sys.argv[2]
D = json.load(gzip.open(src))
P = {k: v['html'] for k, v in D['pages'].items() if v.get('s') == 200 and v.get('html')}
LISTS = {k: v for k, v in D['lists'].items() if isinstance(v, str)}

GLOBAL_SCRIPT_MARKERS = ('WebFont.load', 'w-mod-', 'google_tags_first_party', 'dataLayer', 'global-search')


def soup(html):
    return BeautifulSoup(html, 'lxml')


def text(el):
    return el.get_text(' ', strip=True) if el else ''


def local(href):
    """Абсолютні посилання на старий сайт → відносні"""
    if href and href.startswith(SITE):
        return href[len(SITE):] or '/'
    return href


def head_meta(s):
    m = lambda **kw: (s.find('meta', attrs=kw) or {}).get('content')
    return {
        'title': s.title.string.strip() if s.title and s.title.string else None,
        'description': m(name='description'),
        'og_image': m(property='og:image'),
    }


def page_scripts(s):
    """Свої скрипти сторінки (без глобальних Webflow/аналітики й віджета доступності)"""
    out = []
    for sc in s.find_all('script'):
        t = (sc.string or '').strip()
        if sc.get('src'):
            u = sc['src']
            if 'jquery-3.5.1' in u or 'googletagmanager' in u or 'webfont' in u or 'accessibility' in u \
                    or u.startswith('/') or 'pulse.gov.ua' in u or 'hbqrknjl5t' in u or 'localhost' in u:
                continue
            # модулі Webflow (взаємодії, слайдери, вкладки) свої для кожного шаблону; вантажаться одразу після jQuery
            out.append({'src': u, 'webflow': True} if '/js/webflow.' in u else {'src': u})
        elif t and not any(k in t for k in GLOBAL_SCRIPT_MARKERS) and not t.startswith('%3C'):
            out.append({'code': t})
    return out


def page_styles(s):
    """Свої стилі сторінки (Webflow кладе custom code у head); стилі віджета доступності пропускаємо"""
    return [st.get_text() for st in s.find_all('style')
            if st.find_parent('head') and '.asw-' not in st.get_text() and st.get_text().strip()]


# ---------- записи колекцій ----------
DATE_RE = re.compile(r'^(\d{2})[./](\d{2})[./](\d{4})$|^(\d{4})-(\d{2})-(\d{2})$')


def parse_date(t):
    m = DATE_RE.match(t.strip())
    if not m:
        return None
    if m.group(1):
        return f'{m.group(3)}-{m.group(2)}-{m.group(1)}'
    return f'{m.group(4)}-{m.group(5)}-{m.group(6)}'


def date_format(t):
    t = t.strip()
    if re.match(r'^\d{2}/\d{2}/\d{4}$', t): return 'd/m/Y'
    if re.match(r'^\d{2}\.\d{2}\.\d{4}$', t): return 'd.m.Y'
    if re.match(r'^\d{4}-\d{2}-\d{2}$', t): return 'Y-m-d'
    return None


posts, orders, reports = {}, {}, {}
for path, html in P.items():
    if not path.startswith(('/blog/', '/orders/', '/reports/')):
        continue
    s = soup(html)
    slug = path.split('/', 2)[2]
    meta = head_meta(s)
    if path.startswith('/blog/'):
        rich = s.select_one('.current-blog-rich')
        cover = s.select_one('.current-blog-img img')
        gallery = []
        for sc in s.find_all('script', type='application/json'):
            pass
        for a in s.select('.eng-blog-gallery a, .eng-blog-gallery img'):
            u = a.get('href') or a.get('src')
            if u and u not in gallery and not u.startswith('#'):
                gallery.append(u)
        # лайтбокс галереї Webflow лежить у json-скрипті
        for sc in s.select('.eng-blog-gallery script.w-json'):
            try:
                for it in json.loads(sc.string).get('items', []):
                    if it.get('url') and it['url'] not in gallery:
                        gallery.append(it['url'])
            except Exception:
                pass
        dt = text(s.select_one('.current-blog-first .news-date'))
        posts[slug] = {
            'slug': slug,
            'title': text(s.select_one('h1.all-news-head')) or meta['title'],
            'published_at': parse_date(dt) if dt else None,
            'cover_image': cover.get('src') if cover else None,
            'body': rich.decode_contents().strip() if rich else '',
            'gallery': gallery,
            'meta_title': meta['title'],
            'meta_description': meta['description'],
            'og_image': meta['og_image'],
            'source_url': SITE + path,
        }
    elif path.startswith('/orders/'):
        hdr = [text(x) for x in s.select('.orders-header')]
        # «Наказ», «№», N, «від», дата, «"», назва, «"»
        number = hdr[2] if len(hdr) > 2 else None
        date = parse_date(hdr[4]) if len(hdr) > 4 else None
        title = hdr[6] if len(hdr) > 6 else meta['title']
        m = re.search(r"const pdfUrl = '([^']+)'", html)
        orders[slug] = {'slug': slug, 'kind': 'order', 'number': number, 'date': date, 'title': title,
                        'url': m.group(1) if m else None, 'source_url': SITE + path}
    else:
        m1 = re.search(r'let reportMonth = (\d+)', html)
        m2 = re.search(r'const reportYear = (\d+)', html)
        reports[slug] = {'slug': slug, 'kind': 'report', 'title': text(s.select_one('.reports-heading')) or meta['title'],
                         'meta': {'month': int(m1.group(1)) if m1 else None, 'year': int(m2.group(1)) if m2 else None},
                         'source_url': SITE + path}

# порядок і ознаки з публічних списків
def list_items(url, sel):
    s = soup(LISTS[url])
    return s


news_pages = sorted([k for k in LISTS if k.startswith('/news-and-announcements')],
                    key=lambda k: int(re.search(r'_page=(\d+)', k).group(1)) if '_page=' in k else 1)
first = soup(LISTS[news_pages[0]])
slug_of = lambda a: a.get('href', '').split('/blog/', 1)[1] if '/blog/' in a.get('href', '') else None
for a in first.select('.popular_news_wrap .w-dyn-item a'):
    if slug_of(a) in posts: posts[slug_of(a)]['is_featured'] = True
for a in first.select('.anounce-wrap .w-dyn-item a'):
    if slug_of(a) in posts: posts[slug_of(a)]['category'] = 'announcement'
for a in first.select('.last-news .w-dyn-item a'):
    if slug_of(a) in posts: posts[slug_of(a)]['is_main'] = True
for p in posts.values():
    p.setdefault('category', 'news')
order_pages = sorted([k for k in LISTS if k.startswith('/orders')],
                     key=lambda k: int(re.search(r'_page=(\d+)', k).group(1)) if '_page=' in k else 1)
n = 0
for k in order_pages:
    for a in soup(LISTS[k]).select('.orders-list-item a'):
        sl = a.get('href', '').split('/orders/', 1)[-1]
        if sl in orders and 'sort' not in orders[sl]:
            orders[sl]['sort'] = n; n += 1
for r in reports.values():
    r['sort'] = (r['meta']['year'] or 0) * 100 + (r['meta']['month'] or 0)

# ---------- шаблони елементів динамічних списків ----------
COLLECTION_BY_HREF = (('/blog/', 'news'), ('/orders/', 'orders'), ('/reports/', 'reports'))


def record_for(coll, slug):
    return {'news': posts, 'orders': orders, 'reports': reports}[coll].get(slug)


def make_template(item, coll):
    """Перший елемент списку → шаблон з {{url}}, {{title}}, {{cover}}, {{date|fmt}}, {{number}}"""
    a = item.find('a', href=True)
    href = a['href'] if a else ''
    prefix = next(p for p, c in COLLECTION_BY_HREF if c == coll)
    slug = href.split(prefix, 1)[1] if prefix in href else None
    rec = record_for(coll, slug) or {}
    for el in item.find_all(href=True):
        if prefix in el['href']:
            el['href'] = '{{url}}'
    for img in item.find_all('img'):
        img['src'] = '{{cover}}'
        for at in ('srcset', 'sizes'):
            if img.has_attr(at): del img[at]
    for node in list(item.find_all(string=True)):
        if isinstance(node, Comment): continue
        t = node.strip()
        if not t: continue
        if rec.get('title') and t == rec['title'].strip():
            node.replace_with('{{title}}')
        elif date_format(t):
            node.replace_with('{{date|' + date_format(t) + '}}')
        elif coll == 'orders' and rec.get('number') and t == rec['number']:
            node.replace_with('{{number}}')
    # порожні дати Webflow (w-dyn-bind-empty)
    for el in item.select('.w-dyn-bind-empty'):
        if 'date' in ' '.join(el.get('class', [])):
            el.string = '{{date|d.m.Y}}'
    return str(item)


def list_filter(dl):
    cls = ' '.join(dl.get('class', [])) + ' ' + ' '.join(sum([p.get('class', []) for p in dl.parents if p.name], []))
    if 'popular_news_wrap' in cls: return 'featured'
    if 'anounce-wrap' in cls: return 'announcement'
    if 'last-news' in cls: return 'main'
    return 'all'


def pager_template(page_path, param, pager):
    """Пагінація з другої сторінки списку (там є і «назад», і «далі») → шаблон з {{prev}}/{{next}}/{{page}}/{{pages}}"""
    p2 = LISTS.get(f'/{page_path.strip("/")}?{param}=2')
    w = soup(p2).select_one('.w-pagination-wrapper') if p2 else None
    w = BeautifulSoup(str(w or pager), 'html.parser').select_one('.w-pagination-wrapper')
    for a in w.select('a.w-pagination-previous'):
        a['href'] = '{{prev}}'
        a.insert_before(Comment('prev')); a.insert_after(Comment('/prev'))
    for a in w.select('a.w-pagination-next'):
        a['href'] = '{{next}}'
        a.insert_before(Comment('next'))
    for l in w.select('link[rel=prerender]'):
        l['href'] = '{{next}}'
        l.insert_after(Comment('/next'))
    if not w.select('link[rel=prerender]'):
        for a in w.select('a.w-pagination-next'):
            a.insert_after(Comment('/next'))
    for c in w.select('.w-page-count'):
        c['aria-label'] = 'Page {{page}} of {{pages}}'
        c.string = '{{page}} / {{pages}}'
    return str(w)


def dynamize(s, page_path):
    """Замінює динамічні списки маркерами <div data-collection=…> з шаблоном елемента"""
    for dl in s.select('.w-dyn-list'):
        items = dl.select('.w-dyn-item')
        hrefs = [a.get('href', '') for a in dl.select('.w-dyn-item a[href]')]
        coll = next((c for p, c in COLLECTION_BY_HREF if any(h.startswith(p) for h in hrefs)), None)
        if not coll or not items:
            continue
        wrap = dl.select_one('.w-dyn-items')
        tpl = make_template(BeautifulSoup(str(items[0]), 'lxml').select_one('.w-dyn-item'), coll)
        pager = dl.select_one('.w-pagination-wrapper')
        param = None
        if pager:
            nxt = pager.select_one('a.w-pagination-next')
            m = re.search(r'\?(\w+_page)=', nxt['href']) if nxt else None
            param = m.group(1) if m else None
        marker = s.new_tag('template')
        marker['data-collection'] = coll
        marker['data-filter'] = list_filter(dl) if coll == 'news' else 'all'
        marker['data-limit'] = str(len(items))
        if param:
            marker['data-page-param'] = param
            marker['data-pager'] = pager_template(page_path, param, pager)
            slot = s.new_tag('template')
            slot['data-pager-slot'] = param
            pager.replace_with(slot)
        marker.append(BeautifulSoup(tpl, 'html.parser'))
        wrap.clear()
        wrap.append(marker)
    return s


# ---------- сторінки ----------
def body_html(s):
    parts = []
    for c in s.body.find_all(recursive=False):
        if c.name in ('header', 'footer', 'script', 'style', 'link'):
            continue
        for x in c.select('.availability-box'):
            x.decompose()
        for x in c.find_all('script'):
            x.decompose()
        parts.append(str(c))
    return '\n'.join(parts)


def absolutize(html):
    return html.replace('href="' + SITE + '/', 'href="/').replace('href="' + SITE + '"', 'href="/"')


pages = []
for path, html in P.items():
    if path.startswith(('/blog/', '/orders/', '/reports/')):
        continue
    s = soup(html)
    meta = head_meta(s)
    dynamize(s, path)
    h1 = s.select_one('h1')
    pages.append({
        'path': path.strip('/') or 'home',
        'title': (text(h1) if h1 else '') or (meta['title'] or path),
        'meta_title': meta['title'],
        'meta_description': meta['description'],
        'og_image': meta['og_image'],
        'wf_page': s.html.get('data-wf-page'),
        'body': absolutize(body_html(s)),
        'scripts': page_scripts(s),
        'styles': page_styles(s),
    })

# ---------- шаблони сторінок записів (новина, наказ, звіт) ----------
def detail_template(sample_path, fill):
    s = soup(P[sample_path])
    fill(s)
    return {'wf_page': s.html.get('data-wf-page'), 'body': absolutize(body_html(s)), 'scripts': page_scripts(s), 'styles': page_styles(s)}


def fill_post(s):
    s.select_one('.current-blog-first .news-date').string = '{{date|d.m.Y}}'
    s.select_one('h1.all-news-head').string = '{{title}}'
    img = s.select_one('.current-blog-img img')
    img['src'] = '{{cover}}'
    for at in ('srcset', 'sizes'):
        if img.has_attr(at): del img[at]
    rich = s.select_one('.current-blog-rich'); rich.clear(); rich.append('{{{body}}}')
    gal = s.select_one('.eng-blog-gallery')
    if gal:
        gal.clear(); gal.append('{{{gallery}}}')


def fill_order(s):
    hdr = s.select('.orders-header')
    hdr[2].string = '{{number}}'; hdr[4].string = '{{date|Y-m-d}}'; hdr[6].string = '{{title}}'
    s.select_one('#pdf-view')['data-pdf'] = '{{pdf}}'
    s.select_one('.download-button')['data-pdf'] = '{{pdf}}'
    del s.select_one('.download-button')['onclick']


def fill_report(s):
    s.select_one('.reports-heading').string = '{{title}}'
    holder = s.select_one('#report-container')
    holder['data-month'] = '{{month}}'
    holder['data-year'] = '{{year}}'


# Скрипти шаблонів записів: на старому сайті адреса PDF і місяць звіту були вписані в код кожної сторінки,
# у нас їх беремо з data-атрибутів
ORDER_JS = """(function(){var v=document.getElementById('pdf-view');if(!v)return;var u=v.getAttribute('data-pdf');
document.querySelectorAll('.download-button[data-pdf]').forEach(function(b){b.addEventListener('click',function(){var a=document.createElement('a');a.href=b.getAttribute('data-pdf');a.download='';a.target='_blank';a.rel='noopener';a.click();});});
if(!u||!window.pdfjsLib)return;pdfjsLib.getDocument(u).promise.then(function(pdf){for(var n=1;n<=pdf.numPages;n++){(function(c,n){c.classList.add('pdf-page');v.appendChild(c);
pdf.getPage(n).then(function(page){var vp=page.getViewport({scale:1.5});c.width=vp.width;c.height=vp.height;page.render({canvasContext:c.getContext('2d'),viewport:vp});});})(document.createElement('canvas'),n);}});})();"""
REPORT_JS = """(function(){var c=document.querySelector('#report-container[data-month]');if(!c)return;var m=('0'+c.getAttribute('data-month')).slice(-2);
fetch('https://script.google.com/macros/s/AKfycbxdGxZDUGhPVoOTcgFxMigrfQkUWJeipv_MjUbI4e_PyzhVRuZH4xEFQEDUp4EguzaGrQ/exec?month='+m+'&year='+c.getAttribute('data-year'))
.then(function(r){return r.text();}).then(function(h){c.innerHTML=h;}).catch(function(e){c.innerHTML='Помилка завантаження звіту.';console.error(e);});})();"""


plain_post = next(k for k in P if k.startswith('/blog/') and 'data-wf-page="64b680cfc9ad6b5c6f36ae08"' in P[k])
templates = {
    'post': detail_template(plain_post, fill_post),
    'order': detail_template(next(k for k in P if k.startswith('/orders/')), fill_order),
    'report': detail_template(next(k for k in P if k.startswith('/reports/')), fill_report),
}
wf_only = lambda t: [x for x in t['scripts'] if x.get('webflow')]
templates['order']['scripts'] = wf_only(templates['order']) + [{'src': 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js'}, {'code': ORDER_JS}]
templates['report']['scripts'] = wf_only(templates['report']) + [{'code': REPORT_JS}]

# ---------- шапка й підвал ----------
home = soup(P['/news-and-announcements'])
header = home.find('header')
for el in header.select('.w--current'):
    el['class'] = [c for c in el['class'] if c != 'w--current']
    if el.has_attr('aria-current'): del el['aria-current']
footer = home.find('footer')
global_script = next(sc.string for sc in home.find_all('script') if sc.string and 'global-search' in sc.string)
accessibility = soup(P['/']).select_one('.availability-box')

# стилі шапки однакові на всіх сторінках: лишаємо їх у layout.head_styles
head_styles = page_styles(home)
for item in [*pages, *templates.values()]:
    item['styles'] = [st for st in item['styles'] if st not in head_styles]

seed = {
    'source': SITE,
    'crawled_at': D.get('crawled_at'),
    'layout': {
        'header': absolutize(str(header)),
        'footer': absolutize(str(footer)),
        'accessibility': str(accessibility) if accessibility else '',
        'global_script': global_script,
        'head_styles': head_styles,
    },
    'templates': templates,
    'pages': sorted(pages, key=lambda p: p['path']),
    'posts': sorted(posts.values(), key=lambda p: p['published_at'] or '', reverse=True),
    'documents': list(orders.values()) + list(reports.values()),
}
json.dump(seed, open(dst, 'w'), ensure_ascii=False, indent=0)
print(f"pages {len(pages)}, posts {len(posts)}, orders {len(orders)}, reports {len(reports)}")
