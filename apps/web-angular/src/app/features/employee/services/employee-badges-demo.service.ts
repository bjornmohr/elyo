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
    iconKey: 'check',
    tone: 'teal',
    progressTarget: 1,
    benefit: 'Deine Startwerte sind die Basis für passende Empfehlungen.',
  },
  {
    id: 'first-checkin',
    title: 'Erster Check-in',
    description: 'Den ersten täglichen Check-in abgeschlossen.',
    category: 'STARTER',
    icon: '1',
    iconKey: 'checkcircle',
    tone: 'teal',
    progressTarget: 1,
    benefit: 'Check-ins machen deine Muster über die Zeit sichtbar.',
  },
  {
    id: 'first-measure',
    title: 'Erste Maßnahme',
    description: 'Die erste empfohlene Maßnahme gestartet.',
    category: 'STARTER',
    icon: '+',
    iconKey: 'target',
    tone: 'blue',
    progressTarget: 1,
    unit: 'Maßnahmen',
    benefit: 'Eine erste Maßnahme macht aus einer Empfehlung eine konkrete Routine.',
  },
  {
    id: 'seven-day-compass',
    title: '7-Tage-Kompass',
    description: '7 Tage in Folge eingecheckt.',
    category: 'STREAK',
    icon: '7',
    iconKey: 'compass',
    tone: 'amber',
    progressTarget: 7,
    unit: 'Tage',
    benefit: 'Regelmäßigkeit macht Veränderungen und Muster sichtbar.',
  },
  {
    id: 'sleep-series',
    title: 'Schlaf-Serie',
    description: '5 Tage Schlafroutine dokumentiert.',
    category: 'STREAK',
    icon: 'Z',
    iconKey: 'moon',
    tone: 'blue',
    progressTarget: 5,
    unit: 'Tage',
    benefit: 'Eine feste Schlafroutine kann Energie und Stimmung stabilisieren.',
    dashboardReason: 'Zahlt auf deine Routine ein',
  },
  {
    id: 'hydration-series',
    title: 'Hydration-Serie',
    description: '5 Tage Flüssigkeitsziel erreicht.',
    category: 'STREAK',
    icon: '~',
    iconKey: 'droplet',
    tone: 'teal',
    progressTarget: 5,
    unit: 'Tage',
    benefit: 'Genug zu trinken unterstützt Konzentration und Energie im Alltag.',
  },
  {
    id: 'mobility-starter',
    title: 'Mobilitätsstarter',
    description: '3 Mobilitätsübungen abgeschlossen.',
    category: 'QUEST',
    icon: 'M',
    iconKey: 'activity',
    tone: 'violet',
    progressTarget: 3,
    unit: 'Übungen',
    benefit: 'Kurze Bewegungseinheiten lösen Steifheit aus dem Arbeitsalltag.',
    measureRoute: '/employee/measures',
  },
  {
    id: 'stress-resilience-1',
    title: 'Stress-Resilienz I',
    description: '3 Atem- oder Entspannungsübungen abgeschlossen.',
    category: 'QUEST',
    icon: 'R',
    iconKey: 'wind',
    tone: 'teal',
    progressTarget: 3,
    unit: 'Übungen',
    benefit: 'Kurze Übungen helfen dir, in stressigen Phasen bewusst runterzufahren.',
    dashboardReason: 'Passt zu deiner aktuellen Woche',
    measureRoute: '/employee/measures',
  },
  {
    id: 'prevention-cycle',
    title: 'Präventionszyklus',
    description: 'Screening abgeschlossen und passende Maßnahmen begonnen.',
    category: 'PREVENTION',
    icon: 'P',
    iconKey: 'shield',
    tone: 'violet',
    progressTarget: 2,
    unit: 'Schritte',
    benefit: 'Screening, Check-ins und Maßnahmen greifen hier sichtbar ineinander.',
  },
  {
    id: 'smart-pause',
    title: 'Smart Pause',
    description: 'Schonmodus genutzt und eine sanfte Alternative gewählt.',
    category: 'RECOVERY',
    icon: 'II',
    iconKey: 'pause',
    tone: 'amber',
    progressTarget: 1,
    benefit: 'Kluge Pausen helfen, Belastung nicht einfach zu übergehen.',
  },
  {
    id: 'back-in-flow',
    title: 'Zurück im Flow',
    description: 'Nach einer Pause wieder mit Check-ins oder Maßnahmen eingestiegen.',
    category: 'RECOVERY',
    icon: '↻',
    iconKey: 'refresh',
    tone: 'blue',
    progressTarget: 1,
    benefit: 'Nach einer Pause wieder einzusteigen zählt mehr als perfekt zu bleiben.',
  },
  {
    id: 'marker-understander',
    title: 'Marker-Versteher',
    description: 'Laborwerte angesehen und auffällige Marker erklärt bekommen.',
    category: 'LAB',
    icon: 'L',
    iconKey: 'flask',
    tone: 'blue',
    progressTarget: 1,
    benefit: 'Du verstehst besser, was hinter deinen Werten steckt.',
  },
  {
    id: 'vitamin-d-routine',
    title: 'Vitamin-D-Routine',
    description: '3 Tageslicht-Routinen innerhalb von 14 Tagen abgeschlossen.',
    category: 'LAB',
    icon: 'D',
    iconKey: 'sun',
    tone: 'amber',
    progressTarget: 3,
    unit: 'Routinen',
    benefit: 'Tageslicht bewusst einzuplanen macht Versorgung und Tagesrhythmus greifbarer.',
  },
  {
    id: 'body-radar',
    title: 'Körperradar',
    description: 'Mehrere Check-ins mit Körpersignalen dokumentiert.',
    category: 'INSIGHT',
    icon: 'K',
    iconKey: 'target',
    tone: 'violet',
    progressTarget: 4,
    unit: 'Check-ins',
    benefit: 'Körpersignale früh wahrzunehmen hilft, rechtzeitig gegenzusteuern.',
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

// TODO: Hook the centered unlock celebration into a future badge-earned event.

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

  nextGoalBadge(streakDays?: number): EmployeeBadge | null {
    const badges = this.getBadges(streakDays);
    const inProgress = badges
      .filter(badge => badge.status === 'IN_PROGRESS')
      .sort((a, b) => b.progressPercent - a.progressPercent);

    return inProgress[0]
      ?? badges.find(badge => badge.status === 'EARNED')
      ?? badges.find(badge => badge.status === 'LOCKED')
      ?? null;
  }

  dashboardFeaturedBadge(streakDays?: number): EmployeeBadge | null {
    const badge = this.nextGoalBadge(streakDays);
    if (!badge) return null;

    return {
      ...badge,
      dashboardReason: this.dashboardReason(badge, streakDays),
    };
  }

  dashboardSecondaryBadges(streakDays?: number, limit = 2): EmployeeBadge[] {
    const featured = this.dashboardFeaturedBadge(streakDays);

    return this.getBadges(streakDays)
      .filter(badge => badge.status === 'IN_PROGRESS' && badge.id !== featured?.id)
      .sort((a, b) => b.progressPercent - a.progressPercent)
      .slice(0, limit)
      .map(badge => ({
        ...badge,
        dashboardReason: badge.dashboardReason ?? this.secondaryReason(badge),
      }));
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

  private dashboardReason(badge: EmployeeBadge, streakDays?: number): string {
    if (badge.id === 'seven-day-compass') {
      return `Deine ${streakDays ?? badge.progressCurrent}-Tage-Serie läuft — heute einchecken sichert sie.`;
    }

    return badge.dashboardReason ?? 'Fast geschafft';
  }

  private secondaryReason(badge: EmployeeBadge): string {
    const remaining = Math.max(0, badge.progressTarget - badge.progressCurrent);
    if (remaining > 0 && badge.unit) {
      return `Noch ${remaining} ${badge.unit} offen`;
    }

    if (badge.progressPercent >= 60) return 'Fast geschafft';

    return 'Zahlt auf deine Routine ein';
  }
}
