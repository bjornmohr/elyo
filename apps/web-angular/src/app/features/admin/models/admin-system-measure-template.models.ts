import {
  AdminSystemExerciseTag,
  SystemExerciseDifficulty,
  SystemExerciseStatus,
  SystemExerciseType,
} from './admin-system-exercise.models';

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
  exercise?: {
    id: number;
    slug: string;
    title: string;
    shortDescription: string | null;
    exerciseType: SystemExerciseType;
    difficulty: SystemExerciseDifficulty;
    defaultDurationMinutes: number | null;
    status: SystemExerciseStatus;
    tags: AdminSystemExerciseTag[];
  };
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
  status?: SystemMeasureTemplateStatus;
  estimatedDurationMinutes?: number | null;
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

export type UpdateSystemMeasureTemplateExercisePayload = Partial<Omit<CreateSystemMeasureTemplateExercisePayload, 'systemExerciseId'>>;

export interface ReorderSystemMeasureTemplateExercisesPayload {
  items: { id: number; sortOrder: number }[];
}
