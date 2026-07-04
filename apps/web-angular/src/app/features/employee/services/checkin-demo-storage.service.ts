import { Injectable } from '@angular/core';

/**
 * Demo persistence for the adaptive check-in (Handoff 02): the whole run is
 * faked and stored ONLY in the browser. No API writes, no model changes.
 * Key format: elyo.demo.checkin.<yyyy-mm-dd> — never touches foreign keys.
 */
export type CheckinLocation = 'office' | 'home' | 'plant' | 'onroad';

export interface DemoCheckinSymptom {
  key: string;
  region: string;
  severity: number;
}

export interface DemoCheckinIllnessDetail {
  subs: string[];
  severity: number;
}

export type IllnessType = 'cold' | 'gi' | 'flu' | 'migraine' | 'allergy';

export interface DemoCheckin {
  date: string;
  location: CheckinLocation;
  mood: number;
  energy: number;
  stress: number;
  sleep: { hours: number; recovery: number } | null;
  symptoms: DemoCheckinSymptom[];
  /** sick flag plus at most one illness-type key holding the details. */
  illness: { sick: boolean } & Partial<Record<IllnessType, DemoCheckinIllnessDetail>>;
}

@Injectable({ providedIn: 'root' })
export class CheckinDemoStorageService {
  private key(date: string): string {
    return `elyo.demo.checkin.${date}`;
  }

  todayKey(): string {
    return new Date().toISOString().slice(0, 10);
  }

  save(checkin: DemoCheckin): void {
    try {
      localStorage.setItem(this.key(checkin.date), JSON.stringify(checkin));
    } catch {
      // localStorage unavailable — demo state is lost, nothing else breaks
    }
  }

  loadToday(): DemoCheckin | null {
    return this.load(this.todayKey());
  }

  load(date: string): DemoCheckin | null {
    try {
      const raw = localStorage.getItem(this.key(date));
      return raw ? (JSON.parse(raw) as DemoCheckin) : null;
    } catch {
      return null;
    }
  }

  todayCompleted(): boolean {
    return this.loadToday() !== null;
  }
}
