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
      delete: vi.fn(() => of(undefined)),
    };

    TestBed.configureTestingModule({
      providers: [
        AdminSystemMeasureTemplateService,
        { provide: ApiClient, useValue: api },
      ],
    });

    service = TestBed.inject(AdminSystemMeasureTemplateService);
  });

  it('lists templates with filters', () => {
    const filters = { search: 'reset', status: 'ACTIVE' as const, page: 2 };

    service.listTemplates(filters).subscribe();

    expect(api.get).toHaveBeenCalledWith('/admin/system-measure-templates', filters);
  });

  it('gets a template by id', () => {
    service.getTemplate(3).subscribe();

    expect(api.get).toHaveBeenCalledWith('/admin/system-measure-templates/3');
  });

  it('creates and updates templates without a slug payload', () => {
    service.createTemplate({ title: 'Reset', category: 'MIXED' }).subscribe();
    service.updateTemplate(3, { title: 'Updated' }).subscribe();

    expect(api.post).toHaveBeenCalledWith('/admin/system-measure-templates', { title: 'Reset', category: 'MIXED' });
    expect(api.patch).toHaveBeenCalledWith('/admin/system-measure-templates/3', { title: 'Updated' });
  });

  it('archives templates through the archive endpoint', () => {
    service.archiveTemplate(3).subscribe();

    expect(api.post).toHaveBeenCalledWith('/admin/system-measure-templates/3/archive', {});
  });

  it('manages template exercises through relationship endpoints', () => {
    service.addExercise(3, { systemExerciseId: 8 }).subscribe();
    service.updateTemplateExercise(3, 9, { customTitle: 'Override' }).subscribe();
    service.removeTemplateExercise(3, 9).subscribe();
    service.reorderExercises(3, { items: [{ id: 9, sortOrder: 1 }] }).subscribe();

    expect(api.post).toHaveBeenCalledWith('/admin/system-measure-templates/3/exercises', { systemExerciseId: 8 });
    expect(api.patch).toHaveBeenCalledWith('/admin/system-measure-templates/3/exercises/9', { customTitle: 'Override' });
    expect(api.delete).toHaveBeenCalledWith('/admin/system-measure-templates/3/exercises/9');
    expect(api.post).toHaveBeenCalledWith('/admin/system-measure-templates/3/exercises/reorder', { items: [{ id: 9, sortOrder: 1 }] });
  });
});
