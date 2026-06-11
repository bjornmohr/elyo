import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { NotificationService } from '../../../../shared/notifications/notification.service';
import { AdminSystemExerciseService } from '../../services/admin-system-exercise.service';
import {
  AdminSystemExercise,
  AdminSystemExerciseTag,
  CreateSystemExercisePayload,
  ListSystemExercisesParams,
  PaginatedResponse,
  SystemExerciseDifficulty,
  SystemExerciseStatus,
  SystemExerciseTagCategory,
  SystemExerciseType,
} from '../../models/admin-system-exercise.models';

@Component({
  selector: 'app-admin-system-exercises',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  template: `
    <div>
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">System-Übungen</h1>
        <button type="button" (click)="openCreate()" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white" style="background: linear-gradient(135deg, #14b8a6, #0d9488)">
          + Übung anlegen
        </button>
      </div>

      <form [formGroup]="filterForm" (ngSubmit)="applyFilters()" class="bg-white rounded-xl border border-gray-200 p-4 mb-6 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <label class="block col-span-2">
          <span class="text-xs font-medium text-gray-500">Suche</span>
          <input type="text" formControlName="search" placeholder="Titel, Beschreibung, Slug…" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
        </label>
        <label class="block">
          <span class="text-xs font-medium text-gray-500">Status</span>
          <select formControlName="status" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
            <option value="">Alle</option>
            @for (status of statuses; track status) {
              <option [value]="status">{{ status }}</option>
            }
          </select>
        </label>
        <label class="block">
          <span class="text-xs font-medium text-gray-500">Typ</span>
          <select formControlName="exerciseType" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
            <option value="">Alle</option>
            @for (type of exerciseTypes; track type) {
              <option [value]="type">{{ type }}</option>
            }
          </select>
        </label>
        <label class="block">
          <span class="text-xs font-medium text-gray-500">Schwierigkeit</span>
          <select formControlName="difficulty" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
            <option value="">Alle</option>
            @for (difficulty of difficulties; track difficulty) {
              <option [value]="difficulty">{{ difficulty }}</option>
            }
          </select>
        </label>
        <label class="block">
          <span class="text-xs font-medium text-gray-500">Tag</span>
          <select formControlName="tag" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
            <option value="">Alle</option>
            @for (tag of tags(); track tag.id) {
              <option [value]="tag.category + '|' + tag.key">{{ tag.category }} · {{ tag.label }}</option>
            }
          </select>
        </label>
        <div class="col-span-2 md:col-span-3 lg:col-span-6 flex justify-end">
          <button type="submit" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700">Filtern</button>
        </div>
      </form>

      @if (formOpen()) {
        <form [formGroup]="form" (ngSubmit)="save()" class="bg-white rounded-xl border border-gray-200 p-6 mb-6 space-y-4">
          <h2 class="text-lg font-semibold text-gray-900">{{ editing() ? 'Übung bearbeiten' : 'Neue Übung' }}</h2>

          @if (editing(); as exercise) {
            <p class="text-xs text-gray-400">Slug (automatisch generiert): <span class="font-mono">{{ exercise.slug }}</span></p>
          }

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="block md:col-span-2">
              <span class="text-sm font-medium text-gray-700">Titel <span class="text-red-500">*</span></span>
              <input type="text" formControlName="title" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
            </label>
            <label class="block md:col-span-2">
              <span class="text-sm font-medium text-gray-700">Kurzbeschreibung</span>
              <input type="text" formControlName="shortDescription" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
            </label>
            <label class="block md:col-span-2">
              <span class="text-sm font-medium text-gray-700">Beschreibung</span>
              <textarea formControlName="description" rows="3" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"></textarea>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Typ <span class="text-red-500">*</span></span>
              <select formControlName="exerciseType" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
                @for (type of exerciseTypes; track type) {
                  <option [value]="type">{{ type }}</option>
                }
              </select>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Schwierigkeit <span class="text-red-500">*</span></span>
              <select formControlName="difficulty" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
                @for (difficulty of difficulties; track difficulty) {
                  <option [value]="difficulty">{{ difficulty }}</option>
                }
              </select>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Status</span>
              <select formControlName="status" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
                @for (status of statuses; track status) {
                  <option [value]="status">{{ status }}</option>
                }
              </select>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Dauer (Minuten)</span>
              <input type="number" min="1" formControlName="defaultDurationMinutes" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Sätze</span>
              <input type="number" min="1" formControlName="defaultSets" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Wiederholungen</span>
              <input type="number" min="1" formControlName="defaultRepetitions" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Haltezeit (Sekunden)</span>
              <input type="number" min="1" formControlName="defaultHoldSeconds" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
            </label>
            <label class="block md:col-span-2">
              <span class="text-sm font-medium text-gray-700">Anleitung</span>
              <textarea formControlName="instructions" rows="3" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"></textarea>
            </label>
            <label class="block md:col-span-2">
              <span class="text-sm font-medium text-gray-700">Sicherheitshinweise</span>
              <textarea formControlName="safetyNotes" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"></textarea>
            </label>
            <label class="block md:col-span-2">
              <span class="text-sm font-medium text-gray-700">Kontraindikationen</span>
              <textarea formControlName="contraindications" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"></textarea>
            </label>
            <label class="block md:col-span-2">
              <span class="text-sm font-medium text-gray-700">Feedback-Frage</span>
              <input type="text" formControlName="defaultFeedbackPrompt" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
            </label>
            <label class="flex items-center gap-2 md:col-span-2">
              <input type="checkbox" formControlName="requiresFeedback" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
              <span class="text-sm font-medium text-gray-700">Feedback erforderlich</span>
            </label>
          </div>

          <div>
            <span class="text-sm font-medium text-gray-700">Tags</span>
            <p class="text-xs text-gray-400 mb-2">Tags sind ein fester Katalog und können hier nur zugewiesen werden.</p>
            @if (tags().length === 0) {
              <p class="text-sm text-gray-500">Keine Tags vorhanden.</p>
            }
            @for (group of groupedTags(); track group.category) {
              <div class="mb-2">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ group.category }}</span>
                <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1">
                  @for (tag of group.tags; track tag.id) {
                    <label class="flex items-center gap-1.5 text-sm text-gray-700">
                      <input type="checkbox" [checked]="selectedTagIds().includes(tag.id)" (change)="toggleTag(tag.id)" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
                      {{ tag.label }}
                    </label>
                  }
                </div>
              </div>
            }
          </div>

          @if (error()) {
            <div class="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error() }}</div>
          }

          <div class="flex justify-end gap-3">
            <button type="button" (click)="closeForm()" class="px-4 py-2 rounded-lg border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Abbrechen</button>
            <button type="submit" [disabled]="saving() || form.invalid" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700 disabled:bg-gray-300">
              {{ saving() ? 'Speichern…' : 'Speichern' }}
            </button>
          </div>
        </form>
      }

      @if (loading()) {
        <div class="flex justify-center py-12">
          <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
        </div>
      } @else if (exercises().length === 0) {
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
          <p class="text-gray-500 text-sm">Keine System-Übungen gefunden.</p>
        </div>
      } @else {
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 bg-gray-50/50">
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Titel</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Typ</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Schwierigkeit</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Dauer</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Status</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Tags</th>
                <th class="text-right px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Aktionen</th>
              </tr>
            </thead>
            <tbody>
              @for (exercise of exercises(); track exercise.id) {
                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                  <td class="px-4 py-3">
                    <div class="font-medium text-gray-900">{{ exercise.title }}</div>
                    <div class="text-xs text-gray-400 font-mono">{{ exercise.slug }}</div>
                  </td>
                  <td class="px-4 py-3 text-gray-500">{{ exercise.exerciseType }}</td>
                  <td class="px-4 py-3 text-gray-500">{{ exercise.difficulty }}</td>
                  <td class="px-4 py-3 text-gray-500">{{ exercise.defaultDurationMinutes ? exercise.defaultDurationMinutes + ' min' : '–' }}</td>
                  <td class="px-4 py-3">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
                      [class]="exercise.status === 'ACTIVE' ? 'bg-green-50 text-green-700' : exercise.status === 'DRAFT' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600'">
                      {{ exercise.status }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-gray-500">
                    <div class="flex flex-wrap gap-1">
                      @for (tag of exercise.tags; track tag.id) {
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-teal-50 text-teal-700">{{ tag.label }}</span>
                      }
                    </div>
                  </td>
                  <td class="px-4 py-3 text-right whitespace-nowrap">
                    <button type="button" (click)="openEdit(exercise)" class="text-sm font-semibold text-teal-600 hover:text-teal-700 mr-3">Bearbeiten</button>
                    @if (exercise.status !== 'ARCHIVED') {
                      <button type="button" (click)="archive(exercise)" class="text-sm font-semibold text-gray-400 hover:text-red-600">Archivieren</button>
                    }
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>

        @if (meta(); as m) {
          <div class="flex items-center justify-between mt-4 text-sm text-gray-500">
            <span>Seite {{ m.current_page }} von {{ m.last_page }} ({{ m.total }} Übungen)</span>
            <div class="flex gap-2">
              <button type="button" [disabled]="m.current_page <= 1" (click)="goToPage(m.current_page - 1)" class="px-3 py-1.5 rounded-lg border border-gray-200 font-semibold hover:bg-gray-50 disabled:opacity-40">Zurück</button>
              <button type="button" [disabled]="m.current_page >= m.last_page" (click)="goToPage(m.current_page + 1)" class="px-3 py-1.5 rounded-lg border border-gray-200 font-semibold hover:bg-gray-50 disabled:opacity-40">Weiter</button>
            </div>
          </div>
        }
      }
    </div>
  `
})
export class AdminSystemExercisesComponent implements OnInit {
  private service = inject(AdminSystemExerciseService);
  private fb = inject(FormBuilder);
  private notifications = inject(NotificationService);

