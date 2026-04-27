import { Injectable, inject } from '@angular/core';
import { ApiClient } from '../../../core/services/api-client.service';
import { Observable } from 'rxjs';

export interface WellbeingEntry {
  id: string;
  score: number;
  mood: string | null;
  stressLevel: number | null;
  sleepQuality: number | null;
  physicalActivity: number | null;
  notes: string | null;
  createdAt: string;
}

export interface DashboardData {
  recentEntries: WellbeingEntry[];
  streak: number;
  points: number;
  lastCheckin: string | null;
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

@Injectable({
  providedIn: 'root'
})
export class EmployeeService {
  private api = inject(ApiClient);

  getDashboard(): Observable<DashboardData> {
    return this.api.get<DashboardData>('employee/dashboard');
  }

  getHistory(): Observable<WellbeingEntry[]> {
    return this.api.get<WellbeingEntry[]>('employee/history');
  }

  submitCheckin(data: {
    mood: string;
    stressLevel: number;
    sleepQuality: number;
    physicalActivity: number;
    notes?: string;
  }): Observable<any> {
    return this.api.post('employee/checkin', data);
  }

  getProfile(): Observable<any> {
    return this.api.get('employee/profile');
  }

  updateProfile(data: any): Observable<any> {
    return this.api.put('employee/profile', data);
  }

  getSurveys(): Observable<SurveyListItem[]> {
    return this.api.get<SurveyListItem[]>('employee/surveys');
  }

  getSurvey(id: string): Observable<SurveyDetail> {
    return this.api.get<SurveyDetail>(`employee/surveys/${id}`);
  }

  submitSurveyResponse(id: string, answers: any[]): Observable<any> {
    return this.api.post(`employee/surveys/${id}/respond`, { answers });
  }
}
