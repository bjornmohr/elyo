import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { ApiClient } from '../../../core/services/api-client.service';
import { AdminSystemExerciseService } from './admin-system-exercise.service';

describe('AdminSystemExerciseService', () => {
  let api: {
    get: ReturnType<typeof vi.fn>;
    post: ReturnType<typeof vi.fn>;
    patch: ReturnType<typeof vi.fn>;
  };
  let service: AdminSystemExerciseService;

  beforeEach(() => {
    api = {
      get: vi.fn(() => of({ data: [] })),
      post: vi.fn(() => of({ data: {} })),
      patch: vi.fn(() => of({ data: {} })),
    };

    TestBed.configureTestingModule({
      providers: [
        AdminSystemExerciseService,
        { provide: ApiClient, useValue: api },
      ],
    });

    service = TestBed.inject(AdminSystemExerciseService);
  });

  it('lists exercises with the provided filters', () => {
    const filters = { search: 'nacken', status: 'ACTIVE' as const, page: 2 };

    service.listExercises(filters).subscribe();

    expect(api.get).toHaveBeenCalledWith('/admin/system-exercises', filters);
  });

  it('lists exercises without filters by default', () => {
    service.listExercises().subscribe();

    expect(api.get).toHaveBeenCalledWith('/admin/system-exercises', {});
  });

  it('gets a single exercise by id', () => {
    service.getExercise(7).subscribe();

    expect(api.get).toHaveBeenCalledWith('/admin/system-exercises/7');
  });

  it('creates an exercise with the given payload', () => {
    const payload = {
      title: 'Schulterkreisen',
      exerciseType: 'MOBILITY' as const,
      difficulty: 'BEGINNER' as const,
      tagIds: [1, 2],
    };

    service.createExercise(payload).subscribe();

    expect(api.post).toHaveBeenCalledWith('/admin/system-exercises', payload);
  });

  it('updates an exercise via PATCH', () => {
    service.updateExercise(5, { title: 'Neu' }).subscribe();

    expect(api.patch).toHaveBeenCalledWith('/admin/system-exercises/5', { title: 'Neu' });
  });

  it('archives an exercise with an empty body and no delete call', () => {
    service.archiveExercise(5).subscribe();

    expect(api.post).toHaveBeenCalledWith('/admin/system-exercises/5/archive', {});
  });

  it('lists tags with filters', () => {
    service.listTags({ category: 'BODY_REGION', isActive: true }).subscribe();

    expect(api.get).toHaveBeenCalledWith('/admin/system-exercise-tags', { category: 'BODY_REGION', isActive: true });
  });
});
