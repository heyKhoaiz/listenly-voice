// 18px line icons for the sidebar, tiles and buttons. Decorative: always aria-hidden.
const PATHS: Record<string, string> = {
  home: 'M4 11l8-7 8 7M6 9.5V20h12V9.5',
  voices: 'M12 3a3 3 0 0 0-3 3v5a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3zM6 11a6 6 0 0 0 12 0M12 17v4',
  player: 'M4 5h16v14H4zM10 9.5v5l4-2.5z',
  page: 'M6 3h9l3 3v15H6zM9 12h6M9 16h6',
  help: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM9.6 9.4a2.5 2.5 0 0 1 4.8.9c0 1.7-2.4 2.2-2.4 3.7M12 17h.01',
  play: 'M8 5.5v13l10-6.5z',
  arrow: 'M5 12h14M13 6l6 6-6 6',
  external: 'M14 4h6v6M20 4l-9 9M18 14v6H4V6h6',
  chart: 'M4 20h16M7 16v-5M12 16V7M17 16v-8',
  brush: 'M14 4l6 6-8 8H6v-6zM12 6l6 6',
  globe: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM3 12h18M12 3c2.5 2.5 3.5 5.5 3.5 9s-1 6.5-3.5 9c-2.5-2.5-3.5-5.5-3.5-9s1-6.5 3.5-9z',
  cloud: 'M7 18h10a4 4 0 0 0 .5-8 6 6 0 0 0-11.5 2A3 3 0 0 0 7 18z',
  text: 'M5 6h14M5 10h14M5 14h9M5 18h6',
  podcast: 'M12 13a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM12 15v6M7.5 15.5a6 6 0 1 1 9 0',
  library: 'M5 4v16M9 4v16M13 5l4 15M19 4v16',
  building: 'M5 21V4h9v17M14 9h5v12M8 8h3M8 12h3M8 16h3M3 21h18',
}

export function Icon({ name, size = 18 }: { name: string; size?: number }) {
  return (
    <svg className="lv-icon" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">
      <path d={PATHS[name] ?? PATHS.page} />
    </svg>
  )
}
