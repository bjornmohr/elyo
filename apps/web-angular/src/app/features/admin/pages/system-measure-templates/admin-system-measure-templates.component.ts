import { CommonModule } from '@angular/common';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { NotificationService } from '../../../../shared/notifications/notification.service';
import { AdminSystemExerciseService } from '../../services/admin-system-exercise.service';
import { AdminSystemMeasureTemplateService } from '../../services/admin-system-measure-template.service';
import { AdminSystemExercise } from '../../models/admin-system-exercise.models';
import {
  AdminSystemMeasureTemplate,
  AdminSystemMeasureTemplateExercise,
  AdminSystemMeasureTemplateListResponse,
  CreateSystemMeasureTemplatePayload,
  ListSystemMeasureTemplatesParams,
  SystemMeasureTemplateCategory,
  SystemMeasureTemplateDifficulty,
  SystemMeasureTemplateStatus,
  UpdateSystemMeasureTemplateExercisePayload,
} from '../../models/admin-system-measure-template.models';

@Component({
  selector: 'app-admin-system-measure-templates',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  template: `
    <div>
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">System-Templates</h1>
        <button type="button" (click)="openCreate()" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white" style="background: linear-gradient(135deg, #14b8a6, #0d9488)">
          + Template anlegen
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
          <span class="text-xs font-medium text-gray-500">Kategorie</span>
          <select formControlName="category" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
            <option value="">Alle</option>
            @for (category of categories; track category) {
              <option [value]="category">{{ category }}</option>
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
          <span class="text-xs font-medium text-gray-500">Featured</span>
          <select formControlName="featured" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
            <option value="">Alle</option>
            <option value="true">Ja</option>
            <option value="false">Nein</option>
          </select>
        </label>
        <div class="col-span-2 md:col-span-3 lg:col-span-6 flex justify-end">
          <button type="submit" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700">Filtern</button>
        </div>
      </form>

      @if (formOpen()) {
        <form [formGroup]="form" (ngSubmit)="save()" class="bg-white rounded-xl border border-gray-200 p-6 mb-6 space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">{{ editing() ? 'Template bearbeiten' : 'Neues Template' }}</h2>
            @if (editing(); as template) {
              <p class="text-xs text-gray-400">Slug: <span class="font-mono">{{ template.slug }}</span></p>
            }
          </div>

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
              <span class="text-sm font-medium text-gray-700">Kategorie</span>
              <select formControlName="category" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
                @for (category of categories; track category) {
                  <option [value]="category">{{ category }}</option>
                }
              </select>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Schwierigkeit</span>
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
              <input type="number" min="1" formControlName="estimatedDurationMinutes" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
            </label>
            <label class="flex items-center gap-2 md:col-span-2">
              <input type="checkbox" formControlName="isFeatured" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
              <span class="text-sm font-medium text-gray-700">Featured</span>
            </label>
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

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <section>
          @if (loading()) {
            <div class="flex justify-center py-12">
              <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
            </div>
          } @else if (templates().length === 0) {
            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
              <p class="text-gray-500 text-sm">Keine System-Templates gefunden.</p>
            </div>
          } @else {
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-gray-100 bg-gray-50/50">
                    <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Titel</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Kategorie</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Status</th>
                    <th class="text-right px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Aktionen</th>
                  </tr>
                </thead>
                <tbody>
                  @for (template of templates(); track template.id) {
                    <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors" [class.bg-teal-50]="selectedTemplate()?.id === template.id">
                      <td class="px-4 py-3">
                        <button type="button" (click)="selectTemplate(template)" class="text-left">
                          <div class="font-medium text-gray-900">{{ template.title }}</div>
                          <div class="text-xs text-gray-400 font-mono">{{ template.slug }}</div>
                          <div class="text-xs text-gray-500">{{ template.exerciseCount ?? 0 }} Übungen</div>
                        </button>
                      </td>
                      <td class="px-4 py-3 text-gray-500">{{ template.category }}</td>
                      <td class="px-4 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
                          [class]="template.status === 'ACTIVE' ? 'bg-green-50 text-green-700' : template.status === 'DRAFT' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600'">
                          {{ template.status }}
                        </span>
                      </td>
                      <td class="px-4 py-3 text-right whitespace-nowrap">
                        <button type="button" (click)="openEdit(template)" class="text-sm font-semibold text-teal-600 hover:text-teal-700 mr-3">Bearbeiten</button>
                        @if (template.status !== 'ARCHIVED') {
                          <button type="button" (click)="archive(template)" class="text-sm font-semibold text-gray-400 hover:text-red-600">Archivieren</button>
                        }
                      </td>
                    </tr>
                  }
                </tbody>
              </table>
            </div>
          }
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-5">
          @if (selectedTemplate(); as template) {
            <div class="flex items-start justify-between gap-4 mb-4">
              <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ template.title }}</h2>
                <p class="text-xs text-gray-400 font-mono">{{ template.slug }}</p>
              </div>
              <button type="button" (click)="selectTemplate(template)" class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Neu laden</button>
            </div>

            <form [formGroup]="addExerciseForm" (ngSubmit)="addExercise()" class="border border-gray-100 rounded-lg p-3 mb-4 grid grid-cols-1 md:grid-cols-[1fr_auto] gap-3">
              <label class="block">
                <span class="text-xs font-medium text-gray-500">Aktive System-Übung hinzufügen</span>
                <select formControlName="systemExerciseId" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
                  <option [ngValue]="null">Bitte wählen</option>
                  @for (exercise of availableExercises(); track exercise.id) {
                    <option [ngValue]="exercise.id">{{ exercise.title }} · {{ exercise.exerciseType }}</option>
                  }
                </select>
              </label>
              <button type="submit" [disabled]="addExerciseForm.invalid || savingExercise()" class="self-end px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700 disabled:bg-gray-300">Hinzufügen</button>
            </form>

            @if ((template.exercises ?? []).length === 0) {
              <p class="text-sm text-gray-500">Dieses Template enthält noch keine Übungen.</p>
            }
            @for (templateExercise of template.exercises ?? []; track templateExercise.id) {
              <div class="border border-gray-100 rounded-lg p-3 mb-3">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <div class="text-xs font-semibold text-gray-400">#{{ templateExercise.sortOrder }}</div>
                    <div class="font-medium text-gray-900">{{ templateExercise.exercise.title }}</div>
                    <div class="text-xs text-gray-500">{{ templateExercise.exercise.exerciseType }} · {{ templateExercise.exercise.difficulty }}</div>
                  </div>
                  <div class="flex gap-2">
                    <button type="button" (click)="moveExercise(templateExercise, -1)" class="px-2 py-1 rounded border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50">↑</button>
                    <button type="button" (click)="moveExercise(templateExercise, 1)" class="px-2 py-1 rounded border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50">↓</button>
                    <button type="button" (click)="removeExercise(templateExercise)" class="px-2 py-1 rounded border border-gray-200 text-xs font-semibold text-gray-500 hover:text-red-600">Entfernen</button>
                  </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-3">
                  <input #customTitle type="text" [value]="templateExercise.customTitle ?? ''" placeholder="Eigener Titel" class="rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
                  <input #customDuration type="number" min="1" [value]="templateExercise.customDurationMinutes ?? ''" placeholder="Dauer" class="rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
                  <input #customSets type="number" min="1" [value]="templateExercise.customSets ?? ''" placeholder="Sätze" class="rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
                  <input #customRepetitions type="number" min="1" [value]="templateExercise.customRepetitions ?? ''" placeholder="Wiederholungen" class="rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
                  <input #customHold type="number" min="1" [value]="templateExercise.customHoldSeconds ?? ''" placeholder="Haltezeit" class="rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
                  <input #customPrompt type="text" [value]="templateExercise.customFeedbackPrompt ?? ''" placeholder="Feedback-Frage" class="rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
                  <textarea #customInstructions [value]="templateExercise.customInstructions ?? ''" rows="2" placeholder="Eigene Anleitung" class="md:col-span-2 rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"></textarea>
                  <label class="flex items-center gap-2">
                    <input #isRequired type="checkbox" [checked]="templateExercise.isRequired" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
                    <span class="text-sm text-gray-700">Erforderlich</span>
                  </label>
                  <div class="text-right">
                    <button type="button" (click)="updateOverride(templateExercise, customTitle.value, customInstructions.value, customDuration.value, customSets.value, customRepetitions.value, customHold.value, customPrompt.value, isRequired.checked)" class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm font-semibold text-teal-600 hover:bg-gray-50">
                      Overrides speichern
                    </button>
                  </div>
                </div>
              </div>
            }
          } @else {
            <p class="text-sm text-gray-500">Template auswählen, um die Übungssequenz zu bearbeiten.</p>
          }
        </section>
      </div>
    </div>
  `
})
export class AdminSystemMeasureTemplatesComponent implements OnInit {
  private templateService = inject(AdminSystemMeasureTemplateService);
  private exerciseService = inject(AdminSystemExerciseService);
  private fb = inject(FormBuilder);
  private notifications = inject(NotificationService);

