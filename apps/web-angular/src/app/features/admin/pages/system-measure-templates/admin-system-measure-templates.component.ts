import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { NotificationService } from '../../../../shared/notifications/notification.service';
import { AdminSystemExerciseService } from '../../services/admin-system-exercise.service';
import { AdminSystemMeasureTemplateService } from '../../services/admin-system-measure-template.service';
import {
  AdminSystemExercise,
  ExerciseLocationTag,
  ExercisePostureTag,
  PaginatedResponse,
} from '../../models/admin-system-exercise.models';
import {
  AdminSystemMeasureTemplate,
  AdminSystemMeasureTemplateExercise,
  CreateSystemMeasureTemplatePayload,
  ListSystemMeasureTemplatesParams,
  MeasureEffectMetric,
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
          <span class="text-xs font-medium text-gray-500">Empfohlen</span>
          <select formControlName="isFeatured" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
            <option value="">Alle</option>
            <option value="true">Nur empfohlene</option>
            <option value="false">Nicht empfohlene</option>
          </select>
        </label>
        <div class="col-span-2 md:col-span-3 lg:col-span-6 flex justify-end">
          <button type="submit" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700">Filtern</button>
        </div>
      </form>

      @if (formOpen()) {
        <form [formGroup]="form" (ngSubmit)="save()" class="bg-white rounded-xl border border-gray-200 p-6 mb-6 space-y-4">
          <h2 class="text-lg font-semibold text-gray-900">{{ editing() ? 'Template bearbeiten' : 'Neues Template' }}</h2>

          @if (editing(); as template) {
            <p class="text-xs text-gray-400">Slug (automatisch generiert): <span class="font-mono">{{ template.slug }}</span></p>
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
              <span class="text-sm font-medium text-gray-700">Geschätzte Dauer (Minuten)</span>
              <input type="number" min="1" formControlName="estimatedDurationMinutes" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
            </label>
            <label class="flex items-center gap-2 md:col-span-2">
              <input type="checkbox" formControlName="isFeatured" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
              <span class="text-sm font-medium text-gray-700">Empfohlenes Template</span>
            </label>

            <label class="block">
              <span class="text-sm font-medium text-gray-700">Ziel-Signal</span>
              <input type="text" formControlName="targetSignal" placeholder="z.B. neck_pain, sleep, stress" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500 font-mono" />
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Zuweisungsgrund-Vorlage</span>
              <input type="text" formControlName="assignmentReasonTemplate" placeholder="aus Check-in „Nackenschmerzen“" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Effekt-Metrik (Vorher/Nachher)</span>
              <select formControlName="effectMetric" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
                <option value="">Keine</option>
                <option value="pain">Schmerz</option>
                <option value="stress">Stress</option>
                <option value="sleep_hours">Schlafstunden</option>
              </select>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Effekt-Einheit</span>
              <input type="text" formControlName="effectMetricUnit" placeholder="z.B. nrs_0_10, scale_1_5, hours" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500 font-mono" />
            </label>
            <div>
              <span class="text-sm font-medium text-gray-700">Eignung: Ort</span>
              <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1">
                @for (option of locationOptions; track option.value) {
                  <label class="flex items-center gap-1.5 text-sm text-gray-700">
                    <input type="checkbox" [checked]="selectedLocationTags().includes(option.value)" (change)="toggleLocationTag(option.value)" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
                    {{ option.label }}
                  </label>
                }
              </div>
            </div>
            <div>
              <span class="text-sm font-medium text-gray-700">Eignung: Haltung</span>
              <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1">
                @for (option of postureOptions; track option.value) {
                  <label class="flex items-center gap-1.5 text-sm text-gray-700">
                    <input type="checkbox" [checked]="selectedPostureTags().includes(option.value)" (change)="togglePostureTag(option.value)" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
                    {{ option.label }}
                  </label>
                }
              </div>
            </div>
            <label class="flex items-center gap-2 md:col-span-2">
              <input type="checkbox" formControlName="requiresFloor" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
              <span class="text-sm font-medium text-gray-700">Bodenübungen enthalten (im Büro/Werk ausgeblendet)</span>
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

      @if (detail(); as template) {
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6 space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Übungen: {{ template.title }}</h2>
            <button type="button" (click)="closeDetail()" class="text-sm font-semibold text-gray-400 hover:text-gray-600">Schließen</button>
          </div>

          @if (detailExercises().length === 0) {
            <p class="text-sm text-gray-500">Noch keine Übungen im Template.</p>
          } @else {
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-100 bg-gray-50/50">
                  <th class="text-left px-3 py-2 font-semibold text-gray-500 text-xs uppercase tracking-wider">Reihenfolge</th>
                  <th class="text-left px-3 py-2 font-semibold text-gray-500 text-xs uppercase tracking-wider">Übung</th>
                  <th class="text-left px-3 py-2 font-semibold text-gray-500 text-xs uppercase tracking-wider">Pflicht</th>
                  <th class="text-right px-3 py-2 font-semibold text-gray-500 text-xs uppercase tracking-wider">Aktionen</th>
                </tr>
              </thead>
              <tbody>
                @for (item of detailExercises(); track item.id; let first = $first; let last = $last) {
                  <tr class="border-b border-gray-50">
                    <td class="px-3 py-2 text-gray-500 whitespace-nowrap">
                      {{ item.sortOrder }}
                      <button type="button" [disabled]="first" (click)="moveUp(item)" class="ml-2 text-teal-600 disabled:text-gray-300" aria-label="Nach oben">↑</button>
                      <button type="button" [disabled]="last" (click)="moveDown(item)" class="text-teal-600 disabled:text-gray-300" aria-label="Nach unten">↓</button>
                    </td>
                    <td class="px-3 py-2">
                      <div class="font-medium text-gray-900">{{ item.customTitle || item.exercise?.title }}</div>
                      <div class="text-xs text-gray-400">{{ item.exercise?.exerciseType }} · {{ item.exercise?.difficulty }}</div>
                    </td>
                    <td class="px-3 py-2 text-gray-500">{{ item.isRequired ? 'Ja' : 'Nein' }}</td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">
                      <button type="button" (click)="openOverrides(item)" class="text-sm font-semibold text-teal-600 hover:text-teal-700 mr-3">Anpassen</button>
                      <button type="button" (click)="removeExercise(item)" class="text-sm font-semibold text-gray-400 hover:text-red-600">Entfernen</button>
                    </td>
                  </tr>
                }
              </tbody>
            </table>
          }

          @if (overridesFor(); as item) {
            <form [formGroup]="overridesForm" (ngSubmit)="saveOverrides()" class="border border-gray-100 rounded-lg p-4 space-y-3">
              <h3 class="text-sm font-semibold text-gray-900">Anpassungen: {{ item.exercise?.title }}</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="block md:col-span-2">
                  <span class="text-xs font-medium text-gray-500">Eigener Titel</span>
                  <input type="text" formControlName="customTitle" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
                </label>
                <label class="block md:col-span-2">
                  <span class="text-xs font-medium text-gray-500">Eigene Anleitung</span>
                  <textarea formControlName="customInstructions" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"></textarea>
                </label>
                <label class="block">
                  <span class="text-xs font-medium text-gray-500">Dauer (Minuten)</span>
                  <input type="number" min="1" formControlName="customDurationMinutes" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
                </label>
                <label class="block">
                  <span class="text-xs font-medium text-gray-500">Sätze</span>
                  <input type="number" min="1" formControlName="customSets" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
                </label>
                <label class="block">
                  <span class="text-xs font-medium text-gray-500">Wiederholungen</span>
                  <input type="number" min="1" formControlName="customRepetitions" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
                </label>
                <label class="block">
                  <span class="text-xs font-medium text-gray-500">Haltezeit (Sekunden)</span>
                  <input type="number" min="1" formControlName="customHoldSeconds" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
                </label>
                <label class="block md:col-span-2">
                  <span class="text-xs font-medium text-gray-500">Feedback-Frage</span>
                  <input type="text" formControlName="customFeedbackPrompt" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
                </label>
                <label class="flex items-center gap-2 md:col-span-2">
                  <input type="checkbox" formControlName="isRequired" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
                  <span class="text-xs font-medium text-gray-500">Pflichtübung</span>
                </label>
              </div>
              <div class="flex justify-end gap-3">
                <button type="button" (click)="closeOverrides()" class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Abbrechen</button>
                <button type="submit" class="px-3 py-1.5 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700">Übernehmen</button>
              </div>
            </form>
          }

          <div class="border-t border-gray-100 pt-4">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Übung aus dem Katalog hinzufügen</h3>
            <p class="text-xs text-gray-400 mb-2">Nur aktive System-Übungen können hinzugefügt werden. Übungen werden hier nicht angelegt oder bearbeitet.</p>
            <div class="flex gap-3 items-end">
              <label class="block grow">
                <span class="text-xs font-medium text-gray-500">Aktive Übung</span>
                <select [formControl]="addExerciseControl" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
                  <option [ngValue]="null">Bitte wählen…</option>
                  @for (exercise of availableExercises(); track exercise.id) {
                    <option [ngValue]="exercise.id">{{ exercise.title }} ({{ exercise.exerciseType }})</option>
                  }
                </select>
              </label>
              <button type="button" [disabled]="addExerciseControl.value === null" (click)="addExercise()" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700 disabled:bg-gray-300">Hinzufügen</button>
            </div>
          </div>
        </div>
      }

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
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Schwierigkeit</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Dauer</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Übungen</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Status</th>
                <th class="text-right px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Aktionen</th>
              </tr>
            </thead>
            <tbody>
              @for (template of templates(); track template.id) {
                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                  <td class="px-4 py-3">
                    <div class="font-medium text-gray-900">
                      {{ template.title }}
                      @if (template.isFeatured) {
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-amber-50 text-amber-700 ml-1">Empfohlen</span>
                      }
                    </div>
                    <div class="text-xs text-gray-400 font-mono">{{ template.slug }}</div>
                  </td>
                  <td class="px-4 py-3 text-gray-500">{{ template.category }}</td>
                  <td class="px-4 py-3 text-gray-500">{{ template.difficulty }}</td>
                  <td class="px-4 py-3 text-gray-500">{{ template.estimatedDurationMinutes ? template.estimatedDurationMinutes + ' min' : '–' }}</td>
                  <td class="px-4 py-3 text-gray-500">{{ template.exerciseCount ?? 0 }}</td>
                  <td class="px-4 py-3">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
                      [class]="template.status === 'ACTIVE' ? 'bg-green-50 text-green-700' : template.status === 'DRAFT' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600'">
                      {{ template.status }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right whitespace-nowrap">
                    <button type="button" (click)="openDetail(template)" class="text-sm font-semibold text-teal-600 hover:text-teal-700 mr-3">Übungen</button>
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

        @if (meta(); as m) {
          <div class="flex items-center justify-between mt-4 text-sm text-gray-500">
            <span>Seite {{ m.current_page }} von {{ m.last_page }} ({{ m.total }} Templates)</span>
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
export class AdminSystemMeasureTemplatesComponent implements OnInit {
  private service = inject(AdminSystemMeasureTemplateService);
  private exerciseService = inject(AdminSystemExerciseService);
  private fb = inject(FormBuilder);
  private notifications = inject(NotificationService);

  categories: SystemMeasureTemplateCategory[] = ['MOBILITY', 'STRENGTH', 'BREATHING', 'MINDFULNESS', 'EDUCATION', 'REFLECTION', 'MIXED'];
  difficulties: SystemMeasureTemplateDifficulty[] = ['BEGINNER', 'INTERMEDIATE', 'ADVANCED'];
  statuses: SystemMeasureTemplateStatus[] = ['DRAFT', 'ACTIVE', 'ARCHIVED'];

  templates = signal<AdminSystemMeasureTemplate[]>([]);
  meta = signal<PaginatedResponse<AdminSystemMeasureTemplate>['meta'] | null>(null);
  loading = signal(true);
  saving = signal(false);
  error = signal<string | null>(null);
  formOpen = signal(false);
  editing = signal<AdminSystemMeasureTemplate | null>(null);
  page = signal(1);

  detail = signal<AdminSystemMeasureTemplate | null>(null);
  detailExercises = signal<AdminSystemMeasureTemplateExercise[]>([]);
  availableExercises = signal<AdminSystemExercise[]>([]);
  overridesFor = signal<AdminSystemMeasureTemplateExercise | null>(null);

  filterForm = this.fb.nonNullable.group({
    search: '',
    status: '',
    category: '',
    difficulty: '',
    isFeatured: '',
  });

  form = this.fb.nonNullable.group({
    title: ['', Validators.required],
    shortDescription: '',
    description: '',
    category: 'MIXED' as SystemMeasureTemplateCategory,
    difficulty: 'BEGINNER' as SystemMeasureTemplateDifficulty,
    status: 'ACTIVE' as SystemMeasureTemplateStatus,
    estimatedDurationMinutes: null as number | null,
    isFeatured: false,
    targetSignal: '',
    assignmentReasonTemplate: '',
    effectMetric: '',
    effectMetricUnit: '',
    requiresFloor: false,
  });

  locationOptions: Array<{ value: ExerciseLocationTag; label: string }> = [
    { value: 'office', label: 'Büro' },
    { value: 'home', label: 'Zuhause' },
    { value: 'plant', label: 'Werk' },
    { value: 'onroad', label: 'Unterwegs' },
  ];
  postureOptions: Array<{ value: ExercisePostureTag; label: string }> = [
    { value: 'standing', label: 'Im Stehen' },
    { value: 'sitting', label: 'Im Sitzen' },
  ];
  selectedLocationTags = signal<ExerciseLocationTag[]>([]);
  selectedPostureTags = signal<ExercisePostureTag[]>([]);

  toggleLocationTag(tag: ExerciseLocationTag) {
    this.selectedLocationTags.update(tags =>
      tags.includes(tag) ? tags.filter(t => t !== tag) : [...tags, tag]
    );
  }

  togglePostureTag(tag: ExercisePostureTag) {
    this.selectedPostureTags.update(tags =>
      tags.includes(tag) ? tags.filter(t => t !== tag) : [...tags, tag]
    );
  }

  overridesForm = this.fb.nonNullable.group({
    customTitle: '',
    customInstructions: '',
    customDurationMinutes: null as number | null,
    customSets: null as number | null,
    customRepetitions: null as number | null,
    customHoldSeconds: null as number | null,
    customFeedbackPrompt: '',
    isRequired: true,
  });

  addExerciseControl = this.fb.control<number | null>(null);

  ngOnInit() {
    this.loadTemplates();
  }

  applyFilters() {
    this.page.set(1);
    this.loadTemplates();
  }

  goToPage(page: number) {
    this.page.set(page);
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
    if (filters.isFeatured) params.isFeatured = filters.isFeatured === 'true';

    this.service.listTemplates(params).subscribe({
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

  openCreate() {
    this.editing.set(null);
    this.form.reset();
    this.selectedLocationTags.set([]);
    this.selectedPostureTags.set([]);
    this.error.set(null);
    this.formOpen.set(true);
  }

  openEdit(template: AdminSystemMeasureTemplate) {
    this.editing.set(template);
    this.form.reset();
    this.selectedLocationTags.set(template.locationTags ?? []);
    this.selectedPostureTags.set(template.postureTags ?? []);
    this.form.patchValue({
      title: template.title,
      shortDescription: template.shortDescription ?? '',
      description: template.description ?? '',
      category: template.category,
      difficulty: template.difficulty,
      status: template.status,
      estimatedDurationMinutes: template.estimatedDurationMinutes,
      isFeatured: template.isFeatured,
      targetSignal: template.targetSignal ?? '',
      assignmentReasonTemplate: template.assignmentReasonTemplate ?? '',
      effectMetric: template.effectMetric ?? '',
      effectMetricUnit: template.effectMetricUnit ?? '',
      requiresFloor: template.requiresFloor ?? false,
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
      status: value.status,
      estimatedDurationMinutes: value.estimatedDurationMinutes,
      isFeatured: value.isFeatured,
      targetSignal: value.targetSignal.trim() || null,
      assignmentReasonTemplate: value.assignmentReasonTemplate.trim() || null,
      effectMetric: (value.effectMetric || null) as MeasureEffectMetric | null,
      effectMetricUnit: value.effectMetricUnit.trim() || null,
      locationTags: this.selectedLocationTags().length ? this.selectedLocationTags() : null,
      postureTags: this.selectedPostureTags().length ? this.selectedPostureTags() : null,
      requiresFloor: value.requiresFloor,
    };
  }

  save() {
    this.error.set(null);
    if (this.form.invalid) return;

    this.saving.set(true);
    const payload = this.buildPayload();
    const editing = this.editing();
    const request$ = editing
      ? this.service.updateTemplate(editing.id, payload)
      : this.service.createTemplate(payload);

    request$.subscribe({
      next: () => {
        this.notifications.success(editing ? 'Template wurde aktualisiert.' : 'Template wurde angelegt.');
        this.saving.set(false);
        this.closeForm();
        this.loadTemplates();
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
    if (!confirm(`Template "${template.title}" archivieren? Die zugeordneten Übungen bleiben erhalten.`)) return;

    this.service.archiveTemplate(template.id).subscribe({
      next: () => {
        this.notifications.success('Template wurde archiviert.');
        this.loadTemplates();
      },
      error: () => this.notifications.error('Template konnte nicht archiviert werden.'),
    });
  }

  openDetail(template: AdminSystemMeasureTemplate) {
    this.overridesFor.set(null);
    this.addExerciseControl.setValue(null);
    this.loadDetail(template.id);
    // Exercise selection comes from the existing catalog API, active exercises only.
    this.exerciseService.listExercises({ status: 'ACTIVE', perPage: 100 }).subscribe({
      next: res => this.availableExercises.set(res.data),
      error: () => this.notifications.error('Übungskatalog konnte nicht geladen werden.'),
    });
  }

  closeDetail() {
    this.detail.set(null);
    this.detailExercises.set([]);
    this.overridesFor.set(null);
  }

  loadDetail(templateId: number) {
    this.service.getTemplate(templateId).subscribe({
      next: res => {
        this.detail.set(res.data);
        this.detailExercises.set(res.data.exercises ?? []);
      },
      error: () => this.notifications.error('Template-Detail konnte nicht geladen werden.'),
    });
  }

  addExercise() {
    const template = this.detail();
    const systemExerciseId = this.addExerciseControl.value;
    if (!template || systemExerciseId === null) return;

    this.service.addExercise(template.id, { systemExerciseId }).subscribe({
      next: () => {
        this.notifications.success('Übung wurde hinzugefügt.');
        this.addExerciseControl.setValue(null);
        this.loadDetail(template.id);
      },
      error: err => this.notifications.error(err.error?.message ?? 'Übung konnte nicht hinzugefügt werden.'),
    });
  }

  openOverrides(item: AdminSystemMeasureTemplateExercise) {
    this.overridesFor.set(item);
    this.overridesForm.reset();
    this.overridesForm.patchValue({
      customTitle: item.customTitle ?? '',
      customInstructions: item.customInstructions ?? '',
      customDurationMinutes: item.customDurationMinutes,
      customSets: item.customSets,
      customRepetitions: item.customRepetitions,
      customHoldSeconds: item.customHoldSeconds,
      customFeedbackPrompt: item.customFeedbackPrompt ?? '',
      isRequired: item.isRequired,
    });
  }

  closeOverrides() {
    this.overridesFor.set(null);
  }

  saveOverrides() {
    const template = this.detail();
    const item = this.overridesFor();
    if (!template || !item) return;

    const value = this.overridesForm.getRawValue();
    const payload: UpdateSystemMeasureTemplateExercisePayload = {
      customTitle: value.customTitle.trim() || null,
      customInstructions: value.customInstructions.trim() || null,
      customDurationMinutes: value.customDurationMinutes,
      customSets: value.customSets,
      customRepetitions: value.customRepetitions,
      customHoldSeconds: value.customHoldSeconds,
      customFeedbackPrompt: value.customFeedbackPrompt.trim() || null,
      isRequired: value.isRequired,
    };

    this.service.updateTemplateExercise(template.id, item.id, payload).subscribe({
      next: () => {
        this.notifications.success('Anpassungen wurden gespeichert.');
        this.closeOverrides();
        this.loadDetail(template.id);
      },
      error: err => this.notifications.error(err.error?.message ?? 'Anpassungen konnten nicht gespeichert werden.'),
    });
  }

  removeExercise(item: AdminSystemMeasureTemplateExercise) {
    const template = this.detail();
    if (!template) return;
    if (!confirm('Übung aus dem Template entfernen? Die Übung selbst bleibt im Katalog erhalten.')) return;

    this.service.removeTemplateExercise(template.id, item.id).subscribe({
      next: () => {
        this.notifications.success('Übung wurde entfernt.');
        this.loadDetail(template.id);
      },
      error: () => this.notifications.error('Übung konnte nicht entfernt werden.'),
    });
  }

  moveUp(item: AdminSystemMeasureTemplateExercise) {
    this.swapWithNeighbour(item, -1);
  }

  moveDown(item: AdminSystemMeasureTemplateExercise) {
    this.swapWithNeighbour(item, 1);
  }

  private swapWithNeighbour(item: AdminSystemMeasureTemplateExercise, direction: -1 | 1) {
    const template = this.detail();
    if (!template) return;

    const ordered = [...this.detailExercises()];
    const index = ordered.findIndex(entry => entry.id === item.id);
    const targetIndex = index + direction;
    if (index === -1 || targetIndex < 0 || targetIndex >= ordered.length) return;

    [ordered[index], ordered[targetIndex]] = [ordered[targetIndex], ordered[index]];
    // The reorder endpoint requires the complete ID set with sequential sort orders.
    const items = ordered.map((entry, position) => ({ id: entry.id, sortOrder: position + 1 }));

    this.service.reorderExercises(template.id, { items }).subscribe({
      next: res => this.detailExercises.set(res.data),
      error: err => this.notifications.error(err.error?.message ?? 'Reihenfolge konnte nicht gespeichert werden.'),
    });
  }
}
