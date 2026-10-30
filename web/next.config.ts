import type { NextConfig } from 'next'

// Laravel (API, адмінка, файли) у розробці на :8000; на сервері /api, /admin і /storage віддає nginx
const backend = process.env.BACKEND_DEV_URL || 'http://127.0.0.1:8000'

const config: NextConfig = {
  output: 'standalone',
  poweredByHeader: false,
  images: { unoptimized: true },
  async rewrites() {
    if (process.env.NODE_ENV === 'production') return []
    return ['/api/:p*', '/storage/:p*', '/admin/:p*'].map((source) => ({ source, destination: `${backend}${source.replace('/:p*', '')}/:p*` }))
  },
}

export default config
