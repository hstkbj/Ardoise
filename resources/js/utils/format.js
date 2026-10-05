// Formatage partagé (fr-FR, FCFA). Aucune dépendance externe.

const numberFmt = new Intl.NumberFormat('fr-FR');

export const DAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

export function formatNumber(value) {
  if (value === null || value === undefined || value === '') return '—';
  return numberFmt.format(value);
}

export function formatMoney(value, currency = 'FCFA') {
  if (value === null || value === undefined || value === '') return '—';
  return `${numberFmt.format(value)} ${currency}`;
}

export function formatScore(value, digits = 2) {
  if (value === null || value === undefined || value === '') return '—';
  return Number(value).toLocaleString('fr-FR', { maximumFractionDigits: digits });
}

export function formatDate(value, options = { day: '2-digit', month: '2-digit', year: 'numeric' }) {
  if (!value) return '—';
  const d = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleDateString('fr-FR', options);
}

export function formatDateTime(value) {
  if (!value) return '—';
  const d = new Date(value);
  return `${formatDate(d)} ${d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`;
}

export function initials(name = '') {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase())
    .join('');
}

/** Lit une valeur imbriquée : get(obj, 'class.name') */
export function get(obj, path) {
  return path.split('.').reduce((acc, key) => (acc == null ? acc : acc[key]), obj);
}

/** Convertit une valeur de note saisie ("14,5") en nombre, ou null si vide. */
export function parseGrade(input) {
  if (input === null || input === undefined) return null;
  const s = String(input).trim().replace(',', '.');
  if (s === '') return null;
  const n = Number(s);
  return Number.isNaN(n) ? NaN : n;
}

export function today() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/** Ouvre un fichier servi par l'API (PDF, reçu, document) dans un nouvel onglet. */
export function openFile(path) {
  window.open(`${import.meta.env.VITE_API_URL || '/api/v1'}${path}`, '_blank', 'noopener');
}