  exerciseTypes: SystemExerciseType[] = ['MOBILITY', 'STRENGTH', 'BREATHING', 'MINDFULNESS', 'EDUCATION', 'REFLECTION'];
  difficulties: SystemExerciseDifficulty[] = ['BEGINNER', 'INTERMEDIATE', 'ADVANCED'];
  statuses: SystemExerciseStatus[] = ['DRAFT', 'ACTIVE', 'ARCHIVED'];

  exercises = signal<AdminSystemExercise[]>([]);
  meta = signal<PaginatedResponse<AdminSystemExercise>['meta'] | null>(null);
  tags = signal<AdminSystemExerciseTag[]>([]);
  loading = signal(true);
  saving = signal(false);
  error = signal<string | null>(null);
  formOpen = signal(false);
  editing = signal<AdminSystemExercise | null>(null);
  selectedTagIds = signal<number[]>([]);
  page = signal(1);

  groupedTags = computed(() => {
    const groups = new Map<SystemExerciseTagCategory, AdminSystemExerciseTag[]>();
    for (const tag of this.tags()) {
      const group = groups.get(tag.category) ?? [];
      group.push(tag);
      groups.set(tag.category, group);
    }
    return [...groups.entries()].map(([category, tags]) => ({ category, tags }));
  });

  filterForm = this.fb.nonNullable.group({
    search: '',
    status: '',
    exerciseType: '',
    difficulty: '',
    tag: '',
  });

