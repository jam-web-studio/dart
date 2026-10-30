export default function Pager({ page, pages, href }: { page: number; pages: number; href: (p: number) => string }) {
  if (pages <= 1) return null
  const nums = Array.from({ length: pages }, (_, i) => i + 1).filter((n) => n === 1 || n === pages || Math.abs(n - page) <= 2)
  return (
    <nav className="pager">
      {nums.map((n, i) => (
        <span key={n}>
          {i > 0 && n - nums[i - 1] > 1 && <span className="pager__gap">…</span>}
          {n === page ? <span className="pager__cur">{n}</span> : <a href={href(n)}>{n}</a>}
        </span>
      ))}
    </nav>
  )
}
