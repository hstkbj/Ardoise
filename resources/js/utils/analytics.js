/**
 * Mesure d'audience des pages PUBLIQUES (accueil, fonctionnalités, tarifs, contact, guide, pages légales).
 * Jamais dans les espaces écoles, enseignants ou superadmin, ni sur les adresses des écoles :
 * seuls les chemins listés dans config/ardoise.php › seo.pages sont comptés.
 *
 * Fournisseur choisi côté serveur (ANALYTICS_PROVIDER) :
 *  - plausible, umami : sans cookie, chargés sans consentement ;
 *  - ga4 : cookies, chargé uniquement après « Accepter » dans le bandeau.
 * Le signal « Do Not Track » du navigateur est respecté.
 */
import { watch } from 'vue';
import { useCookieConsent } from '@/composables/useCookieConsent';

const NEEDS_CONSENT = { ga4: true, plausible: false, umami: false };
const SAFE_ID = /^[A-Za-z0-9._\-]{1,80}$/;

let scriptState = 'idle'; // idle | loading | ready | failed
let pendingPath = null;
let consent = null;

const config = () => window.__ARDOISE__?.analytics ?? null;

function isPublicPage(path) {
  const app = window.__ARDOISE__;
  return Boolean(app?.central && app.seo?.pages?.[path]);
}

function allowed(conf) {
  if (!conf || !SAFE_ID.test(conf.id)) return false;
  if (navigator.doNotTrack === '1' || window.doNotTrack === '1') return false;
  return !NEEDS_CONSENT[conf.provider] || Boolean(consent?.allowsOptional.value);
}

function injectScript(src, attrs = {}) {
  return new Promise((resolve, reject) => {
    const el = document.createElement('script');
    el.async = true;
    el.src = src;
    Object.entries(attrs).forEach(([k, v]) => el.setAttribute(k, v));
    el.onload = resolve;
    el.onerror = reject;
    document.head.appendChild(el);
  });
}

async function load(conf) {
  scriptState = 'loading';
  try {
    if (conf.provider === 'plausible') {
      window.plausible = window.plausible || function () { (window.plausible.q = window.plausible.q || []).push(arguments); };
      await injectScript(conf.src || 'https://plausible.io/js/script.manual.js', { defer: '', 'data-domain': conf.id });
    } else if (conf.provider === 'umami') {
      await injectScript(conf.src || 'https://cloud.umami.is/script.js', { defer: '', 'data-website-id': conf.id, 'data-auto-track': 'false' });
    } else if (conf.provider === 'ga4') {
      window.dataLayer = window.dataLayer || [];
      window.gtag = function () { window.dataLayer.push(arguments); };
      window.gtag('js', new Date());
      window.gtag('config', conf.id, { send_page_view: false, anonymize_ip: true });
      await injectScript(`${conf.src || 'https://www.googletagmanager.com/gtag/js'}?id=${encodeURIComponent(conf.id)}`);
    }
    scriptState = 'ready';
  } catch {
    scriptState = 'failed'; // bloqueur de publicités, hors ligne : on n'insiste pas
  }
}

function send(conf, path) {
  const url = `${window.location.origin}${path}`;
  if (conf.provider === 'plausible') window.plausible?.('pageview', { u: url });
  else if (conf.provider === 'umami') window.umami?.track((props) => ({ ...props, url: path }));
  else if (conf.provider === 'ga4') window.gtag?.('event', 'page_view', { page_path: path, page_location: url, page_title: document.title });
}

async function flush() {
  const conf = config();
  if (!pendingPath || !allowed(conf) || scriptState === 'failed' || scriptState === 'loading') return;
  if (scriptState === 'idle') {
    await load(conf);
    if (scriptState !== 'ready') return;
  }
  const path = pendingPath;
  pendingPath = null;
  send(conf, path);
}

/** À appeler à chaque navigation (routeur). */
export function trackPageView(to) {
  if (!config() || !isPublicPage(to.path)) {
    pendingPath = null;
    return;
  }
  pendingPath = to.path;
  flush();
}

/** À appeler une fois au démarrage : relance la mesure quand le visiteur accepte les cookies. */
export function initAnalytics() {
  if (!config()) return;
  consent = useCookieConsent();
  watch(consent.allowsOptional, () => flush());
}
