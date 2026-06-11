import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { NotificationService } from '../../../../shared/notifications/notification.service';
import { AdminSystemExercise } from '../../models/admin-system-exercise.models';
import {
  AdminSystemMeasureTemplate,
  AdminSystemMeasureTemplateExercise,
} from '../../models/admin-system-measure-template.models';
import { AdminSystemExerciseService } from '../../services/admin-system-exercise.service';
import { AdminSystemMeasureTemplateService } from '../../services/admin-system-measure-template.service';
import { AdminSystemMeasureTemplatesComponent } from './admin-system-measure-templates.component';

describe('AdminSystemMeasureTemplatesComponent', () => {
  let templateService: {
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

  const exercise: AdminSystemExercise = {
    id: 8,
    slug: 'nacken-stretch',
    title: 'Nacken Stretch',
    shortDescription: 'Kurz',
    description: 'Lang',
    exerciseType: 'MOBILITY',
    difficulty: 'BEGINNER',
    defaultDurationMinutes: 5,
    defaultSets: null,
    defaultRepetitions: null,
    defaultHoldSeconds: null,
    instructions: 'Anleitung',
    safetyNotes: null,
    contraindications: null,
    defaultFeedbackPrompt: null,
    requiresFeedback: true,
    status: 'ACTIVE',
    tags: [],
    createdAt: '2026-06-11T00:00:00Z',
    updatedAt: '2026-06-11T00:00:00Z',
  };

  const templateExercise: AdminSystemMeasureTemplateExercise = {
    id: 9,
    systemExerciseId: 8,
    sortOrder: 1,
    customTitle: null,
    customInstructions: null,
    customDurationMinutes: null,
    customSets: null,
    customRepetitions: null,
    customHoldSeconds: null,
    customFeedbackPrompt: null,
    isRequired: true,
    exercise,
  };

  const template: AdminSystemMeasureTemplate = {
    id: 3,
    slug: 'reset-plan',
    title: 'Reset Plan',
    shortDescription: 'Kurz',
    description: 'Lang',
    category: 'MIXED',
    difficulty: 'BEGINNER',
    estimatedDurationMinutes: 20,
    status: 'DRAFT',
    isFeatured: false,
    exerciseCount: 1,
    exercises: [templateExercise],
    createdAt: '2026-06-11T00:00:00Z',
    updatedAt: '2026-06-11T00:00:00Z',
  };

  const listResponse = {
    data: [template],
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, from: 1, last_page: 1, per_page: 25, to: 1, total: 1 },
  };

  beforeEach(async () => {
    templateService = {
      listTemplates: vi.fn(() => of(listResponse)),
      getTemplate: vi.fn(() => of({ data: template })),
      createTemplate: vi.fn(() => of({ data: template })),
      updateTemplate: vi.fn(() => of({ data: template })),
      archiveTemplate: vi.fn(() => of({ data: { ...template, status: 'ARCHIVED' } })),
      addExercise: vi.fn(() => of({ data: templateExercise })),
      updateTemplateExercise: vi.fn(() => of({ data: templateExercise })),
      removeTemplateExercise: vi.fn(() => of(undefined)),
      reorderExercises: vi.fn(() => of({ data: [{ ...templateExercise, sortOrder: 1 }] })),
    };
    exerciseService = {
      listExercises: vi.fn(() => of({
        data: [exercise],
        links: { first: null, last: null, prev: null, next: null },
        meta: { current_page: 1, from: 1, last_page: 1, per_page: 100, to: 1, total: 1 },
      })),
    };

    await TestBed.configureTestingModule({
      imports: [AdminSystemMeasureTemplatesComponent],
      providers: [
        { provide: AdminSystemMeasureTemplateService, useValue: templateService },
        { provide: AdminSystemExerciseService, useValue: exerciseService },
        { provide: NotificationService, useValue: { success: vi.fn(), error: vi.fn() } },
      ],
    }).compileComponents();
  });

  it('loads templates and active exercises on init', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();

    expect(templateService.listTemplates).toHaveBeenCalledWith({ page: 1 });
    expect(exerciseService.listExercises).toHaveBeenCalledWith({ status: 'ACTIVE', perPage: 100 });
    expect(fixture.componentInstance.templates()).toEqual([template]);
    expect(fixture.componentInstance.availableExercises()).toEqual([exercise]);
  });

  it('reloads with filter params', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();

    fixture.componentInstance.filterForm.patchValue({
      search: 'reset',
      status: 'ACTIVE',
      category: 'MIXED',
      difficulty: 'BEGINNER',
      featured: 'true',
    });
    fixture.componentInstance.applyFilters();

    expect(templateService.listTemplates).toHaveBeenLastCalledWith({
      page: 1,
      search: 'reset',
      status: 'ACTIVE',
      category: 'MIXED',
      difficulty: 'BEGINNER',
      isFeatured: true,
    });
  });

  it('creates templates without sending a slug', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();
    const component = fixture.componentInstance;

    component.openCreate();
    component.form.patchValue({ title: 'New Template', category: 'BREATHING', status: 'DRAFT' });
    component.save();

    expect(templateService.createTemplate).toHaveBeenCalledWith(expect.objectContaining({
      title: 'New Template',
      category: 'BREATHING',
      status: 'DRAFT',
    }));
    const payload = templateService.createTemplate.mock.calls[0][0] as Record<string, unknown>;
    expect(payload).not.toHaveProperty('slug');
  });

  it('updates an existing template', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();
    const component = fixture.componentInstance;

    component.openEdit(template);
    component.form.patchValue({ title: 'Updated Template', isFeatured: true });
    component.save();

    expect(templateService.updateTemplate).toHaveBeenCalledWith(
      3,
      expect.objectContaining({ title: 'Updated Template', isFeatured: true })
    );
  });

  it('archives a template after confirmation', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    fixture.componentInstance.archive(template);

    expect(templateService.archiveTemplate).toHaveBeenCalledWith(3);
  });

  it('loads detail and adds an active exercise through the template service', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();
    const component = fixture.componentInstance;

    component.selectTemplate(template);
    component.addExerciseForm.patchValue({ systemExerciseId: 8 });
    component.addExercise();

    expect(templateService.getTemplate).toHaveBeenCalledWith(3);
    expect(templateService.addExercise).toHaveBeenCalledWith(3, { systemExerciseId: 8 });
  });

  it('updates overrides, removes relationships, and reorders exercises', () => {
    const second = { ...templateExercise, id: 10, sortOrder: 2 };
    const detail = { ...template, exercises: [templateExercise, second] };
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();
    const component = fixture.componentInstance;
    component.selectedTemplate.set(detail);
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    component.moveExercise(templateExercise, 1);
    component.selectedTemplate.set(detail);
    component.updateOverride(templateExercise, 'Custom', 'Instructions', '12', '3', '10', '30', 'Prompt', false);
    component.selectedTemplate.set(detail);
    component.removeExercise(templateExercise);

    expect(templateService.updateTemplateExercise).toHaveBeenCalledWith(3, 9, {
      customTitle: 'Custom',
      customInstructions: 'Instructions',
      customDurationMinutes: 12,
      customSets: 3,
      customRepetitions: 10,
      customHoldSeconds: 30,
      customFeedbackPrompt: 'Prompt',
      isRequired: false,
    });
    expect(templateService.removeTemplateExercise).toHaveBeenCalledWith(3, 9);
    expect(templateService.reorderExercises).toHaveBeenCalledWith(3, {
      items: [{ id: 10, sortOrder: 1 }, { id: 9, sortOrder: 2 }],
    });
  });

  it('does not render exercise creation affordances in the template builder', () => {
    const fixture = TestBed.createComponent(AdminSystemMeasureTemplatesComponent);
    fixture.detectChanges();
    fixture.componentInstance.selectTemplate(template);
    fixture.detectChanges();

    const text = (fixture.nativeElement as HTMLElement).textContent ?? '';
    expect(text).toContain('Aktive System-Übung hinzufügen');
    expect(text).not.toContain('Übung anlegen');
    expect(text).not.toContain('Neue Übung');
  });
});
