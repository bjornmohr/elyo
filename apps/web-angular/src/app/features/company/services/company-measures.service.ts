import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiClient } from '../../../core/services/api-client.service';

export interface MeasureCheckinTokenResponse {
  measureId: number;
  token: string;
  checkinPath: string;
  validFrom: string | null;
  validUntil: string | null;
  revokedAt: string | null;
}

export interface MeasureExecution {
  measureId: number;
  derivedStatus: 'UPCOMING' | 'RUNNING' | 'COMPLETED' | 'PLANNED';
  deliveryType: 'ONSITE' | 'REMOTE' | 'HYBRID';
  executionType: string;
  startsAt: string | null;
  endsAt: string | null;
  locationName: string | null;
  capacity: number | null;
  registeredCount: number | null;
  checkin: { active: boolean; createdAt: string | null; required: boolean };
  isAboveThreshold: boolean;
}

export interface MeasureFieldStatistics {
  field: string;
  fieldLabel: string;
  measureCount: number;
  avgParticipationRate: number | null;
  isAboveThreshold: boolean;
  avgImpactRating: number | null;
  impactIsPreliminary: boolean;
  fieldTrend30d: number | null;
}

export interface MeasureImpactGroup {
  n: number;
  scoreBefore: number;
  scoreAfter: number;
}

export interface MeasureImpact {
  measureId: number;
  field: string;
  windowWeeks: number;
  participants: MeasureImpactGroup;
  control: MeasureImpactGroup;
  netEffect: number;
  rating: number;
  isAboveThreshold: boolean;
}

@Injectable({
  providedIn: 'root',
})
export class CompanyMeasuresService {
  private api = inject(ApiClient);

  listMeasures(): Observable<{ data: any[] }> {
    return this.api.get<{ data: any[] }>('/company/measures');
  }

  getParticipationSummary(measureId: number | string): Observable<{ data: any }> {
    return this.api.get<{ data: any }>(`/company/measures/${measureId}/participation-summary`);
  }

  createMeasure(payload: Record<string, unknown>): Observable<{ data: any }> {
    return this.api.post<{ data: any }>('/company/measures', payload);
  }

  updateMeasure(measureId: number | string, payload: Record<string, unknown>): Observable<{ data: any }> {
    return this.api.patch<{ data: any }>(`/company/measures/${measureId}`, payload);
  }

  generateMeasureCheckinToken(measureId: number | string): Observable<{ data: MeasureCheckinTokenResponse }> {
    return this.api.post<{ data: MeasureCheckinTokenResponse }>(`/company/measures/${measureId}/checkin-token`, {});
  }

  getExecution(measureId: number | string): Observable<{ data: MeasureExecution }> {
    return this.api.get<{ data: MeasureExecution }>(`/company/measures/${measureId}/execution`);
  }

  getStatistics(): Observable<{ data: MeasureFieldStatistics[] }> {
    return this.api.get<{ data: MeasureFieldStatistics[] }>('/company/measures/statistics');
  }

  getImpact(measureId: number | string): Observable<{ data: MeasureImpact | null }> {
    return this.api.get<{ data: MeasureImpact | null }>(`/company/measures/${measureId}/impact`);
  }
}
