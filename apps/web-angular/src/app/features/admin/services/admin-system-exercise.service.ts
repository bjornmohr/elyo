import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiClient } from '../../../core/services/api-client.service';
import {
  AdminSystemExercise,
  AdminSystemExerciseTag,
  CreateSystemExercisePayload,
  ListSystemExercisesParams,
  ListSystemExerciseTagsParams,
  PaginatedResponse,
  UpdateSystemExercisePayload,
} from '../models/admin-system-exercise.models';

@Injectable({
  providedIn: 'root',
})
export class AdminSystemExerciseService {
  private api = inject(ApiClient);

  listExercises(filters: ListSystemExercisesParams = {}): Observable<PaginatedResponse<AdminSystemExercise>> {
    return this.api.get<PaginatedResponse<AdminSystemExercise>>('/admin/system-exercises', filters);
  }

  getExercise(id: number | string): Observable<{ data: AdminSystemExercise }> {
    return this.api.get<{ data: AdminSystemExercise }>(`/admin/system-exercises/${id}`);
  }

  createExercise(payload: CreateSystemExercisePayload): Observable<{ data: AdminSystemExercise }> {
    return this.api.post<{ data: AdminSystemExercise }>('/admin/system-exercises', payload);
  }

  updateExercise(id: number | string, payload: UpdateSystemExercisePayload): Observable<{ data: AdminSystemExercise }> {
    return this.api.patch<{ data: AdminSystemExercise }>(`/admin/system-exercises/${id}`, payload);
  }

  archiveExercise(id: number | string): Observable<{ data: AdminSystemExercise }> {
    return this.api.post<{ data: AdminSystemExercise }>(`/admin/system-exercises/${id}/archive`, {});
  }

  listTags(filters: ListSystemExerciseTagsParams = {}): Observable<{ data: AdminSystemExerciseTag[] }> {
    return this.api.get<{ data: AdminSystemExerciseTag[] }>('/admin/system-exercise-tags', filters);
  }
}
