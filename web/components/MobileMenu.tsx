'use client'
import { useState } from 'react'
import type { MenuItem } from '@/lib/api'

export default function MobileMenu({ menu, label }: { menu: MenuItem[]; label: string }) {
  const [open, setOpen] = useState(false)
  return (
    <div className="mobmenu">
      <button className="burger" aria-expanded={open} aria-label={label} onClick={() => setOpen(!open)}>
        <span /><span /><span />
      </button>
      {open && (
        <nav className="mobmenu__panel">
          <ul>
            {menu.map((i) => (
              <li key={i.label}>
                <a href={i.url}>{i.label}</a>
                {!!i.children?.length && <ul>{i.children.map((c) => <li key={c.label}><a href={c.url}>{c.label}</a></li>)}</ul>}
              </li>
            ))}
          </ul>
        </nav>
      )}
    </div>
  )
}
