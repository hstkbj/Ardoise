// Export CSV des listes : s'ouvre directement dans Excel (UTF-8 avec BOM, séparateur « ; »).
import { optionLabel } from '@/config/options';
import { STATUSES } from '@/config/statuses';
import { formatDate, formatDateTime, get } from './format';

/** Libellé de la colonne « détail » d'une colonne de type person (sub). */
const SUB_LABELS = {
  matricule: 'Matricule',
  class_name: 'Classe',
  sub: 'Détail',
  phone: 'Téléphone',
  email: 'E-mail',
  domain: 'Domaine',
  city: 'Ville',
  tenant_name: 'Tenant',
  user_email: 'E-mail',
  ip_address: 'Adresse IP',
};

/** Valeur brute lisible dans un tableur (nombres sans espace, dates JJ/MM/AAAA, statuts en clair). */
function cell(col, row) {
  const v = get(row, col.key);
  if (v === null || v === undefined || v === '') return '';
  switch (col.type) {
    case 'money':
    case 'number':
      return String(v);
    case 'grade':
      return String(v).replace('.', ',');
    case 'date':
      return formatDate(v);
    case 'datetime':
      return formatDateTime(v);
    case 'status':
      return STATUSES[v]?.label ?? v;
    case 'option':
      return optionLabel(col.optionsKey, v);
    case 'code':
      return `••••-${v}`;
    default:
      return Array.isArray(v) ? v.join(', ') : typeof v === 'object' ? (v.name ?? v.label ?? '') : String(v);
  }
}

function escape(value) {
  const s = String(value ?? '');
  return /[";\n\r]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
}

/** Colonnes affichées → lignes CSV (une colonne supplémentaire pour les sous-titres « person »). */
export function toCsv(columns, rows) {
  const cols = columns.flatMap((c) => (c.type === 'person' && c.sub ? [c, { key: c.sub, label: SUB_LABELS[c.sub] || 'Détail' }] : [c]));
  const lines = [cols.map((c) => escape(c.label)).join(';'), ...rows.map((r) => cols.map((c) => escape(cell(c, r))).join(';'))];
  return `﻿${lines.join('\r\n')}\r\n`;
}

export function downloadCsv(filename, content) {
  const url = URL.createObjectURL(new Blob([content], { type: 'text/csv;charset=utf-8' }));
  Object.assign(document.createElement('a'), { href: url, download: filename }).click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
