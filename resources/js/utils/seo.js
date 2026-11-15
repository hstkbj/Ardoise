/**
 * Met à jour titre, description, Open Graph, Twitter, canonique et robots à chaque navigation.
 * Les textes des pages publiques viennent du serveur (window.__ARDOISE__.seo, config/ardoise.php) ;
 * toute autre page (connexion, espaces de l'école) est en « noindex ».
 */

function setTag(selector, create, content) {
  if (content == null) return;
  let el = document.head.querySelector(selector);
  if (!el) {
    el = create();
    document.head.appendChild(el);
  }
  el.setAttribute(el.tagName === 'LINK' ? 'href' : 'content', content);
}

const meta = (attr, key) => () => {
  const el = document.createElement('meta');
  el.setAttribute(attr, key);
  return el;
};

export function applySeo(to) {
  const cfg = window.__ARDOISE__ || {};
  const site = cfg.seo?.siteName || 'Ardoise';
  const page = cfg.central ? cfg.seo?.pages?.[to.path] : null;
  const title = page?.title || (to.meta.title ? `${to.meta.title} · ${site}` : site);

  document.title = title;
  setTag('meta[name="robots"]', meta('name', 'robots'), page ? 'index, follow' : 'noindex, nofollow');

  if (!page) return;

  const url = `${window.location.origin}${to.path === '/' ? '/' : to.path}`;
  setTag('meta[name="description"]', meta('name', 'description'), page.description);
  setTag('meta[property="og:title"]', meta('property', 'og:title'), title);
  setTag('meta[property="og:description"]', meta('property', 'og:description'), page.description);
  setTag('meta[property="og:url"]', meta('property', 'og:url'), url);
  setTag('meta[name="twitter:title"]', meta('name', 'twitter:title'), title);
  setTag('meta[name="twitter:description"]', meta('name', 'twitter:description'), page.description);
  setTag('link[rel="canonical"]', () => Object.assign(document.createElement('link'), { rel: 'canonical' }), url);
}
