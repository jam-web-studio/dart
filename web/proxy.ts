import { NextRequest, NextResponse } from 'next/server'

// Українська без префікса (/news), англійська з /en (/en/news). Усередині все живе в app/[lang]/…
export function proxy(req: NextRequest) {
  const { pathname } = req.nextUrl
  if (pathname === '/uk' || pathname.startsWith('/uk/')) {
    const url = req.nextUrl.clone()
    url.pathname = pathname.slice(3) || '/'
    return NextResponse.redirect(url, 301)
  }
  if (pathname === '/en' || pathname.startsWith('/en/')) return NextResponse.next()
  const url = req.nextUrl.clone()
  url.pathname = `/uk${pathname === '/' ? '' : pathname}`
  return NextResponse.rewrite(url)
}

export const config = {
  matcher: ['/((?!api|admin|storage|_next|favicon|robots\\.txt|sitemap\\.xml|.*\\.[a-z0-9]{2,5}$).*)'],
}