  form = this.fb.nonNullable.group({
    title: ['', Validators.required],
    shortDescription: '',
    description: '',
    exerciseType: 'MOBILITY' as SystemExerciseType,
    difficulty: 'BEGINNER' as SystemExerciseDifficulty,
    status: 'ACTIVE' as SystemExerciseStatus,
    defaultDurationMinutes: null as number | null,
    defaultSets: null as number | null,
    defaultRepetitions: null as number | null,
    defaultHoldSeconds: null as number | null,
    instructions: '',
    safetyNotes: '',
    contraindications: '',
    defaultFeedbackPrompt: '',
    requiresFeedback: true,
  });

  ngOnInit() {
    this.service.listTags().subscribe({
      next: res => this.tags.set(res.data),
      error: () => this.notifications.error('Tags konnten nicht geladen werden.'),
    });
    this.loadExercises();
  }

  applyFilters() {
    this.page.set(1);
    this.loadExercises();
  }

  goToPage(page: number) {
    this.page.set(page);
    this.loadExercises();
  }

  loadExercises() {
    this.loading.set(true);
    const filters = this.filterForm.getRawValue();
    const params: ListSystemExercisesParams = { page: this.page() };
    if (filters.search.trim()) params.search = filters.search.trim();
    if (filters.status) params.status = filters.status as SystemExerciseStatus;
    if (filters.exerciseType) params.exerciseType = filters.exerciseType as SystemExerciseType;
    if (filters.difficulty) params.difficulty = filters.difficulty as SystemExerciseDifficulty;
    if (filters.tag) {
      // Option value is "<category>|<key>"; both must match the same tag server-side.
      const [tagCategory, tagKey] = filters.tag.split('|');
      params.tagCategory = tagCategory as SystemExerciseTagCategory;
      params.tagKey = tagKey;
    }

    this.service.listExercises(params).subscribe({
      next: res => {
        this.exercises.set(res.data);
        this.meta.set(res.meta);
        this.loading.set(false);
      },
      error: () => {
        this.notifications.error('System-Übungen konnten nicht geladen werden.');
        this.loading.set(false);
      },
    });
  }

