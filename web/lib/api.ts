// Під час SSR ходимо до Laravel напряму (внутрішній nginx :8081), у браузері через /api того ж домену
const INTERNAL = process.env.API_INTERNAL || 'http://127.0.0.1:8000/api'

export type Lang = 'uk' | 'en'
export const LANGS: Lang[] = ['uk', 'en']
export const prefix = (lang: Lang) => (lang === 'uk' ? '' : `/${lang}`)

export class NotFound extends Error {}

export async function api<T = any>(path: string, params: Record<string, string | number | undefined> = {}, revalidate = 60): Promise<T> {
  const qs = new URLSearchParams()
  for (const [k, v] of Object.entries(params)) if (v !== undefined && v !== '') qs.set(k, String(v))
  const res = await fetch(`${INTERNAL}/v1/${path}${qs.size ? `?${qs}` : ''}`, {
    headers: { Accept: 'application/json' },
    next: { revalidate },
  })
  if (res.status === 404) throw new NotFound(path)
  if (!res.ok) throw new Error(`API ${path}: ${res.status}`)
  return res.json()
}

export const t = (lang: Lang, uk: string, en: string) => (lang === 'en' ? en : uk)

export function formatDate(iso: string | null | undefined, lang: Lang) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString(lang === 'en' ? 'en-GB' : 'uk-UA', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Europe/Kyiv' })
}

export type MenuItem = { label: string; url: string; new_tab: boolean; children?: MenuItem[] }
export type Layout = { settings: Record<string, string>; menus: Record<string, MenuItem[]>; locales: Record<string, string> }
export type PostCard = { slug: string; url: string; title: string; excerpt: string | null; cover: string | null; date: string | null; category: { slug: string; name: string } | null }
