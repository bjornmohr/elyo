/**
 * Canonical 1–5 wellbeing scale (ELYO-102 §3.1 / B5, ADR-003 D3).
 *
 * Shared by the check-in form and the wellbeing displays so the same value
 * never renders as a different face or colour depending on the screen, and so
 * the input bounds have a single source of truth against the API contract.
 */
export const WELLBEING_SCALE_MIN = 1;
export const WELLBEING_SCALE_MAX = 5;

export function getScoreColor(score: number): string {
  if (score >= 4) return '#14b8a6';
  if (score >= 3) return '#4c8448';
  if (score >= 2) return '#d97706';
  return '#ef4444';
}

export function getMoodEmoji(mood: number): string {
  if (mood >= 5) return '🤩';
  if (mood >= 4) return '😊';
  if (mood >= 3) return '😐';
  if (mood >= 2) return '😟';
  return '😫';
}

export function getStressEmoji(stress: number): string {
  if (stress >= 5) return '💥';
  if (stress >= 4) return '😫';
  if (stress >= 3) return '😐';
  if (stress >= 2) return '😌';
  return '🧘';
}
