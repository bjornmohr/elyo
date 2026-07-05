import { Injectable } from '@angular/core';
import { BadgeCategory, BadgeDefinition, BadgeProgress, EmployeeBadge } from '../models/badge.model';

export const BADGE_CATEGORY_LABELS: Record<BadgeCategory, string> = {
  STARTER: 'Starter',
  STREAK: 'Routine',
  QUEST: 'Quest',
  INSIGHT: 'Reflexion',
  RECOVERY: 'Erholung',
  PREVENTION: 'Prävention',
  LAB: 'Labor',
};

const BADGE_DEFINITIONS: BadgeDefinition[] = [
  {
    id: 'baseline-set',
    title: 'Baseline gesetzt',
    description: 'Erstes Screening abgeschlossen.',
    category: 'STARTER',
    icon: '✓',
    tone: 'teal',
    progressTarget: 1,
  },
  {
    id: 'first-checkin',
    title: 'Erster Check-in',
    description: 'Den ersten täglichen Check-in abgeschlossen.',
    category: 'STARTER',
    icon: '1',
    tone: 'teal',
    progressTarget: 1,
  },
  {
    id: 'first-measure',
    title: 'Erste Maßnahme',
    description: 'Die erste empfohlene Maßnahme gestartet.',
    category: 'STARTER',
    icon: '+',
    tone: 'blue',
    progressTarget: 1,
  },
  {
    id: 'seven-day-compass',
    title: '7-Tage-Kompass',
    description: '7 Tage in Folge eingecheckt.',
    category: 'STREAK',
    icon: '7',
    tone: 'amber',
    progressTarget: 7,
  },
  {
    id: 'sleep-series',
    title: 'Schlaf-Serie',
    description: '5 Tage Schlafroutine dokumentiert.',
    category: 'STREAK',
    icon: 'Z',
    tone: 'blue',
    progressTarget: 5,
  },
  {
    id: 'hydration-series',
    title: 'Hydration-Serie',
    description: '5 Tage Flüssigkeitsziel erreicht.',
    category: 'STREAK',
    icon: '~',
    tone: 'teal',
    progressTarget: 5,
  },
  {
    id: 'mobility-starter',
    title: 'Mobilitätsstarter',
    description: '3 Mobilitätsübungen abgeschlossen.',
    category: 'QUEST',
    icon: 'M',
    tone: 'violet',
    progressTarget: 3,
  },
  {
    id: 'stress-resilience-1',
    title: 'Stress-Resilienz I',
    description: '3 Atem- oder Entspannungsübungen abgeschlossen.',
    category: 'QUEST',
    icon: 'R',
    tone: 'teal',
    progressTarget: 3,
  },
  {
    id: 'prevention-cycle',
    title: 'Präventionszyklus',
    description: 'Screening abgeschlossen und passende Maßnahmen begonnen.',
    category: 'PREVENTION',
    icon: 'P',
    tone: 'violet',
    progressTarget: 2,
  },
  {
    id: 'smart-pause',
    title: 'Smart Pause',
    description: 'Schonmodus genutzt und eine sanfte Alternative gewählt.',
    category: 'RECOVERY',
    icon: 'II',
    tone: 'amber',
    progressTarget: 1,
  },
  {
    id: 'back-in-flow',
    title: 'Zurück im Flow',
    description: 'Nach einer Pause wieder mit Check-ins oder Maßnahmen eingestiegen.',
    category: 'RECOVERY',
    icon: '↻',
    tone: 'blue',
    progressTarget: 1,
  },
  {
    id: 'marker-understander',
    title: 'Marker-Versteher',
    description: 'Laborwerte angesehen und auffällige Marker erklärt bekommen.',
    category: 'LAB',
    icon: 'L',
    tone: 'slate',
    progressTarget: 1,
  },
  {
    id: 'vitamin-d-routine',
    title: 'Vitamin-D-Routine',
    description: '3 Tageslicht-Routinen innerhalb von 14 Tagen abgeschlossen.',
    category: 'LAB',
    icon: 'D',
    tone: 'amber',
    progressTarget: 3,
  },
  {
    id: 'body-radar',
    title: 'Körperradar',
    description: 'Mehrere Check-ins mit Körpersignalen dokumentiert.',
    category: 'INSIGHT',
    icon: 'K',
    tone: 'violet',
    progressTarget: 4,
  },
];

