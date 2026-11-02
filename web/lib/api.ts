// Під час SSR ходимо до Laravel напряму (внутрішній nginx), у розробці на :8000
const INTERNAL = process.env.API_INTERNAL || 'http://127.0.0.1:8000/api'

export type Lang = 'uk' | 'en'

export class NotFound extends Error {}

export async function api<T = any>(path: string, params: Record<string, string | undefined> = {}, revalidate = 60): Promise<T> {
  const qs = new URLSearchParams()
  for (const [k, v] of Object.entries(params)) if (v !== undefined && v !== '') qs.set(k, v)
  const res = await fetch(`${INTERNAL}/v1/${path}${qs.size ? `?${qs}` : ''}`, {
    headers: { Accept: 'application/json' },
    next: { revalidate },
  })
  if (res.status === 404) throw new NotFound(path)
  if (!res.ok) throw new Error(`API ${path}: ${res.status}`)
  return res.json()
}

export type Script = { src?: string; code?: string; webflow?: boolean }

export type Layout = {
  settings: Record<string, string>
  header: string
  footer: string
  accessibility: string
  global_script: string
  head_styles: string[]
}

export type Rendered = {
  type: 'page' | 'blog' | 'orders' | 'reports'
  title: string
  meta: { title: string | null; description: string | null; image: string | null }
  wf_page: string | null
  html: string
  scripts: Script[]
  styles: string[]
}

export type SearchHit = { title: string; url: string; date: string | null; text: string | null }
