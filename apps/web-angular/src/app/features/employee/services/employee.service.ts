import { Injectable, inject } from '@angular/core';
import { ApiClient } from '../../../core/services/api-client.service';
import { environment } from '../../../../environments/environment';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

export interface WellbeingEntry {
  id: string;
  score: number;
  mood: number | null;
  stress: number | null;
  energy: number | null;
  notes?: string | null;
  createdAt: string;
}

export interface MetricAggregate {
  current: number | null;
  previous: number | null;
  delta: number | null;
}

export interface WellbeingAggregate extends MetricAggregate {
  scale: number;
  sparkline: number[];
}

export interface BodySignal {
  label: string;
  thisWeekDays: number;
  lastWeekDays: number;
  trend: 'up' | 'down' | 'flat';
}

export interface HealthFlag {
  state: 'caution' | 'ok';
  label: string;
  badge: string;
  note: string;
}

export interface DashboardLever {
  title: string;
  badge: string;
  reason: string;
  expected: string;
  measureId: number;
}

export interface DashboardData {
  recentEntries: WellbeingEntry[];
  streak: number;
  points: number;
  lastCheckin: string | null;
  todayCheckinCompleted: boolean;
  wellbeing: WellbeingAggregate | null;
  metrics: { mood: MetricAggregate; energy: MetricAggregate; stress: MetricAggregate } | null;
  sleep: { currentH: number; previousH: number } | null;
  bodySignals: BodySignal[] | null;
  healthFlag: HealthFlag | null;
  levers: DashboardLever[] | null;
}

export interface SurveyListItem {
  id: string;
  title: string;
  description: string | null;
  status: 'ACTIVE' | 'COMPLETED' | 'EXPIRED';
  expiresAt: string | null;
  isCompleted: boolean;
}

export interface SurveyQuestion {
  id: string;
  text: string;
  type: 'SCALE' | 'MULTIPLE_CHOICE' | 'TEXT' | 'YES_NO';
  options: string[] | null;
  required: boolean;
  order: number;
}

export interface SurveyDetail extends SurveyListItem {
  questions: SurveyQuestion[];
}

export interface SurveyResult {
  id: string;
  title: string;
  description: string | null;
  submittedAt: string | null;
  questions: Array<SurveyQuestion & { answer: any }>;
}

export interface EmployeeMeasureParticipation {
  isParticipating: boolean;
  participatedAt: string | null;
  verificationType?: 'SELF_REPORTED' | 'QR_CHECKIN' | null;
  verifiedAt?: string | null;
}

export interface EmployeeMeasure {
  id: number;
  title: string;
  description: string | null;
  category: string | null;
  deliveryType?: 'REMOTE' | 'ONSITE' | 'HYBRID' | null;
  executionType?: string | null;
  verificationRequirement?: 'SELF_REPORT' | 'QR_CODE' | null;
  startsAt?: string | null;
  endsAt?: string | null;
  durationMinutes?: number | null;
  instructions?: string | null;
  locationName?: string | null;
  locationAddress?: string | null;
  capacity?: number | null;
  pointsOverride?: number | null;
  team?: { id: number; name: string } | null;
  participation?: EmployeeMeasureParticipation | null;
}

export interface MeasureEffect {
  metric: 'pain' | 'stress' | 'sleep_hours';
  unit: string | null;
  before: number | null;
  after: number | null;
  direction: 'up' | 'down';
}

export interface AssignedMeasure {
  id: number;
  title: string;
  category: string | null;
  assignmentReason: string | null;
  exerciseCount: number;
  estMinutes: number | null;
  streakDays: number | null;
  weeklyDone: number | null;
  weeklyTarget: number | null;
  effect: MeasureEffect | null;
  locationTags: string[];
  postureTags: string[];
  requiresFloor: boolean;
}

export interface MeasureExerciseStep {
  text: string;
  pictogramPath: string | null;
  alt: string | null;
}

export interface MeasureExercise {
  id: number;
  position: number;
  title: string;
  description: string | null;
  instructions: string | null;
  sets: number | null;
  repetitions: number | null;
  holdSeconds: number | null;
  durationMinutes: number | null;
  safetyNotes: string | null;
  status: string;
  mainPictogramPath: string | null;
  mainPictogramAlt: string | null;
  steps: MeasureExerciseStep[];
  defaultEffort: number | null;
  locationTags: string[];
  postureTags: string[];
  requiresFloor: boolean;
}

export interface AssignedMeasureDetail extends AssignedMeasure {
  description: string | null;
  lastSession: { effect: MeasureEffect; effort: number | null; points: number | null } | null;
  exercises: MeasureExercise[];
}

