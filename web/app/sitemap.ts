import type { MetadataRoute } from 'next'
import { api } from '@/lib/api'

export const dynamic = 'force-dynamic'

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const site = process.env.SITE_URL || 'http://localhost:3000'
  const res = await api<{ pages: { url: string; updated_at: string }[]; posts: { url: string; updated_at: string }[] }>('sitemap', {}, 3600)
  return [{ url: `${site}/` }, { url: `${site}/news` }, ...res.pages, ...res.posts].map((i) => ({ url: i.url.startsWith('http') ? i.url : site + i.url, lastModified: 'updated_at' in i ? i.updated_at : undefined }))
}
