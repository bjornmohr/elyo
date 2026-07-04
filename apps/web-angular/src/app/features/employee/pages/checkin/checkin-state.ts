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
  illnessType: IllnessTypeKey | null;
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

export interface SymptomDefinition {
  key: string;
  label: string;
  pain: boolean;
  /** Symptom-specific sub-regions for the "Wo genau?" follow-up; first entry is the default. */
  regions: string[];
}

/** "Zuletzt häufig" first — ordering matters for the adaptive UI. */
export const FREQUENT_SYMPTOMS: SymptomDefinition[] = [
  { key: 'neck', label: 'Nackenschmerzen', pain: true, regions: ['Seitlich links', 'Seitlich rechts', 'Nacken-Schulter-Übergang', 'Ansatz Hinterkopf'] },
  { key: 'fatigue', label: 'Müdigkeit', pain: false, regions: ['Allgemein'] },
  { key: 'headache', label: 'Kopfschmerzen', pain: true, regions: ['Stirn', 'Schläfen', 'Hinterkopf', 'Ganzer Kopf'] },
];

export const OTHER_SYMPTOMS: SymptomDefinition[] = [
  { key: 'back', label: 'Rückenschmerzen', pain: true, regions: ['Unterer Rücken', 'Mittlerer Rücken', 'Oberer Rücken'] },
  { key: 'shoulder', label: 'Schulterschmerzen', pain: true, regions: ['Links', 'Rechts', 'Beidseitig', 'Schulterblatt'] },
  { key: 'eyes', label: 'Augenbelastung', pain: false, regions: ['Augen'] },
  { key: 'tension', label: 'Innere Unruhe', pain: false, regions: ['Allgemein'] },
];

export type IllnessTypeKey = 'cold' | 'gi' | 'flu' | 'migraine' | 'allergy';

export const ILLNESS_TYPES: Array<{ key: IllnessTypeKey; label: string; subs: string[] }> = [
  { key: 'cold', label: 'Erkältung', subs: ['Halsschmerzen', 'Schnupfen', 'Husten', 'Fieber', 'Gliederschmerzen'] },
  { key: 'gi', label: 'Magen-Darm', subs: ['Übelkeit', 'Bauchschmerzen', 'Durchfall', 'Appetitlosigkeit'] },
  { key: 'flu', label: 'Grippeartig', subs: ['Fieber', 'Schüttelfrost', 'Gliederschmerzen', 'Abgeschlagenheit'] },
  { key: 'migraine', label: 'Migräne / starke Kopfschmerzen', subs: ['Pochender Kopfschmerz', 'Lichtempfindlichkeit', 'Übelkeit', 'Sehstörungen'] },
  { key: 'allergy', label: 'Allergie', subs: ['Niesen', 'Juckende Augen', 'Hautausschlag', 'Atembeschwerden'] },
];

export function illnessSubsFor(type: IllnessTypeKey | null): string[] {
  return ILLNESS_TYPES.find(t => t.key === type)?.subs ?? [];
}

export function illnessLabelFor(type: IllnessTypeKey | null): string {
  return ILLNESS_TYPES.find(t => t.key === type)?.label ?? '';
}

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
