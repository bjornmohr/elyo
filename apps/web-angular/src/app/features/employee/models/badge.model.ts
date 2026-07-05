export type BadgeCategory =
  | 'STARTER'
  | 'STREAK'
  | 'QUEST'
  | 'INSIGHT'
  | 'RECOVERY'
  | 'PREVENTION'
  | 'LAB';

export type BadgeStatus = 'LOCKED' | 'IN_PROGRESS' | 'EARNED';

export type BadgeTone = 'teal' | 'blue' | 'amber' | 'violet' | 'slate';

export interface BadgeDefinition {
  id: string;
  title: string;
  description: string;
  category: BadgeCategory;
  icon: string;
  tone: BadgeTone;
  progressTarget: number;
}

export interface BadgeProgress {
  badgeId: string;
  status: BadgeStatus;
  progressCurrent: number;
  progressTarget: number;
  earnedAt?: string;
}

export interface EmployeeBadge extends BadgeDefinition, Omit<BadgeProgress, 'badgeId'> {
  progressPercent: number;
}
