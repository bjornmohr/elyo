import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { NotificationService } from '../../../../shared/notifications/notification.service';
import { AdminSystemExerciseService } from '../../services/admin-system-exercise.service';
import { AdminSystemMeasureTemplateService } from '../../services/admin-system-measure-template.service';
import {
  AdminSystemMeasureTemplate,
  AdminSystemMeasureTemplateExercise,
} from '../../models/admin-system-measure-template.models';
import { AdminSystemMeasureTemplatesComponent } from './admin-system-measure-templates.component';

describe('AdminSystemMeasureTemplatesComponent', () => {
  let service: {
    listTemplates: ReturnType<typeof vi.fn>;
    getTemplate: ReturnType<typeof vi.fn>;
    createTemplate: ReturnType<typeof vi.fn>;
    updateTemplate: ReturnType<typeof vi.fn>;
    archiveTemplate: ReturnType<typeof vi.fn>;
    addExercise: ReturnType<typeof vi.fn>;
    updateTemplateExercise: ReturnType<typeof vi.fn>;
    removeTemplateExercise: ReturnType<typeof vi.fn>;
    reorderExercises: ReturnType<typeof vi.fn>;
  };
  let exerciseService: {
    listExercises: ReturnType<typeof vi.fn>;
  };

  const templateExercises: AdminSystemMeasureTemplateExercise[] = [
    {
      id: 21,
      systemExerciseId: 1,
      sortOrder: 1,
      customTitle: null,
      customInstructions: null,
      customDurationMinutes: null,
      customSets: null,
      customRepetitions: null,
      customHoldSeconds: null,
      customFeedbackPrompt: null,
      isRequired: true,
      exercise: {
        id: 1,
        slug: 'nacken-stretch',
        title: 'Nacken Stretch',
        shortDescription: null,
        exerciseType: 'MOBILITY',
        difficulty: 'BEGINNER',
        defaultDurationMinutes: 5,
        status: 'ACTIVE',
        tags: [],
      },
    },
    {
      id: 22,
      systemExerciseId: 2,
      sortOrder: 2,
      customTitle: 'Atemfokus',
      customInstructions: null,
      customDurationMinutes: 10,
      customSets: null,
      customRepetitions: null,
      customHoldSeconds: null,
      customFeedbackPrompt: null,
      isRequired: false,
      exercise: {
        id: 2,
        slug: 'atemuebung',
        title: 'Atemübung',
        shortDescription: null,
        exerciseType: 'BREATHING',
        difficulty: 'BEGINNER',
        defaultDurationMinutes: 8,
        status: 'ACTIVE',
        tags: [],
      },
    },
  ];

  const template: AdminSystemMeasureTemplate = {
    id: 10,
    slug: 'stress-reset',
    title: 'Stress Reset',
    shortDescription: 'Kurz',
    description: 'Lang',
    category: 'MINDFULNESS',
    difficulty: 'BEGINNER',
    estimatedDurationMinutes: 30,
    status: 'ACTIVE',
    isFeatured: false,
    targetSignal: null,
    assignmentReasonTemplate: null,
    effectMetric: null,
    effectMetricUnit: null,
    locationTags: null,
    postureTags: null,
    requiresFloor: false,
    exerciseCount: 2,
    createdAt: '2026-06-11T00:00:00Z',
    updatedAt: '2026-06-11T00:00:00Z',
  };

  const detailResponse = { data: { ...template, exercises: templateExercises } };

  const listResponse = {
    data: [template],
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, from: 1, last_page: 1, per_page: 25, to: 1, total: 1 },
  };

  const catalogResponse = {
    data: [
      { id: 3, slug: 'schulterkreisen', title: 'Schulterkreisen', exerciseType: 'MOBILITY', status: 'ACTIVE' },
    ],
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, from: 1, last_page: 1, per_page: 100, to: 1, total: 1 },
  };

  beforeEach(async () => {
    service = {
      listTemplates: vi.fn(() => of(listResponse)),
      getTemplate: vi.fn(() => of(detailResponse)),
      createTemplate: vi.fn(() => of({ data: template })),
      updateTemplate: vi.fn(() => of({ data: template })),
      archiveTemplate: vi.fn(() => of({ data: { ...template, status: 'ARCHIVED' } })),
      addExercise: vi.fn(() => of({ data: templateExercises[0] })),
      updateTemplateExercise: vi.fn(() => of({ data: templateExercises[1] })),
      removeTemplateExercise: vi.fn(() => of(void 0)),
      reorderExercises: vi.fn(() => of({ data: [...templateExercises].reverse() })),
    };
    exerciseService = {
      listExercises: vi.fn(() => of(catalogResponse)),
    };

    await TestBed.configureTestingModule({
      imports: [AdminSystemMeasureTemplatesComponent],
      providers: [
        { provide: AdminSystemMeasureTemplateService, useValue: service },
        { provide: AdminSystemExerciseService, useValue: exerciseService },
        {
          provide: NotificationService,
          useValue: { success: vi.fn(), error: vi.fn() },
        },
      ],
    }).compileComponents();
  });

  it('loads templates on init', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();

    expect(service.listTemplates).toHaveBeenCalledWith({ page: 1 });
    expect(fixture.componentInstance.templates()).toEqual([template]);
  });

  it('reloads with filter params when filters are applied', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();

    fixture.componentInstance.filterForm.patchValue({
      search: 'stress',
      status: 'ACTIVE',
      category: 'MINDFULNESS',
      difficulty: 'BEGINNER',
      isFeatured: 'true',
    });
    fixture.componentInstance.applyFilters();

    expect(service.listTemplates).toHaveBeenLastCalledWith({
      page: 1,
      search: 'stress',
      status: 'ACTIVE',
      category: 'MINDFULNESS',
      difficulty: 'BEGINNER',
      isFeatured: true,
    });
  });

  it('creates a template with the form payload and no slug field', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();
    const component = fixture.componentInstance;

    component.openCreate();
    component.form.patchValue({
      title: 'Neues Template',
      category: 'BREATHING',
      difficulty: 'INTERMEDIATE',
      status: 'DRAFT',
      estimatedDurationMinutes: 20,
      isFeatured: true,
    });
    component.save();

    expect(service.createTemplate).toHaveBeenCalledWith(
      expect.objectContaining({
        title: 'Neues Template',
        category: 'BREATHING',
        difficulty: 'INTERMEDIATE',
        status: 'DRAFT',
        estimatedDurationMinutes: 20,
        isFeatured: true,
      })
    );
    const payload = service.createTemplate.mock.calls[0][0] as Record<string, unknown>;
    expect(payload).not.toHaveProperty('slug');
    expect(service.updateTemplate).not.toHaveBeenCalled();
  });

  it('updates an existing template in edit mode', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();
    const component = fixture.componentInstance;

    component.openEdit(template);
    component.form.patchValue({ title: 'Geänderter Titel' });
    component.save();

    expect(service.updateTemplate).toHaveBeenCalledWith(
      10,
      expect.objectContaining({ title: 'Geänderter Titel' })
    );
    expect(service.createTemplate).not.toHaveBeenCalled();
  });

  it('archives a template after confirmation and reloads the list', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    fixture.componentInstance.archive(template);

    expect(service.archiveTemplate).toHaveBeenCalledWith(10);
    expect(service.listTemplates).toHaveBeenCalledTimes(2);
  });

  it('loads the detail with ordered exercises and the active exercise catalog', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();
    const component = fixture.componentInstance;

    component.openDetail(template);

    expect(service.getTemplate).toHaveBeenCalledWith(10);
    expect(component.detailExercises()).toEqual(templateExercises);
    expect(component.detailExercises()[0].sortOrder).toBe(1);
    expect(component.detailExercises()[1].sortOrder).toBe(2);
    expect(exerciseService.listExercises).toHaveBeenCalledWith({ status: 'ACTIVE', perPage: 100 });
  });

  it('adds an exercise from the catalog with the expected payload', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();
    const component = fixture.componentInstance;

    component.openDetail(template);
    component.addExerciseControl.setValue(3);
    component.addExercise();

    expect(service.addExercise).toHaveBeenCalledWith(10, { systemExerciseId: 3 });
  });

  it('updates overrides with the expected payload', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();
    const component = fixture.componentInstance;

    component.openDetail(template);
    component.openOverrides(templateExercises[1]);
    component.overridesForm.patchValue({ customTitle: 'Neuer Override', customSets: 3, isRequired: true });
    component.saveOverrides();

    expect(service.updateTemplateExercise).toHaveBeenCalledWith(
      10,
      22,
      expect.objectContaining({
        customTitle: 'Neuer Override',
        customSets: 3,
        isRequired: true,
      })
    );
  });

  it('removes an exercise after confirmation', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();
    const component = fixture.componentInstance;
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    component.openDetail(template);
    component.removeExercise(templateExercises[0]);

    expect(service.removeTemplateExercise).toHaveBeenCalledWith(10, 21);
  });

  it('reorders with the complete id set when moving an exercise down', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();
    const component = fixture.componentInstance;

    component.openDetail(template);
    component.moveDown(templateExercises[0]);

    expect(service.reorderExercises).toHaveBeenCalledWith(10, {
      items: [
        { id: 22, sortOrder: 1 },
        { id: 21, sortOrder: 2 },
      ],
    });
  });

  it('does not reorder when the exercise is already first', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();
    const component = fixture.componentInstance;

    component.openDetail(template);
    component.moveUp(templateExercises[0]);

    expect(service.reorderExercises).not.toHaveBeenCalled();
  });

  it('has no affordance for creating exercises from the template builder', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();
    fixture.componentInstance.openDetail(template);
    fixture.detectChanges();

    const element: HTMLElement = fixture.nativeElement;
    expect(element.textContent).not.toContain('Übung anlegen');
    expect(element.textContent).not.toContain('Übung bearbeiten');
    expect(element.textContent).toContain('Übung aus dem Katalog hinzufügen');
  });
});
