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

  generateMeasureCheckinToken(measureId: number | string): Observable<{ data: MeasureCheckinTokenResponse }> {
    return this.api.post<{ data: MeasureCheckinTokenResponse }>(`/company/measures/${measureId}/checkin-token`, {});
  }
}
