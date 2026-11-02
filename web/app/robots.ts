import type { MetadataRoute } from 'next'

export default function robots(): MetadataRoute.Robots {
  const site = process.env.SITE_URL || 'http://localhost:3000'
  // поки сайт на тестовому домені, пошуковики його не індексують
  if (process.env.INDEXABLE !== 'true') return { rules: { userAgent: '*', disallow: '/' } }
  return { rules: { userAgent: '*', allow: '/', disallow: ['/admin', '/search'] }, sitemap: `${site}/sitemap.xml` }
}
