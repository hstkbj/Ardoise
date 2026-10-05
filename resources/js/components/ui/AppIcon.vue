<script setup>
/** Icônes au trait (24×24), définies une fois pour tout le projet. */
const props = defineProps({
  name: { type: String, required: true },
  strokeWidth: { type: [Number, String], default: 1.8 },
});

const PATHS = {
  dashboard: 'M3 3h7v9H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 16h7v5H3z',
  building: 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6',
  calendar: 'M3 5h18v16H3zM3 10h18M8 3v4M16 3v4',
  layers: 'M4 4h16v6H4zM4 14h16v6H4z',
  users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
  home: 'M3 11l9-7 9 7v10H3zM9 21v-6h6v6',
  user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8M4 21v-1a7 7 0 0 1 16 0v1',
  book: 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5zM4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5',
  clipboard: 'M9 3h6v4H9zM15 5h4v16H5V5h4M9 12h6M9 16h4',
  pencil: 'M4 20h4L19 9l-4-4L4 16zM13 7l4 4',
  file: 'M6 2h9l5 5v15H6zM14 2v6h6M9 13h6M9 17h6',
  'check-circle': 'M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0M8 12l3 3 5-6',
  clock: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20M12 6v6l4 2',
  list: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
  megaphone: 'M3 11v2a1 1 0 0 0 1 1h3l6 5V5L7 10H4a1 1 0 0 0-1 1M17 8a5 5 0 0 1 0 8',
  bell: 'M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21h4',
  wallet: 'M2 6h20v12H2zM2 10h20M6 15h4',
  tag: 'M3 3h8l10 10-8 8L3 11zM7.5 7.5h.01',
  folder: 'M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
  shield: 'M12 2l8 3v6c0 5-3.5 9-8 11-4.5-2-8-6-8-11V5z',
  key: 'M15 7a4 4 0 1 1-4 4M11 11l-8 8v2h3l1-1v-2h2v-2h2l1.5-1.5',
  help: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01',
  sliders: 'M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6',
  chart: 'M3 3v18h18M7 15v3M12 10v8M17 6v12',
  activity: 'M22 12h-4l-3 9L9 3l-3 9H2',
  refresh: 'M21 12a9 9 0 1 1-3-6.7L21 8M21 3v5h-5',
  search: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16M21 21l-4.3-4.3',
  menu: 'M4 7h16M4 12h16M4 17h16',
  x: 'M6 6l12 12M18 6L6 18',
  check: 'M5 12l5 5L20 7',
  'chevron-down': 'M7 10l5 5 5-5',
  'chevron-right': 'M9 6l6 6-6 6',
  'chevron-left': 'M15 6l-6 6 6 6',
  'arrow-right': 'M5 12h14M13 6l6 6-6 6',
  'arrow-up': 'M12 19V5M6 11l6-6 6 6',
  'arrow-down': 'M12 5v14M6 13l6 6 6-6',
  plus: 'M12 5v14M5 12h14',
  more: 'M12 6h.01M12 12h.01M12 18h.01',
  eye: 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6',
  edit: 'M4 20h4L19 9l-4-4L4 16z',
  trash: 'M3 6h18M8 6V4h8v2M6 6l1 15h10l1-15',
  download: 'M12 3v12M6 11l6 6 6-6M4 21h16',
  upload: 'M12 21V9M6 13l6-6 6 6M4 3h16',
  printer: 'M6 9V3h12v6M6 18H4v-7h16v7h-2M8 14h8v7H8z',
  send: 'M22 2L11 13M22 2l-7 20-4-9-9-4z',
  lock: 'M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4',
  unlock: 'M5 11h14v10H5zM8 11V7a4 4 0 0 1 7.5-2',
  filter: 'M3 5h18l-7 8v6l-4 2v-8z',
  alert: 'M12 9v4M12 17h.01M10.3 3.9L2 18a2 2 0 0 0 1.7 3h16.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0',
  info: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20M12 16v-4M12 8h.01',
  logout: 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9',
  mail: 'M3 5h18v14H3zM3 6l9 7 9-7',
  phone: 'M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z',
  map: 'M12 21s-7-6.5-7-12a7 7 0 0 1 14 0c0 5.5-7 12-7 12zM12 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4',
  smartphone: 'M7 2h10v20H7zM11 18h2',
  grid: 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
  copy: 'M9 9h11v11H9zM5 15H4V4h11v1',
};

const d = () => PATHS[props.name] || PATHS.info;
</script>

<template>
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke-width="strokeWidth" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <path :d="d()" />
  </svg>
</template>
