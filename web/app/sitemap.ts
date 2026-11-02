import type { MetadataRoute } from 'next'
import { api } from '@/lib/api'

export const dynamic = 'force-dynamic'

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const site = (process.env.SITE_URL || 'http://localhost:3000').replace(/\/$/, '')
  const res = await api<{ data: { url: string; updated_at: string | null }[] }>('sitemap', {}, 3600)
  return res.data.map((i) => ({ url: site + i.url, lastModified: i.updated_at ?? undefined }))
}
