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
}
