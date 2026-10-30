import type { Metadata } from 'next'
import { notFound } from 'next/navigation'
import { api, LANGS, type Lang, type Layout } from '@/lib/api'
import Header from '@/components/Header'
import Footer from '@/components/Footer'

const SITE = process.env.SITE_URL || 'http://localhost:3000'

export async function generateMetadata({ params }: { params: Promise<{ lang: Lang }> }): Promise<Metadata> {
  const { lang } = await params
  const { settings } = await api<Layout>('layout', { locale: lang }, 300)
  const name = (lang === 'en' ? settings.site_name_en : settings.site_name) || settings.site_name
  return {
    metadataBase: new URL(SITE),
    title: { default: name, template: `%s | ${name}` },
    robots: process.env.INDEXABLE === 'true' ? undefined : { index: false, follow: false },
    icons: { icon: '/favicon.svg' },
  }
}

export default async function LangLayout({ children, params }: { children: React.ReactNode; params: Promise<{ lang: string }> }) {
  const { lang } = await params
  if (!LANGS.includes(lang as Lang)) notFound()
  const layout = await api<Layout>('layout', { locale: lang }, 300)
  return (
    <html lang={lang}>
      <body>
        <Header lang={lang as Lang} layout={layout} />
        <main id="main">{children}</main>
        <Footer lang={lang as Lang} layout={layout} />
      </body>
    </html>
  )
}
