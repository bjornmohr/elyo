export type SystemExerciseType =
  | 'MOBILITY'
  | 'STRENGTH'
  | 'BREATHING'
  | 'MINDFULNESS'
  | 'EDUCATION'
  | 'REFLECTION';

export type SystemExerciseDifficulty = 'BEGINNER' | 'INTERMEDIATE' | 'ADVANCED';

export type SystemExerciseStatus = 'DRAFT' | 'ACTIVE' | 'ARCHIVED';

export type SystemExerciseTagCategory =
  | 'BODY_REGION'
  | 'GOAL'
  | 'SETTING'
  | 'EQUIPMENT'
  | 'CONTRAINDICATION'
  | 'PERSONA_HINT'
  | 'HEALTH_FOCUS';

export interface AdminSystemExerciseTag {
  id: number;
  category: SystemExerciseTagCategory;
  key: string;
  label: string;
  description: string | null;
  sortOrder: number;
  isActive: boolean;
}

export interface AdminSystemExercise {
  id: number;
  slug: string;
  title: string;
  shortDescription: string | null;
  description: string | null;
  exerciseType: SystemExerciseType;
  difficulty: SystemExerciseDifficulty;
  defaultDurationMinutes: number | null;
  defaultSets: number | null;
  defaultRepetitions: number | null;
  defaultHoldSeconds: number | null;
  instructions: string | null;
  safetyNotes: string | null;
  contraindications: string | null;
  defaultFeedbackPrompt: string | null;
  requiresFeedback: boolean;
  status: SystemExerciseStatus;
  tags: AdminSystemExerciseTag[];
  createdAt: string;
  updatedAt: string;
}

export interface ListSystemExercisesParams {
  search?: string;
  status?: SystemExerciseStatus;
  exerciseType?: SystemExerciseType;
  difficulty?: SystemExerciseDifficulty;
  tagCategory?: SystemExerciseTagCategory;
  tagKey?: string;
  page?: number;
  perPage?: number;
}

export interface ListSystemExerciseTagsParams {
  category?: SystemExerciseTagCategory;
  isActive?: boolean;
  search?: string;
}

export interface CreateSystemExercisePayload {
  title: string;
  shortDescription?: string | null;
  description?: string | null;
  exerciseType: SystemExerciseType;
  difficulty: SystemExerciseDifficulty;
  status?: SystemExerciseStatus;
  defaultDurationMinutes?: number | null;
  defaultSets?: number | null;
  defaultRepetitions?: number | null;
  defaultHoldSeconds?: number | null;
  instructions?: string | null;
  safetyNotes?: string | null;
  contraindications?: string | null;
  defaultFeedbackPrompt?: string | null;
  requiresFeedback?: boolean;
  tagIds?: number[];
}

export type UpdateSystemExercisePayload = Partial<CreateSystemExercisePayload>;

export interface PaginatedResponse<T> {
  data: T[];
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
  meta: {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
  };
}