  categories: SystemMeasureTemplateCategory[] = ['MOBILITY', 'STRENGTH', 'BREATHING', 'MINDFULNESS', 'EDUCATION', 'REFLECTION', 'MIXED'];
  difficulties: SystemMeasureTemplateDifficulty[] = ['BEGINNER', 'INTERMEDIATE', 'ADVANCED'];
  statuses: SystemMeasureTemplateStatus[] = ['DRAFT', 'ACTIVE', 'ARCHIVED'];

  templates = signal<AdminSystemMeasureTemplate[]>([]);
  meta = signal<AdminSystemMeasureTemplateListResponse['meta'] | null>(null);
  selectedTemplate = signal<AdminSystemMeasureTemplate | null>(null);
  availableExercises = signal<AdminSystemExercise[]>([]);
  loading = signal(true);
  saving = signal(false);
  savingExercise = signal(false);
  error = signal<string | null>(null);
  formOpen = signal(false);
  editing = signal<AdminSystemMeasureTemplate | null>(null);
  page = signal(1);

  filterForm = this.fb.nonNullable.group({
    search: '',
    status: '',
    category: '',
    difficulty: '',
    featured: '',
  });

  form = this.fb.nonNullable.group({
    title: ['', Validators.required],
    shortDescription: '',
    description: '',
    category: 'MIXED' as SystemMeasureTemplateCategory,
    difficulty: 'BEGINNER' as SystemMeasureTemplateDifficulty,
    status: 'DRAFT' as SystemMeasureTemplateStatus,
    estimatedDurationMinutes: null as number | null,
    isFeatured: false,
  });

