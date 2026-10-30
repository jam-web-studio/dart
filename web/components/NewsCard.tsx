import { formatDate, type Lang, type PostCard } from '@/lib/api'

export default function NewsCard({ post, lang }: { post: PostCard; lang: Lang }) {
  return (
    <article className="card">
      <a href={post.url} className="card__img">{post.cover ? <img src={post.cover} alt="" loading="lazy" /> : <span className="card__ph" />}</a>
      <div className="card__body">
        <div className="card__meta">
          {post.date && <time dateTime={post.date}>{formatDate(post.date, lang)}</time>}
          {post.category && <span>{post.category.name}</span>}
        </div>
        <h3><a href={post.url}>{post.title}</a></h3>
        {post.excerpt && <p>{post.excerpt}</p>}
      </div>
    </article>
  )
}
