import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { ApiClient } from '../../../core/services/api-client.service';
import { AdminSystemMeasureTemplateService } from './admin-system-measure-template.service';

describe('AdminSystemMeasureTemplateService', () => {
  let api: {
    get: ReturnType<typeof vi.fn>;
    post: ReturnType<typeof vi.fn>;
    patch: ReturnType<typeof vi.fn>;
    delete: ReturnType<typeof vi.fn>;
  };
  let service: AdminSystemMeasureTemplateService;

  beforeEach(() => {
    api = {
      get: vi.fn(() => of({ data: [] })),
      post: vi.fn(() => of({ data: {} })),
      patch: vi.fn(() => of({ data: {} })),
      delete: vi.fn(() => of(void 0)),
    };

    TestBed.configureTestingModule({
      providers: [
        AdminSystemMeasureTemplateService,
        { provide: ApiClient, useValue: api },
      ],
    });

    service = TestBed.inject(AdminSystemMeasureTemplateService);
  });

  it('lists templates with the provided filters', () => {
    const filters = { search: 'stress', status: 'ACTIVE' as const, isFeatured: true, page: 2 };

    service.listTemplates(filters).subscribe();

    expect(api.get).toHaveBeenCalledWith('/admin/system-measure-templates', filters);
  });

  it('lists templates without filters by default', () => {
    service.listTemplates().subscribe();

    expect(api.get).toHaveBeenCalledWith('/admin/system-measure-templates', {});
  });

  it('gets a single template by id', () => {
    service.getTemplate(7).subscribe();

    expect(api.get).toHaveBeenCalledWith('/admin/system-measure-templates/7');
  });

  it('creates a template with the given payload', () => {
    const payload = {
      title: 'Stress Reset',
      category: 'MINDFULNESS' as const,
      difficulty: 'BEGINNER' as const,
    };

    service.createTemplate(payload).subscribe();

    expect(api.post).toHaveBeenCalledWith('/admin/system-measure-templates', payload);
  });

  it('updates a template via PATCH', () => {
    service.updateTemplate(5, { title: 'Neu' }).subscribe();

    expect(api.patch).toHaveBeenCalledWith('/admin/system-measure-templates/5', { title: 'Neu' });
  });

  it('archives a template with an empty body and no delete call', () => {
    service.archiveTemplate(5).subscribe();

    expect(api.post).toHaveBeenCalledWith('/admin/system-measure-templates/5/archive', {});
    expect(api.delete).not.toHaveBeenCalled();
  });

  it('adds an exercise to a template', () => {
    const payload = { systemExerciseId: 3, customSets: 2 };

    service.addExercise(5, payload).subscribe();

    expect(api.post).toHaveBeenCalledWith('/admin/system-measure-templates/5/exercises', payload);
  });

  it('updates a template exercise via PATCH', () => {
    service.updateTemplateExercise(5, 9, { customTitle: 'Anders' }).subscribe();

    expect(api.patch).toHaveBeenCalledWith('/admin/system-measure-templates/5/exercises/9', { customTitle: 'Anders' });
  });

  it('removes a template exercise via DELETE', () => {
    service.removeTemplateExercise(5, 9).subscribe();

    expect(api.delete).toHaveBeenCalledWith('/admin/system-measure-templates/5/exercises/9');
  });

  it('reorders template exercises with the complete payload', () => {
    const payload = { items: [{ id: 9, sortOrder: 2 }, { id: 8, sortOrder: 1 }] };

    service.reorderExercises(5, payload).subscribe();

    expect(api.post).toHaveBeenCalledWith('/admin/system-measure-templates/5/exercises/reorder', payload);
  });
});