  addExerciseForm = this.fb.group({
    systemExerciseId: [null as number | null, Validators.required],
  });

  ngOnInit() {
    this.loadTemplates();
    this.loadActiveExercises();
  }

  applyFilters() {
    this.page.set(1);
    this.loadTemplates();
  }

  loadTemplates() {
    this.loading.set(true);
    const filters = this.filterForm.getRawValue();
    const params: ListSystemMeasureTemplatesParams = { page: this.page() };
    if (filters.search.trim()) params.search = filters.search.trim();
    if (filters.status) params.status = filters.status as SystemMeasureTemplateStatus;
    if (filters.category) params.category = filters.category as SystemMeasureTemplateCategory;
    if (filters.difficulty) params.difficulty = filters.difficulty as SystemMeasureTemplateDifficulty;
    if (filters.featured) params.isFeatured = filters.featured === 'true';

    this.templateService.listTemplates(params).subscribe({
      next: res => {
        this.templates.set(res.data);
        this.meta.set(res.meta);
        this.loading.set(false);
      },
      error: () => {
        this.notifications.error('System-Templates konnten nicht geladen werden.');
        this.loading.set(false);
      },
    });
  }

  loadActiveExercises() {
    this.exerciseService.listExercises({ status: 'ACTIVE', perPage: 100 }).subscribe({
      next: res => this.availableExercises.set(res.data),
      error: () => this.notifications.error('Aktive System-Übungen konnten nicht geladen werden.'),
    });
  }

  selectTemplate(template: AdminSystemMeasureTemplate) {
    this.templateService.getTemplate(template.id).subscribe({
      next: res => this.selectedTemplate.set(res.data),
      error: () => this.notifications.error('Template-Details konnten nicht geladen werden.'),
    });
  }

  openCreate() {
    this.editing.set(null);
    this.form.reset();
    this.error.set(null);
    this.formOpen.set(true);
  }

  openEdit(template: AdminSystemMeasureTemplate) {
    this.editing.set(template);
    this.form.reset();
    this.form.patchValue({
      title: template.title,
      shortDescription: template.shortDescription ?? '',
      description: template.description ?? '',
      category: template.category,
      difficulty: template.difficulty,
      status: template.status,
      estimatedDurationMinutes: template.estimatedDurationMinutes,
      isFeatured: template.isFeatured,
    });
    this.error.set(null);
    this.formOpen.set(true);
  }

  closeForm() {
    this.formOpen.set(false);
    this.editing.set(null);
  }

  buildPayload(): CreateSystemMeasureTemplatePayload {
    const value = this.form.getRawValue();
    return {
      title: value.title,
      shortDescription: value.shortDescription.trim() || null,
      description: value.description.trim() || null,
      category: value.category,
      difficulty: value.difficulty,
      estimatedDurationMinutes: value.estimatedDurationMinutes,
      status: value.status,
      isFeatured: value.isFeatured,
    };
  }

