import { AdminSystemExercise, PaginatedResponse, SystemExerciseDifficulty } from './admin-system-exercise.models';

export type SystemMeasureTemplateStatus = 'DRAFT' | 'ACTIVE' | 'ARCHIVED';

export type SystemMeasureTemplateCategory =
  | 'MOBILITY'
  | 'STRENGTH'
  | 'BREATHING'
  | 'MINDFULNESS'
  | 'EDUCATION'
  | 'REFLECTION'
  | 'MIXED';

export type SystemMeasureTemplateDifficulty = SystemExerciseDifficulty;

export interface AdminSystemMeasureTemplateExercise {
  id: number;
  systemExerciseId: number;
  sortOrder: number;
  customTitle: string | null;
  customInstructions: string | null;
  customDurationMinutes: number | null;
  customSets: number | null;
  customRepetitions: number | null;
  customHoldSeconds: number | null;
  customFeedbackPrompt: string | null;
  isRequired: boolean;
  exercise: AdminSystemExercise;
}

export interface AdminSystemMeasureTemplate {
  id: number;
  slug: string;
  title: string;
  shortDescription: string | null;
  description: string | null;
  category: SystemMeasureTemplateCategory;
  difficulty: SystemMeasureTemplateDifficulty;
  estimatedDurationMinutes: number | null;
  status: SystemMeasureTemplateStatus;
  isFeatured: boolean;
  exerciseCount?: number;
  exercises?: AdminSystemMeasureTemplateExercise[];
  createdAt: string;
  updatedAt: string;
}

export interface ListSystemMeasureTemplatesParams {
  search?: string;
  status?: SystemMeasureTemplateStatus;
  category?: SystemMeasureTemplateCategory;
  difficulty?: SystemMeasureTemplateDifficulty;
  isFeatured?: boolean;
  page?: number;
  perPage?: number;
}

export interface CreateSystemMeasureTemplatePayload {
  title: string;
  shortDescription?: string | null;
  description?: string | null;
  category?: SystemMeasureTemplateCategory;
  difficulty?: SystemMeasureTemplateDifficulty;
  estimatedDurationMinutes?: number | null;
  status?: SystemMeasureTemplateStatus;
  isFeatured?: boolean;
}

export type UpdateSystemMeasureTemplatePayload = Partial<CreateSystemMeasureTemplatePayload>;

export interface CreateSystemMeasureTemplateExercisePayload {
  systemExerciseId: number;
  sortOrder?: number;
  customTitle?: string | null;
  customInstructions?: string | null;
  customDurationMinutes?: number | null;
  customSets?: number | null;
  customRepetitions?: number | null;
  customHoldSeconds?: number | null;
  customFeedbackPrompt?: string | null;
  isRequired?: boolean;
}

export interface UpdateSystemMeasureTemplateExercisePayload {
  sortOrder?: number;
  customTitle?: string | null;
  customInstructions?: string | null;
  customDurationMinutes?: number | null;
  customSets?: number | null;
  customRepetitions?: number | null;
  customHoldSeconds?: number | null;
  customFeedbackPrompt?: string | null;
  isRequired?: boolean;
}

export interface ReorderSystemMeasureTemplateExercisesPayload {
  items: Array<{ id: number; sortOrder: number }>;
}

export type AdminSystemMeasureTemplateListResponse = PaginatedResponse<AdminSystemMeasureTemplate>;
