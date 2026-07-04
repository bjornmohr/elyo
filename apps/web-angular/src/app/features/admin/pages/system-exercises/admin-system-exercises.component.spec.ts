import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { NotificationService } from '../../../../shared/notifications/notification.service';
import { AdminSystemExerciseService } from '../../services/admin-system-exercise.service';
import {
  AdminSystemExercise,
  AdminSystemExerciseTag,
} from '../../models/admin-system-exercise.models';
import { AdminSystemExercisesComponent } from './admin-system-exercises.component';

describe('AdminSystemExercisesComponent', () => {
  let service: {
    listExercises: ReturnType<typeof vi.fn>;
    getExercise: ReturnType<typeof vi.fn>;
    createExercise: ReturnType<typeof vi.fn>;
    updateExercise: ReturnType<typeof vi.fn>;
    archiveExercise: ReturnType<typeof vi.fn>;
    listTags: ReturnType<typeof vi.fn>;
  };

  const tags: AdminSystemExerciseTag[] = [
    { id: 1, category: 'BODY_REGION', key: 'NECK', label: 'Nacken', description: null, sortOrder: 1, isActive: true },
    { id: 2, category: 'GOAL', key: 'RELAX', label: 'Entspannung', description: null, sortOrder: 1, isActive: true },
  ];

  const exercise: AdminSystemExercise = {
    id: 10,
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
    steps: [],
    mainPictogramPath: null,
    mainPictogramAlt: null,
    locationTags: null,
    postureTags: null,
    requiresFloor: null,
    defaultEffort: null,
    status: 'ACTIVE',
    tags: [tags[0]],
    createdAt: '2026-06-11T00:00:00Z',
    updatedAt: '2026-06-11T00:00:00Z',
  };

  const listResponse = {
    data: [exercise],
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, from: 1, last_page: 1, per_page: 25, to: 1, total: 1 },
  };

  beforeEach(async () => {
    service = {
      listExercises: vi.fn(() => of(listResponse)),
      getExercise: vi.fn(() => of({ data: exercise })),
      createExercise: vi.fn(() => of({ data: exercise })),
      updateExercise: vi.fn(() => of({ data: exercise })),
      archiveExercise: vi.fn(() => of({ data: { ...exercise, status: 'ARCHIVED' } })),
      listTags: vi.fn(() => of({ data: tags })),
    };

    await TestBed.configureTestingModule({
      imports: [AdminSystemExercisesComponent],
      providers: [
        { provide: AdminSystemExerciseService, useValue: service },
        {
          provide: NotificationService,
          useValue: { success: vi.fn(), error: vi.fn() },
        },
      ],
    }).compileComponents();
  });

  it('loads exercises and tags on init', () => {
    const fixture = TestBed.createComponent(AdminSystemExercisesComponent);
    fixture.detectChanges();

    expect(service.listExercises).toHaveBeenCalledWith({ page: 1 });
    expect(service.listTags).toHaveBeenCalled();
    expect(fixture.componentInstance.exercises()).toEqual([exercise]);
    expect(fixture.componentInstance.tags()).toEqual(tags);
  });

  it('reloads with filter params when filters are applied', () => {
    const fixture = TestBed.createComponent(AdminSystemExercisesComponent);
    fixture.detectChanges();

    fixture.componentInstance.filterForm.patchValue({
      search: 'nacken',
      status: 'ACTIVE',
      exerciseType: 'MOBILITY',
      difficulty: 'BEGINNER',
      tag: 'BODY_REGION|NECK',
    });
    fixture.componentInstance.applyFilters();

    expect(service.listExercises).toHaveBeenLastCalledWith({
      page: 1,
      search: 'nacken',
      status: 'ACTIVE',
      exerciseType: 'MOBILITY',
      difficulty: 'BEGINNER',
      tagCategory: 'BODY_REGION',
      tagKey: 'NECK',
    });
  });

  it('creates an exercise with the form payload including selected tag ids', () => {
    const fixture = TestBed.createComponent(AdminSystemExercisesComponent);
    fixture.detectChanges();
    const component = fixture.componentInstance;

    component.openCreate();
    component.form.patchValue({
      title: 'Neue Übung',
      exerciseType: 'BREATHING',
      difficulty: 'INTERMEDIATE',
      status: 'DRAFT',
      defaultDurationMinutes: 10,
    });
    component.toggleTag(1);
    component.toggleTag(2);
    component.save();

    expect(service.createExercise).toHaveBeenCalledWith(
      expect.objectContaining({
        title: 'Neue Übung',
        exerciseType: 'BREATHING',
        difficulty: 'INTERMEDIATE',
        status: 'DRAFT',
        defaultDurationMinutes: 10,
        requiresFeedback: true,
        tagIds: [1, 2],
      })
    );
    expect(service.updateExercise).not.toHaveBeenCalled();
  });

  it('does not include a slug field in the create payload', () => {
    const fixture = TestBed.createComponent(AdminSystemExercisesComponent);
    fixture.detectChanges();
    const component = fixture.componentInstance;

    component.openCreate();
    component.form.patchValue({ title: 'Ohne Slug' });
    component.save();

    const payload = service.createExercise.mock.calls[0][0] as Record<string, unknown>;
    expect(payload).not.toHaveProperty('slug');
  });

  it('updates an existing exercise in edit mode and syncs tags exactly', () => {
    const fixture = TestBed.createComponent(AdminSystemExercisesComponent);
    fixture.detectChanges();
    const component = fixture.componentInstance;

    component.openEdit(exercise);
    expect(component.selectedTagIds()).toEqual([1]);

    component.form.patchValue({ title: 'Geänderter Titel' });
    component.toggleTag(1);
    component.toggleTag(2);
    component.save();

    expect(service.updateExercise).toHaveBeenCalledWith(
      10,
      expect.objectContaining({
        title: 'Geänderter Titel',
        tagIds: [2],
      })
    );
    expect(service.createExercise).not.toHaveBeenCalled();
  });

  it('archives an exercise after confirmation and reloads the list', () => {
    const fixture = TestBed.createComponent(AdminSystemExercisesComponent);
    fixture.detectChanges();
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    fixture.componentInstance.archive(exercise);

    expect(service.archiveExercise).toHaveBeenCalledWith(10);
    expect(service.listExercises).toHaveBeenCalledTimes(2);
  });

  it('does not archive when the confirmation is declined', () => {
    const fixture = TestBed.createComponent(AdminSystemExercisesComponent);
    fixture.detectChanges();
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    fixture.componentInstance.archive(exercise);

    expect(service.archiveExercise).not.toHaveBeenCalled();
  });

  it('renders tags as selectable checkboxes without create or edit affordances', () => {
    const fixture = TestBed.createComponent(AdminSystemExercisesComponent);
    fixture.detectChanges();
    fixture.componentInstance.openCreate();
    fixture.detectChanges();

    const element: HTMLElement = fixture.nativeElement;
    const tagCheckboxes = element.querySelectorAll('input[type="checkbox"]');
    // requiresFeedback + 4 location tags + 2 posture tags + one checkbox per catalog tag
    expect(tagCheckboxes.length).toBe(7 + tags.length);
    expect(element.textContent).toContain('Nacken');
    expect(element.textContent).toContain('Entspannung');
    expect(element.textContent).not.toContain('Tag anlegen');
    expect(element.textContent).not.toContain('Tag bearbeiten');
  });
});
