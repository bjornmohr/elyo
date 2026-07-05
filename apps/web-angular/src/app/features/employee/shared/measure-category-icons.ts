/**
 * Central icon + colour registry for measure categories. Every category the
 * backend can emit has its own line-icon and a matching tint pair (icon colour
 * + soft background), so any surface that lists measures/levers can render a
 * consistent, category-specific glyph via `categoryIcon(category)`.
 *
 * Icons are Lucide-style 24×24 line paths; `svg` holds the inner markup only
 * (host <svg> supplies viewBox/stroke). Keys match the backend category enum
 * (see CATEGORY_LABELS); `SLEEP` is levers-only, `DEFAULT` is the fallback.
 */
export interface CategoryIcon {
  /** Inner SVG markup — bound into a host <svg> via [innerHTML]. */
  svg: string;
  /** Stroke / text colour for the glyph and its badge. */
  color: string;
  /** Soft tint used behind the glyph and its badge. */
  bg: string;
}

export const MEASURE_CATEGORY_ICONS: Record<string, CategoryIcon> = {
  SLEEP: {
    svg: '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
    color: '#3b6fd4',
    bg: '#eef3ff',
  },
  MOBILITY: {
    svg: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
    color: '#0f766e',
    bg: '#e9f6f3',
  },
  STRENGTH: {
    svg: '<path d="m6.5 6.5 11 11"/><path d="m21 21-1-1"/><path d="m3 3 1 1"/><path d="m18 22 4-4"/><path d="m2 6 4-4"/><path d="m3 10 7-7"/><path d="m14 21 7-7"/>',
    color: '#0d9488',
    bg: '#e9f6f3',
  },
  BREATHING: {
    svg: '<path d="M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2"/><path d="M9.6 4.6A2 2 0 1 1 11 8H2"/><path d="M12.6 19.4A2 2 0 1 0 14 16H2"/>',
    color: '#c2620f',
    bg: '#fdf0e6',
  },
  MINDFULNESS: {
    svg: '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>',
    color: '#7c3aed',
    bg: '#f3eefc',
  },
  EDUCATION: {
    svg: '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
    color: '#4f46e5',
    bg: '#eef0fe',
  },
  REFLECTION: {
    svg: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    color: '#0891b2',
    bg: '#e7f6fa',
  },
  MIXED: {
    svg: '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
    color: '#0f766e',
    bg: '#e9f6f3',
  },
};

const DEFAULT_ICON: CategoryIcon = MEASURE_CATEGORY_ICONS['MIXED'];

/** Resolve a category to its icon, falling back to a neutral target glyph. */
export function categoryIcon(category: string | null | undefined): CategoryIcon {
  if (!category) return DEFAULT_ICON;
  return MEASURE_CATEGORY_ICONS[category.toUpperCase()] ?? DEFAULT_ICON;
}
