import { CheckinLocation, DemoCheckin } from '../../services/checkin-demo-storage.service';

/**
 * Shared state shape and flow rules for both check-in variants (2a stepper,
 * 2c conversational). One data model, two presentations.
 */
export interface CheckinDraft {
  location: CheckinLocation | null;
  mood: number | null;
  energy: number | null;
  sleepWanted: boolean;
  sleepHours: number;
  sleepRecovery: number | null;
  stress: number | null;
  symptoms: Record<string, { region: string; severity: number }>;
  sick: boolean | null;
  illnessType: 'cold' | 'gi' | null;
  illnessSubs: string[];
  illnessSeverity: number | null;
}

export function emptyDraft(): CheckinDraft {
  return {
    location: null,
    mood: null,
    energy: null,
    sleepWanted: false,
    sleepHours: 7,
    sleepRecovery: null,
    stress: null,
    symptoms: {},
    sick: null,
    illnessType: null,
    illnessSubs: [],
    illnessSeverity: null,
  };
}

/** Adaptive rule: low energy pulls in the sleep questions automatically. */
export function sleepBranchActive(draft: CheckinDraft): boolean {
  return draft.sleepWanted || (draft.energy !== null && draft.energy <= 2) || draft.symptoms['fatigue'] !== undefined;
}

export const LOCATIONS: Array<{ value: CheckinLocation; label: string; icon: string }> = [
  { value: 'office', label: 'Büro', icon: '🏢' },
  { value: 'home', label: 'Home-Office', icon: '🏠' },
  { value: 'plant', label: 'Werk', icon: '🏭' },
  { value: 'onroad', label: 'Unterwegs', icon: '🚗' },
];

export const MOOD_OPTIONS: Array<{ value: number; label: string; emoji: string }> = [
  { value: 1, label: 'Sehr schlecht', emoji: '😫' },
  { value: 2, label: 'Schlecht', emoji: '😟' },
  { value: 3, label: 'Geht so', emoji: '😐' },
  { value: 4, label: 'Gut', emoji: '😊' },
  { value: 5, label: 'Sehr gut', emoji: '🤩' },
];

/** "Zuletzt häufig" first — ordering matters for the adaptive UI. */
export const FREQUENT_SYMPTOMS: Array<{ key: string; label: string; region: string; pain: boolean }> = [
  { key: 'neck', label: 'Nackenschmerzen', region: 'Nacken', pain: true },
  { key: 'fatigue', label: 'Müdigkeit', region: 'Allgemein', pain: false },
  { key: 'headache', label: 'Kopfschmerzen', region: 'Kopf', pain: true },
];

export const OTHER_SYMPTOMS: Array<{ key: string; label: string; region: string; pain: boolean }> = [
  { key: 'back', label: 'Rückenschmerzen', region: 'Rücken', pain: true },
  { key: 'shoulder', label: 'Schulterschmerzen', region: 'Schulter', pain: true },
  { key: 'eyes', label: 'Augenbelastung', region: 'Augen', pain: false },
  { key: 'tension', label: 'Innere Unruhe', region: 'Allgemein', pain: false },
];

export const PAIN_REGIONS = ['Nacken', 'Schulter', 'Rücken', 'Kopf', 'Arme', 'Beine'];

export const COLD_SUBS = ['Halsschmerzen', 'Schnupfen', 'Husten', 'Fieber', 'Gliederschmerzen'];
export const GI_SUBS = ['Übelkeit', 'Bauchschmerzen', 'Durchfall', 'Appetitlosigkeit'];

export function draftComplete(draft: CheckinDraft): boolean {
  return draft.location !== null && draft.mood !== null && draft.energy !== null && draft.stress !== null && draft.sick !== null;
}

export function toDemoCheckin(draft: CheckinDraft, date: string): DemoCheckin {
  const illness: DemoCheckin['illness'] = { sick: draft.sick === true };
  if (draft.sick && draft.illnessType && draft.illnessSubs.length > 0) {
    illness[draft.illnessType] = {
      subs: draft.illnessSubs,
      severity: draft.illnessSeverity ?? 3,
    };
  }

  return {
    date,
    location: draft.location ?? 'office',
    mood: draft.mood ?? 3,
    energy: draft.energy ?? 3,
    stress: draft.stress ?? 3,
    sleep: sleepBranchActive(draft)
      ? { hours: draft.sleepHours, recovery: draft.sleepRecovery ?? 3 }
      : null,
    symptoms: Object.entries(draft.symptoms).map(([key, value]) => ({
      key,
      region: value.region,
      severity: value.severity,
    })),
    illness,
  };
}
