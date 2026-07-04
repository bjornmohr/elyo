import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiClient } from '../../../core/services/api-client.service';

export interface RiskFieldMonthlyScore {
  period: string;
  score: number;
}

export interface RiskField {
  field: string;
  fieldLabel: string;
  relevanceLevel: 1 | 2 | 3 | 4 | 5;
  score: number;
  trend30d: number;
  monthlyScores: RiskFieldMonthlyScore[];
  monthsAboveHighSince: number | null;
  linkedMeasures: { activeCount: number; suggestedCount: number };
}

export interface RiskLandscape {
  fields: RiskField[];
  recommendations: { title: string; text: string }[];
}

export interface UsageFunnelStage {
  key: string;
  label: string;
  count: number;
  rate: number;
}

export interface UsageFunnelCategory {
  key: string;
  label: string;
  recommendationReceived: number;
  measureStarted: number;
  active14d: number;
}

export interface UsageFunnel {
  cohort: string;
  stages: UsageFunnelStage[];
  transitions: { from: string; to: string; avgDays: number | null }[];
  returnRate14d: number;
  avgDaysToFirstDecision: number;
  previousCohort: { cohort: string; stages: { key: string; rate: number }[] } | null;
  categoryInsight?: string;
  categories?: UsageFunnelCategory[];
}

export interface InfectionRadar {
  overallStatus: 'NORMAL' | 'ELEVATED' | 'CRITICAL';
  locations: { name: string; status: 'NORMAL' | 'ELEVATED' | 'CRITICAL' }[];
  symptomReports7d: { date: string; count: number }[];
  rkiIncidence: { value: number; deltaPercent: number; district: string };
  recommendations: { title: string; text: string }[];
}

@Injectable({
  providedIn: 'root',
})
export class CompanyInsightsService {
  private api = inject(ApiClient);

  getRiskLandscape(): Observable<{ data: RiskLandscape | [] }> {
    return this.api.get<{ data: RiskLandscape | [] }>('/company/risk-landscape');
  }

  getUsageFunnel(): Observable<{ data: UsageFunnel | null }> {
    return this.api.get<{ data: UsageFunnel | null }>('/company/usage-funnel');
  }

  getInfectionRadar(): Observable<{ data: InfectionRadar | null }> {
    return this.api.get<{ data: InfectionRadar | null }>('/company/infection-radar');
  }
}