@Injectable({
  providedIn: 'root'
})
export class EmployeeService {
  private api = inject(ApiClient);

  /** Base origin serving static assets (pictograms) — the API origin without the /api suffix. */
  assetUrl(path: string | null): string | null {
    if (!path) return null;
    const origin = environment.apiBaseUrl.replace(/\/api\/?$/, '');
    return `${origin}/${path.replace(/^\//, '')}`;
  }

  getAssignedMeasures(): Observable<AssignedMeasure[]> {
    return this.api.get<{ data: AssignedMeasure[] }>('/employee/measures').pipe(map(res => res.data ?? []));
  }

  getAssignedMeasure(id: number): Observable<AssignedMeasureDetail> {
    return this.api.get<{ data: AssignedMeasureDetail }>(`/employee/measures/${id}`).pipe(map(res => res.data));
  }

  getDashboard(): Observable<DashboardData> {
    return this.api.get<any>('/employee/dashboard').pipe(map(res => ({
      recentEntries: res.entries ?? [],
      streak: res.streakCount ?? 0,
      points: res.points ?? 0,
      lastCheckin: res.latest?.createdAt ?? null,
      todayCheckinCompleted: res.todayCheckinCompleted ?? false,
      wellbeing: res.wellbeing ?? null,
      metrics: res.metrics ?? null,
      sleep: res.sleep ?? null,
      bodySignals: res.bodySignals ?? null,
      healthFlag: res.healthFlag ?? null,
      levers: res.levers ?? null,
    })));
  }

  getCheckinStatus(): Observable<{ completed: boolean; entry: WellbeingEntry | null }> {
    return this.api.get<{ completed: boolean; entry: WellbeingEntry | null }>('/employee/checkin/status');
  }

  getHistory(): Observable<WellbeingEntry[]> {
    return this.api.get<{ entries: WellbeingEntry[] }>('/employee/history').pipe(map(res => res.entries ?? []));
  }

  submitCheckin(data: {
    mood: number;
    stress: number;
    energy: number;
    notes?: string;
  }): Observable<any> {
    return this.api.post('/employee/checkin', {
      mood: data.mood,
      stress: data.stress,
      energy: data.energy,
      note: data.notes,
    });
  }

  getProfile(): Observable<any> {
    return this.api.get('/employee/profile');
  }

  updateProfile(data: any): Observable<any> {
    return this.api.put('/employee/profile', data);
  }

  getSurveys(): Observable<SurveyListItem[]> {
    return this.api.get<{ surveys: any[] }>('/employee/surveys').pipe(map(res => (res.surveys ?? []).map(s => ({
      id: s.id,
      title: s.title,
      description: s.description,
      status: s.completed ? 'COMPLETED' : 'ACTIVE',
      expiresAt: s.endsAt,
      isCompleted: s.completed,
    }))));
  }

  getSurvey(id: string): Observable<SurveyDetail> {
    return this.api.get<{ survey: any }>(`/employee/surveys/${id}`).pipe(map(res => ({
      id: res.survey.id,
      title: res.survey.title,
      description: res.survey.description,
      status: 'ACTIVE',
      expiresAt: null,
      isCompleted: false,
      questions: (res.survey.questions ?? []).map((q: any) => ({
        id: q.id,
        text: q.text,
        type: q.type,
        options: q.options,
        required: q.isRequired,
        order: q.order,
      })),
    })));
  }

  submitSurveyResponse(id: string, answers: any[]): Observable<any> {
    return this.api.post(`/employee/surveys/${id}/respond`, { answers });
  }

  getSurveyResult(id: string): Observable<SurveyResult> {
    return this.api.get<{ survey: SurveyResult }>(`/employee/surveys/${id}/result`).pipe(map(res => res.survey));
  }

  getMeasures(): Observable<EmployeeMeasure[]> {
    return this.api.get<{ data: EmployeeMeasure[] }>('/employee/company-measures').pipe(map(res => res.data ?? []));
  }

  participateInMeasure(measureId: number): Observable<{ data: EmployeeMeasure }> {
    return this.api.post<{ data: EmployeeMeasure }>(`/employee/measures/${measureId}/participate`, {});
  }

  redeemMeasureCheckin(token: string): Observable<{ data: EmployeeMeasure }> {
    return this.api.post<{ data: EmployeeMeasure }>(`/employee/measure-checkins/${token}`, {});
  }

  uploadDocument(file: File): Observable<any> {
    const formData = new FormData();
    formData.append('file', file);
    return this.api.postForm('/employee/documents', formData);
  }
}