const DEMO_PROGRESS: BadgeProgress[] = [
  { badgeId: 'baseline-set', status: 'EARNED', progressCurrent: 1, progressTarget: 1, earnedAt: '2026-06-10' },
  { badgeId: 'first-checkin', status: 'EARNED', progressCurrent: 1, progressTarget: 1, earnedAt: '2026-06-12' },
  { badgeId: 'marker-understander', status: 'EARNED', progressCurrent: 1, progressTarget: 1, earnedAt: '2026-06-20' },
  { badgeId: 'seven-day-compass', status: 'IN_PROGRESS', progressCurrent: 5, progressTarget: 7 },
  { badgeId: 'sleep-series', status: 'IN_PROGRESS', progressCurrent: 3, progressTarget: 5 },
  { badgeId: 'vitamin-d-routine', status: 'IN_PROGRESS', progressCurrent: 1, progressTarget: 3 },
  { badgeId: 'stress-resilience-1', status: 'IN_PROGRESS', progressCurrent: 2, progressTarget: 3 },
  { badgeId: 'first-measure', status: 'IN_PROGRESS', progressCurrent: 0, progressTarget: 1 },
  { badgeId: 'hydration-series', status: 'IN_PROGRESS', progressCurrent: 2, progressTarget: 5 },
  { badgeId: 'mobility-starter', status: 'IN_PROGRESS', progressCurrent: 1, progressTarget: 3 },
  { badgeId: 'body-radar', status: 'IN_PROGRESS', progressCurrent: 2, progressTarget: 4 },
  { badgeId: 'prevention-cycle', status: 'LOCKED', progressCurrent: 0, progressTarget: 2 },
  { badgeId: 'smart-pause', status: 'LOCKED', progressCurrent: 0, progressTarget: 1 },
  { badgeId: 'back-in-flow', status: 'LOCKED', progressCurrent: 0, progressTarget: 1 },
];

export const FUTURE_BADGE_EVENTS = [
  'CHECK_IN_COMPLETED',
  'CHECK_IN_NOTE_ADDED',
  'MEASURE_STARTED',
  'MEASURE_COMPLETED',
  'SCREENING_COMPLETED',
  'SAFE_MODE_ACTIVATED',
  'SAFE_MODE_MEASURE_COMPLETED',
  'LAB_VALUES_VIEWED',
  'LAB_MARKER_ACTION_COMPLETED',
] as const;

@Injectable({ providedIn: 'root' })
export class EmployeeBadgesDemoService {
  /**
   * TODO: Replace this local demo progress with user-level badge events
   * (see FUTURE_BADGE_EVENTS) from check-ins, screening completion, measure
   * participation, recovery-mode usage, and optional lab-marker interactions.
   * Do not expose individual badge details to Company/Admin views without an
   * explicit privacy design.
   */
  getBadges(streakDays?: number): EmployeeBadge[] {
    return BADGE_DEFINITIONS.map(definition => {
      const progress = DEMO_PROGRESS.find(item => item.badgeId === definition.id) ?? {
        badgeId: definition.id,
        status: 'LOCKED',
        progressCurrent: 0,
        progressTarget: definition.progressTarget,
      };
      const progressCurrent = definition.id === 'seven-day-compass' && typeof streakDays === 'number'
        ? Math.max(progress.progressCurrent, Math.min(streakDays, progress.progressTarget))
        : progress.progressCurrent;
      const status = progress.status === 'EARNED' || progressCurrent <= 0
        ? progress.status
        : progressCurrent >= progress.progressTarget
          ? 'EARNED'
          : progress.status;

      return {
        ...definition,
        status,
        progressCurrent,
        progressTarget: progress.progressTarget,
        earnedAt: progress.earnedAt,
        progressPercent: Math.round((progressCurrent / progress.progressTarget) * 100),
      };
    });
  }

  activeBadges(streakDays?: number, limit = 3): EmployeeBadge[] {
    return this.getBadges(streakDays)
      .filter(badge => badge.status === 'IN_PROGRESS')
      .sort((a, b) => b.progressPercent - a.progressPercent)
      .slice(0, limit);
  }

  earnedBadges(limit = 3): EmployeeBadge[] {
    return this.getBadges()
      .filter(badge => badge.status === 'EARNED')
      .sort((a, b) => (b.earnedAt ?? '').localeCompare(a.earnedAt ?? ''))
      .slice(0, limit);
  }

  questForMeasure(category: string | null): EmployeeBadge | null {
    const normalized = category?.toUpperCase() ?? '';
    if (normalized === 'BREATHING' || normalized === 'MINDFULNESS') {
      return this.getBadges().find(badge => badge.id === 'stress-resilience-1') ?? null;
    }

    if (normalized === 'MOBILITY') {
      return this.getBadges().find(badge => badge.id === 'mobility-starter') ?? null;
    }

    return null;
  }
}