  openCreate() {
    this.editing.set(null);
    this.selectedTagIds.set([]);
    this.form.reset();
    this.error.set(null);
    this.formOpen.set(true);
  }

  openEdit(exercise: AdminSystemExercise) {
    this.editing.set(exercise);
    this.selectedTagIds.set(exercise.tags.map(tag => tag.id));
    this.form.reset();
    this.form.patchValue({
      title: exercise.title,
      shortDescription: exercise.shortDescription ?? '',
      description: exercise.description ?? '',
      exerciseType: exercise.exerciseType,
      difficulty: exercise.difficulty,
      status: exercise.status,
      defaultDurationMinutes: exercise.defaultDurationMinutes,
      defaultSets: exercise.defaultSets,
      defaultRepetitions: exercise.defaultRepetitions,
      defaultHoldSeconds: exercise.defaultHoldSeconds,
      instructions: exercise.instructions ?? '',
      safetyNotes: exercise.safetyNotes ?? '',
      contraindications: exercise.contraindications ?? '',
      defaultFeedbackPrompt: exercise.defaultFeedbackPrompt ?? '',
      requiresFeedback: exercise.requiresFeedback,
    });
    this.error.set(null);
    this.formOpen.set(true);
  }

  closeForm() {
    this.formOpen.set(false);
    this.editing.set(null);
  }

  toggleTag(tagId: number) {
    this.selectedTagIds.update(ids =>
      ids.includes(tagId) ? ids.filter(id => id !== tagId) : [...ids, tagId]
    );
  }

  buildPayload(): CreateSystemExercisePayload {
    const value = this.form.getRawValue();
    return {
      title: value.title,
      shortDescription: value.shortDescription.trim() || null,
      description: value.description.trim() || null,
      exerciseType: value.exerciseType,
      difficulty: value.difficulty,
      status: value.status,
      defaultDurationMinutes: value.defaultDurationMinutes,
      defaultSets: value.defaultSets,
      defaultRepetitions: value.defaultRepetitions,
      defaultHoldSeconds: value.defaultHoldSeconds,
      instructions: value.instructions.trim() || null,
      safetyNotes: value.safetyNotes.trim() || null,
      contraindications: value.contraindications.trim() || null,
      defaultFeedbackPrompt: value.defaultFeedbackPrompt.trim() || null,
      requiresFeedback: value.requiresFeedback,
      tagIds: this.selectedTagIds(),
    };
  }

  save() {
    this.error.set(null);
    if (this.form.invalid) return;

    this.saving.set(true);
    const payload = this.buildPayload();
    const editing = this.editing();
    const request$ = editing
      ? this.service.updateExercise(editing.id, payload)
      : this.service.createExercise(payload);

    request$.subscribe({
      next: () => {
        this.notifications.success(editing ? 'Übung wurde aktualisiert.' : 'Übung wurde angelegt.');
        this.saving.set(false);
        this.closeForm();
        this.loadExercises();
      },
      error: err => {
        const message = err.error?.message ?? 'Übung konnte nicht gespeichert werden.';
        this.error.set(message);
        this.notifications.error(message);
        this.saving.set(false);
      },
    });
  }

  archive(exercise: AdminSystemExercise) {
    if (!confirm(`Übung "${exercise.title}" archivieren? Sie bleibt in Vorlagen und Zuweisungen erhalten.`)) return;

    this.service.archiveExercise(exercise.id).subscribe({
      next: () => {
        this.notifications.success('Übung wurde archiviert.');
        this.loadExercises();
      },
      error: () => this.notifications.error('Übung konnte nicht archiviert werden.'),
    });
  }
}