  save() {
    this.error.set(null);
    if (this.form.invalid) return;

    this.saving.set(true);
    const editing = this.editing();
    const request$ = editing
      ? this.templateService.updateTemplate(editing.id, this.buildPayload())
      : this.templateService.createTemplate(this.buildPayload());

    request$.subscribe({
      next: res => {
        this.notifications.success(editing ? 'Template wurde aktualisiert.' : 'Template wurde angelegt.');
        this.saving.set(false);
        this.closeForm();
        this.loadTemplates();
        this.selectTemplate(res.data);
      },
      error: err => {
        const message = err.error?.message ?? 'Template konnte nicht gespeichert werden.';
        this.error.set(message);
        this.notifications.error(message);
        this.saving.set(false);
      },
    });
  }

  archive(template: AdminSystemMeasureTemplate) {
    if (!confirm(`Template "${template.title}" archivieren? Zugeordnete Übungen bleiben erhalten.`)) return;

    this.templateService.archiveTemplate(template.id).subscribe({
      next: res => {
        this.notifications.success('Template wurde archiviert.');
        this.loadTemplates();
        if (this.selectedTemplate()?.id === template.id) this.selectedTemplate.set(res.data);
      },
      error: () => this.notifications.error('Template konnte nicht archiviert werden.'),
    });
  }

  addExercise() {
    const template = this.selectedTemplate();
    const systemExerciseId = this.addExerciseForm.value.systemExerciseId;
    if (!template || !systemExerciseId) return;

    this.savingExercise.set(true);
    this.templateService.addExercise(template.id, { systemExerciseId }).subscribe({
      next: () => {
        this.notifications.success('Übung wurde hinzugefügt.');
        this.savingExercise.set(false);
        this.addExerciseForm.reset();
        this.selectTemplate(template);
        this.loadTemplates();
      },
      error: err => {
        this.notifications.error(err.error?.message ?? 'Übung konnte nicht hinzugefügt werden.');
        this.savingExercise.set(false);
      },
    });
  }

  updateOverride(
    templateExercise: AdminSystemMeasureTemplateExercise,
    customTitle: string,
    customInstructions: string,
    customDurationMinutes: string,
    customSets: string,
    customRepetitions: string,
    customHoldSeconds: string,
    customFeedbackPrompt: string,
    isRequired: boolean,
  ) {
    const template = this.selectedTemplate();
    if (!template) return;

    const payload: UpdateSystemMeasureTemplateExercisePayload = {
      customTitle: customTitle.trim() || null,
      customInstructions: customInstructions.trim() || null,
      customDurationMinutes: this.toNullableNumber(customDurationMinutes),
      customSets: this.toNullableNumber(customSets),
      customRepetitions: this.toNullableNumber(customRepetitions),
      customHoldSeconds: this.toNullableNumber(customHoldSeconds),
      customFeedbackPrompt: customFeedbackPrompt.trim() || null,
      isRequired,
    };

    this.templateService.updateTemplateExercise(template.id, templateExercise.id, payload).subscribe({
      next: () => {
        this.notifications.success('Overrides wurden gespeichert.');
        this.selectTemplate(template);
      },
      error: () => this.notifications.error('Overrides konnten nicht gespeichert werden.'),
    });
  }

  removeExercise(templateExercise: AdminSystemMeasureTemplateExercise) {
    const template = this.selectedTemplate();
    if (!template) return;
    if (!confirm(`Übung "${templateExercise.exercise.title}" aus dem Template entfernen?`)) return;

    this.templateService.removeTemplateExercise(template.id, templateExercise.id).subscribe({
      next: () => {
        this.notifications.success('Übung wurde aus dem Template entfernt.');
        this.selectTemplate(template);
        this.loadTemplates();
      },
      error: () => this.notifications.error('Übung konnte nicht entfernt werden.'),
    });
  }

  moveExercise(templateExercise: AdminSystemMeasureTemplateExercise, direction: -1 | 1) {
    const template = this.selectedTemplate();
    const exercises = [...(template?.exercises ?? [])].sort((a, b) => a.sortOrder - b.sortOrder);
    const index = exercises.findIndex(item => item.id === templateExercise.id);
    const targetIndex = index + direction;
    if (!template || index < 0 || targetIndex < 0 || targetIndex >= exercises.length) return;

    [exercises[index], exercises[targetIndex]] = [exercises[targetIndex], exercises[index]];
    const payload = { items: exercises.map((item, itemIndex) => ({ id: item.id, sortOrder: itemIndex + 1 })) };

    this.templateService.reorderExercises(template.id, payload).subscribe({
      next: res => {
        this.notifications.success('Reihenfolge wurde gespeichert.');
        this.selectedTemplate.set({ ...template, exercises: res.data });
      },
      error: () => this.notifications.error('Reihenfolge konnte nicht gespeichert werden.'),
    });
  }

  private toNullableNumber(value: string): number | null {
    const trimmed = value.trim();
    return trimmed === '' ? null : Number(trimmed);
  }
}
