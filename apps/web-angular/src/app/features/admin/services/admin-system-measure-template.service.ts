import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiClient } from '../../../core/services/api-client.service';
import { PaginatedResponse } from '../models/admin-system-exercise.models';
import {
  AdminSystemMeasureTemplate,
  AdminSystemMeasureTemplateExercise,
  CreateSystemMeasureTemplateExercisePayload,
  CreateSystemMeasureTemplatePayload,
  ListSystemMeasureTemplatesParams,
  ReorderSystemMeasureTemplateExercisesPayload,
  UpdateSystemMeasureTemplateExercisePayload,
  UpdateSystemMeasureTemplatePayload,
} from '../models/admin-system-measure-template.models';

@Injectable({
  providedIn: 'root',
})
export class AdminSystemMeasureTemplateService {
  private api = inject(ApiClient);

  listTemplates(filters: ListSystemMeasureTemplatesParams = {}): Observable<PaginatedResponse<AdminSystemMeasureTemplate>> {
    return this.api.get<PaginatedResponse<AdminSystemMeasureTemplate>>('/admin/system-measure-templates', filters);
  }

  getTemplate(id: number | string): Observable<{ data: AdminSystemMeasureTemplate }> {
    return this.api.get<{ data: AdminSystemMeasureTemplate }>(`/admin/system-measure-templates/${id}`);
  }

  createTemplate(payload: CreateSystemMeasureTemplatePayload): Observable<{ data: AdminSystemMeasureTemplate }> {
    return this.api.post<{ data: AdminSystemMeasureTemplate }>('/admin/system-measure-templates', payload);
  }

  updateTemplate(id: number | string, payload: UpdateSystemMeasureTemplatePayload): Observable<{ data: AdminSystemMeasureTemplate }> {
    return this.api.patch<{ data: AdminSystemMeasureTemplate }>(`/admin/system-measure-templates/${id}`, payload);
  }

  archiveTemplate(id: number | string): Observable<{ data: AdminSystemMeasureTemplate }> {
    return this.api.post<{ data: AdminSystemMeasureTemplate }>(`/admin/system-measure-templates/${id}/archive`, {});
  }

  addExercise(templateId: number | string, payload: CreateSystemMeasureTemplateExercisePayload): Observable<{ data: AdminSystemMeasureTemplateExercise }> {
    return this.api.post<{ data: AdminSystemMeasureTemplateExercise }>(`/admin/system-measure-templates/${templateId}/exercises`, payload);
  }

  updateTemplateExercise(
    templateId: number | string,
    templateExerciseId: number | string,
    payload: UpdateSystemMeasureTemplateExercisePayload,
  ): Observable<{ data: AdminSystemMeasureTemplateExercise }> {
    return this.api.patch<{ data: AdminSystemMeasureTemplateExercise }>(
      `/admin/system-measure-templates/${templateId}/exercises/${templateExerciseId}`,
      payload,
    );
  }

  removeTemplateExercise(templateId: number | string, templateExerciseId: number | string): Observable<void> {
    return this.api.delete<void>(`/admin/system-measure-templates/${templateId}/exercises/${templateExerciseId}`);
  }

  reorderExercises(
    templateId: number | string,
    payload: ReorderSystemMeasureTemplateExercisesPayload,
  ): Observable<{ data: AdminSystemMeasureTemplateExercise[] }> {
    return this.api.post<{ data: AdminSystemMeasureTemplateExercise[] }>(
      `/admin/system-measure-templates/${templateId}/exercises/reorder`,
      payload,
    );
  }
}
