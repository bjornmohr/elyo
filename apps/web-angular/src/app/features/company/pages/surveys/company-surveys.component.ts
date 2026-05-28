import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ApiClient } from '../../../../core/services/api-client.service';
import { Role } from '../../../../core/models/auth.models';
import { AuthStore } from '../../../../core/store/auth.store';
import { NotificationService } from '../../../../shared/notifications/notification.service';

@Component({
  selector: 'app-company-surveys',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  template: `
    <div class="space-y-6">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Umfragen</h1>
          <p class="text-sm text-gray-500 mt-1">Entwürfe bearbeiten, Umfragen aktivieren und aggregierte Ergebnisse
            auswerten.</p>
        </div>
        @if (!managerDisabledByTeamLayer()) {
          <button type="button" (click)="startCreate()"
                  class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700">
            Umfrage hinzufügen
          </button>
        }
      </div>

      @if (managerDisabledByTeamLayer()) {
        <div class="rounded-xl border border-amber-100 bg-amber-50 p-5 text-amber-800">
          <div class="font-semibold">Umfragen auf Team-Ebene nicht verfügbar</div>
          <p class="text-sm mt-1">Manager können Umfragen nur bei aktivierter Team-Ebene erstellen oder bearbeiten.</p>
        </div>
      }

      @if (showForm() && !managerDisabledByTeamLayer()) {
        <form [formGroup]="surveyForm" (ngSubmit)="submit()"
              class="bg-white rounded-xl border border-gray-200 p-5 space-y-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h2
                class="text-lg font-semibold text-gray-900">{{ editingSurveyId() ? 'Umfrage bearbeiten' : 'Neue Umfrage' }}</h2>
              <p class="text-sm text-gray-500">Umfragen koennen nur im Entwurfsstatus bearbeitet werden.</p>
            </div>
            <button type="button" (click)="closeForm()" class="text-sm text-gray-500 hover:text-gray-700">Schließen
            </button>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Titel <span class="text-red-500">*</span></span>
              <input formControlName="title"
                     class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"
                     [class.border-red-300]="invalid('title')"/>
              @if (invalid('title')) {
                <span class="text-xs text-red-600">Mindestens 3 Zeichen erforderlich.</span>
              }
            </label>
            @if (teamLayerEnabled()) {
              <label class="block">
                <span class="text-sm font-medium text-gray-700">Teams (Zielgruppe)</span>
                <select formControlName="teamIds" multiple
                        class="mt-1 h-24 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
                  @for (team of teams(); track team.id) {
                    <option [ngValue]="team.id">{{ team.name }}</option>
                  }
                </select>
                <span class="text-xs text-gray-400">Leer bedeutet company-wide. Manager sehen bei company-wide Umfragen nur ihr Team.</span>
              </label>
            }
          </div>

          <label class="block">
            <span class="text-sm font-medium text-gray-700">Beschreibung</span>
            <textarea formControlName="description" rows="3"
                      class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"></textarea>
          </label>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Start</span>
              <input type="datetime-local" formControlName="startsAt"
                     class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"/>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Ende</span>
              <input type="datetime-local" formControlName="endsAt"
                     class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"/>
            </label>
            <label class="flex items-center gap-2 pt-7 text-sm text-gray-700">
              <input type="checkbox" formControlName="isAnonymous" class="rounded border-gray-300 text-teal-600"/>
              Anonym
            </label>
          </div>

          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <h2 class="text-sm font-semibold text-gray-900">Fragen <span class="text-red-500">*</span></h2>
              <button type="button" (click)="addQuestion()"
                      class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Frage hinzufügen
              </button>
            </div>

            <div formArrayName="questions" class="space-y-3">
              @for (question of questions.controls; track $index; let i = $index) {
                <div [formGroupName]="i" class="rounded-xl border border-gray-200 p-4 space-y-3">
                  <div class="flex items-start justify-between gap-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1">
                      <label class="block">
                        <span class="text-sm font-medium text-gray-700">Fragetext <span
                          class="text-red-500">*</span></span>
                        <input formControlName="text"
                               class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"
                               [class.border-red-300]="questionInvalid(i, 'text')"/>
                        @if (questionInvalid(i, 'text')) {
                          <span class="text-xs text-red-600">Mindestens 3 Zeichen erforderlich.</span>
                        }
                      </label>
                      <label class="block">
                        <span class="text-sm font-medium text-gray-700">Typ <span class="text-red-500">*</span></span>
                        <select formControlName="type"
                                class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
                          <option value="SCALE">Skala</option>
                          <option value="MULTIPLE_CHOICE">Mehrfachauswahl</option>
                          <option value="TEXT">Text</option>
                          <option value="YES_NO">Ja/Nein</option>
                        </select>
                      </label>
                    </div>
                    <button type="button" (click)="removeQuestion(i)" [disabled]="questions.length === 1"
                            class="px-3 py-2 rounded-lg text-sm text-red-600 hover:bg-red-50 disabled:text-gray-300 disabled:hover:bg-transparent">
                      Entfernen
                    </button>
                  </div>

                  @if (question.get('type')?.value === 'SCALE') {
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <label class="block">
                        <span class="text-sm font-medium text-gray-700">Skala Minimum Label</span>
                        <input formControlName="scaleMinLabel"
                               class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"/>
                      </label>
                      <label class="block">
                        <span class="text-sm font-medium text-gray-700">Skala Maximum Label</span>
                        <input formControlName="scaleMaxLabel"
                               class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"/>
                      </label>
                    </div>
                  }

                  @if (question.get('type')?.value === 'MULTIPLE_CHOICE') {
                    <label class="block">
                      <span class="text-sm font-medium text-gray-700">Optionen <span
                        class="text-red-500">*</span></span>
                      <input formControlName="optionsText" placeholder="Option A, Option B, Option C"
                             class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"/>
                    </label>
                  }

                  <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" formControlName="isRequired" class="rounded border-gray-300 text-teal-600"/>
                    Pflichtfrage
                  </label>
                </div>
              }
            </div>
          </div>

          @if (formError()) {
            <div class="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError() }}
            </div>
          }

          <div class="flex justify-between gap-3">
            <button
              type="button"
              (click)="activate()"
              [disabled]="!editingSurveyId() || activating()"
              class="px-4 py-2 rounded-lg border border-teal-200 text-teal-700 text-sm font-semibold hover:bg-teal-50 disabled:border-gray-200 disabled:text-gray-300"
            >
              {{ activating() ? 'Aktiviere…' : 'Umfrage aktivieren' }}
            </button>
            <button type="submit" [disabled]="saving()"
                    class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700 disabled:bg-gray-300">
              {{ saving() ? 'Speichern…' : 'Entwurf speichern' }}
            </button>
          </div>
        </form>
      }

      @if (selectedResults()) {
        <section class="bg-white rounded-xl border border-gray-200 p-5 space-y-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h2 class="text-lg font-semibold text-gray-900">{{ selectedResults()?.survey?.title }}</h2>
              <p class="text-sm text-gray-500">Aggregierte Ergebnisse ohne user-level Daten.</p>
            </div>
            <button type="button" (click)="selectedResults.set(null)" class="text-sm text-gray-500 hover:text-gray-700">
              Schließen
            </button>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="rounded-lg bg-stone-50 p-4">
              <div class="text-xs text-gray-400">Teilnahme</div>
              <div class="text-2xl font-semibold text-gray-900">{{ selectedResults()?.participation?.rate ?? 0 }}%</div>
            </div>
            <div class="rounded-lg bg-stone-50 p-4">
              <div class="text-xs text-gray-400">Antworten</div>
              <div
                class="text-2xl font-semibold text-gray-900">{{ selectedResults()?.participation?.responseCount ?? 0 }}
              </div>
            </div>
            <div class="rounded-lg bg-stone-50 p-4">
              <div class="text-xs text-gray-400">Zielgruppe</div>
              <div
                class="text-2xl font-semibold text-gray-900">{{ selectedResults()?.participation?.eligibleCount ?? 0 }}
              </div>
            </div>
          </div>

          <div class="space-y-4">
            @for (question of selectedResults()?.questions ?? []; track question.questionId) {
              <article class="rounded-xl border border-gray-200 p-4">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <h3 class="font-semibold text-gray-900">{{ question.text }}</h3>
                    @if (question.answerCount !== null && question.answerCount !== undefined) {
                      <p class="text-xs text-gray-500 mt-1">{{ question.answerCount }} aggregierte Antworten</p>
                    } @else {
                      <p class="text-xs text-gray-500 mt-1">Antwortzahl anonymisiert</p>
                    }
                  </div>
                  <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ question.type }}</span>
                </div>

                @if (question.type === 'SCALE') {
                  @if (!question.isSuppressed) {
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                      <div class="rounded-lg bg-teal-50 p-3">
                        <div class="text-xs text-teal-700">Durchschnitt</div>
                        <div class="text-2xl font-semibold text-teal-800">{{ question.avgValue ?? '—' }}</div>
                      </div>
                      <div class="rounded-lg bg-stone-50 p-3">
                        <div class="text-xs text-gray-400">Minimum</div>
                        <div class="font-semibold">{{ question.minValue ?? '—' }}</div>
                      </div>
                      <div class="rounded-lg bg-stone-50 p-3">
                        <div class="text-xs text-gray-400">Maximum</div>
                        <div class="font-semibold">{{ question.maxValue ?? '—' }}</div>
                      </div>
                    </div>
                  }
                  @if (!question.isSuppressed) {
                    <div class="mt-4 flex items-end gap-1 h-24">
                      @for (bucket of question.distribution ?? []; track bucket.value) {
                        <div class="flex-1 flex flex-col justify-end items-center gap-1">
                          <div class="w-full rounded-t bg-teal-500" [style.height.%]="bucket.percentage || 4"></div>
                          <span class="text-[10px] text-gray-400">{{ bucket.value }}</span>
                        </div>
                      }
                    </div>
                  } @else {
                    <p class="mt-3 text-sm text-gray-500">
                      @if (question.suppressionReason === 'QUESTION_THRESHOLD_NOT_MET') {
                        Zu wenige Antworten für eine anonyme Auswertung dieser Frage.
                      } @else {
                        Einzelne Skalenwerte werden zum Schutz kleiner Antwortgruppen nicht angezeigt.
                      }
                    </p>
                  }
                } @else if (question.type === 'YES_NO') {
                  @if (question.isSuppressed) {
                    <p class="mt-4 text-sm text-gray-500">
                      @if (question.suppressionReason === 'QUESTION_THRESHOLD_NOT_MET') {
                        Zu wenige Antworten für eine anonyme Auswertung dieser Frage.
                      } @else {
                        Die genaue Ja/Nein-Verteilung wird zum Schutz kleiner Antwortgruppen nicht angezeigt.
                      }
                    </p>
                  } @else {
                    <div class="mt-4 grid grid-cols-2 gap-3">
                      <div class="rounded-lg bg-green-50 p-3 text-green-800">
                        <div class="text-xs">Ja</div>
                        <div class="text-xl font-semibold">{{ question.trueCount }} · {{ question.truePercentage }}%
                        </div>
                      </div>
                      <div class="rounded-lg bg-red-50 p-3 text-red-800">
                        <div class="text-xs">Nein</div>
                        <div class="text-xl font-semibold">{{ question.falseCount }} · {{ question.falsePercentage }}%
                        </div>
                      </div>
                    </div>
                  }
                } @else if (question.type === 'MULTIPLE_CHOICE') {
                  @if (question.isSuppressed) {
                    <p class="mt-4 text-sm text-gray-500">
                      @if (question.suppressionReason === 'QUESTION_THRESHOLD_NOT_MET') {
                        Zu wenige Antworten für eine anonyme Auswertung dieser Frage.
                      } @else {
                        Die Antwortverteilung wird zum Schutz kleiner Antwortgruppen nicht angezeigt.
                      }
                    </p>
                  } @else {
                    <div class="mt-4 space-y-2">
                      @for (option of question.options ?? []; track option.value) {
                        <div>
                          <div class="flex justify-between text-xs text-gray-500 mb-1">
                            <span>{{ option.value || 'Keine Angabe' }}</span>
                            <span>{{ option.count }} · {{ option.percentage }}%</span>
                          </div>
                          <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full bg-teal-500" [style.width.%]="option.percentage"></div>
                          </div>
                        </div>
                      }
                    </div>
                  }
                } @else {
                  @if (question.isSuppressed) {
                    <p class="mt-4 text-sm text-gray-500">Zu wenige Antworten für eine anonyme Auswertung dieser
                      Frage.</p>
                  } @else {
                    <p class="mt-4 text-sm text-gray-500">Freitextantworten werden zum Schutz der Anonymität nicht
                      einzeln angezeigt.</p>
                  }
                }
              </article>
            }
          </div>
        </section>
      }

      @if (resultsError()) {
        <div class="rounded-xl border border-red-100 bg-red-50 p-5 text-red-700">
          <div class="font-semibold">Ergebnisse noch nicht sichtbar</div>
          <p class="text-sm mt-1">{{ resultsError()?.message }}</p>
          @if (resultsError()?.current !== undefined) {
            <p class="text-sm mt-2">Aktuell: {{ resultsError()?.current }} /
              erforderlich: {{ resultsError()?.minRequired }}</p>
          }
        </div>
      }

      @if (!managerDisabledByTeamLayer()) {
        @if (loading()) {
          <div class="flex justify-center py-12">
            <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
          </div>
        } @else if (surveys().length === 0) {
          <div class="bg-white rounded-xl border border-gray-200 p-12 text-center"><p class="text-gray-500 text-sm">Noch
            keine Umfragen vorhanden.</p></div>
        } @else {
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @for (survey of surveys(); track survey.id) {
              <button type="button" (click)="openSurvey(survey)"
                      class="text-left bg-white rounded-xl border border-gray-200 p-5 hover:border-teal-200 hover:shadow-sm transition">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <h2 class="font-semibold text-gray-900">{{ survey.title }}</h2>
                    <p class="text-sm text-gray-500 mt-1">{{ survey.description || 'Keine Beschreibung' }}</p>
                  </div>
                  <span class="px-2 py-0.5 rounded-full text-xs"
                        [class]="statusClass(survey.status)">{{ survey.status }}</span>
                </div>
                <div class="flex gap-3 mt-4 text-xs text-gray-500">
                  <span>{{ survey.questionsCount ?? 0 }} Fragen</span>
                  <span>{{ survey.responsesCount ?? 0 }} Antworten</span>
                  @if (survey.status === 'DRAFT' && survey.canEdit) {
                    <span class="text-teal-700 font-medium">Bearbeiten</span>
                  } @else if (survey.status === 'ACTIVE') {
                    <span class="text-teal-700 font-medium">Ergebnisse ansehen</span>
                  }
                </div>
              </button>
            }
          </div>
        }
      }
    </div>
  `
})
export class CompanySurveysComponent implements OnInit {
  private api = inject(ApiClient);
  private authStore = inject(AuthStore);
  private fb = inject(FormBuilder);
  private notifications = inject(NotificationService);

  surveys = signal<any[]>([]);
  teams = signal<any[]>([]);
  loading = signal(true);
  saving = signal(false);
  activating = signal(false);
  showForm = signal(false);
  editingSurveyId = signal<number | null>(null);
  formError = signal<string | null>(null);
  selectedResults = signal<any | null>(null);
  resultsError = signal<any | null>(null);

  surveyForm = this.fb.group({
    title: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(120)]],
    description: ['', [Validators.maxLength(500)]],
    startsAt: [null as string | null],
    endsAt: [null as string | null],
    isAnonymous: [true],
    teamIds: [[] as number[]],
    questions: this.fb.array([this.createQuestion()]),
  });

  get questions(): FormArray {
    return this.surveyForm.get('questions') as FormArray;
  }

  ngOnInit() {
    if (this.managerDisabledByTeamLayer()) {
      this.loading.set(false);
      return;
    }

    this.loadSurveys();
    if (this.teamLayerEnabled()) {
      this.api.get<{ data: any[] }>('/company/teams').subscribe({
        next: res => this.teams.set(res.data ?? []),
      });
    }
  }

  startCreate() {
    if (this.managerDisabledByTeamLayer()) {
      this.notifications.error('Manager können Umfragen nur bei aktivierter Team-Ebene erstellen.');
      return;
    }

    this.editingSurveyId.set(null);
    this.resetForm();
    this.selectedResults.set(null);
    this.resultsError.set(null);
    this.formError.set(null);
    this.showForm.set(true);
  }

  closeForm() {
    this.showForm.set(false);
    this.editingSurveyId.set(null);
    this.formError.set(null);
  }

  openSurvey(survey: any) {
    this.selectedResults.set(null);
    this.resultsError.set(null);

    if (survey.status === 'DRAFT' && survey.canEdit) {
      if (this.managerDisabledByTeamLayer()) {
        this.notifications.error('Manager können Umfragen nur bei aktivierter Team-Ebene bearbeiten.');
        return;
      }

      this.loadForEdit(survey.id);
      return;
    }

    if (survey.status === 'ACTIVE') {
      this.loadResults(survey.id);
    }
  }

  loadForEdit(id: number) {
    if (this.managerDisabledByTeamLayer()) {
      return;
    }

    this.api.get<{ data: any }>(`/company/surveys/${id}`).subscribe({
      next: res => {
        const survey = res.data;
        this.editingSurveyId.set(survey.id);
        this.patchForm(survey);
        this.showForm.set(true);
      },
      error: err => this.notifications.error(err.error?.message || 'Umfrage konnte nicht geladen werden.'),
    });
  }

  loadResults(id: number) {
    this.api.get<{ data: any }>(`/company/surveys/${id}/results`).subscribe({
      next: res => {
        this.selectedResults.set(res.data);
        this.resultsError.set(null);
      },
      error: err => {
        this.selectedResults.set(null);
        this.resultsError.set({
          message: err.error?.error || err.error?.message || 'Ergebnisse konnten nicht geladen werden.',
          current: err.error?.current,
          minRequired: err.error?.minRequired,
        });
      },
    });
  }

  addQuestion() {
    this.questions.push(this.createQuestion());
  }

  removeQuestion(index: number) {
    if (this.questions.length > 1) this.questions.removeAt(index);
  }

  invalid(control: string) {
    const field = this.surveyForm.get(control);
    return !!field && field.invalid && (field.dirty || field.touched);
  }

  questionInvalid(index: number, control: string) {
    const field = this.questions.at(index).get(control);
    return !!field && field.invalid && (field.dirty || field.touched);
  }

  submit() {
    this.formError.set(null);
    if (this.managerDisabledByTeamLayer()) {
      this.formError.set('Manager können Umfragen nur bei aktivierter Team-Ebene speichern.');
      return;
    }

    if (this.surveyForm.invalid) {
      this.surveyForm.markAllAsTouched();
      return;
    }

    const payload = this.payload();
    if (!payload) return;

    this.saving.set(true);
    const id = this.editingSurveyId();
    const request = id
      ? this.api.patch<{ data: any }>(`/company/surveys/${id}`, payload)
      : this.api.post<{ data: any }>('/company/surveys', payload);

    request.subscribe({
      next: res => {
        this.upsertSurvey(res.data);
        this.editingSurveyId.set(res.data.id);
        this.notifications.success('Umfrage wurde gespeichert.');
        this.saving.set(false);
      },
      error: err => {
        const message = this.validationMessage(err);
        this.formError.set(message);
        this.notifications.error(message);
        this.saving.set(false);
      }
    });
  }

  activate() {
    const id = this.editingSurveyId();
    if (!id || this.managerDisabledByTeamLayer()) return;

    this.activating.set(true);
    this.api.post<{ data: any }>(`/company/surveys/${id}/activate`, {}).subscribe({
      next: res => {
        this.upsertSurvey(res.data);
        this.closeForm();
        this.notifications.success('Umfrage wurde aktiviert.');
        this.activating.set(false);
      },
      error: err => {
        this.notifications.error(err.error?.message || 'Umfrage konnte nicht aktiviert werden.');
        this.activating.set(false);
      },
    });
  }

  statusClass(status: string) {
    if (status === 'DRAFT') return 'bg-yellow-50 text-yellow-700';
    if (status === 'ACTIVE') return 'bg-green-50 text-green-700';
    return 'bg-gray-100 text-gray-600';
  }

  teamLayerEnabled() {
    return this.authStore.teamLayerEnabled();
  }

  managerDisabledByTeamLayer() {
    return this.isManagerOnly() && !this.teamLayerEnabled();
  }

  private isManagerOnly() {
    const roles = this.authStore.roles();
    return roles.includes(Role.COMPANY_MANAGER) && !roles.some(role => [Role.COMPANY_ADMIN, Role.COMPANY_OWNER].includes(role as Role));
  }

  private payload() {
    const questions = this.questions.controls.map((control, index) => {
      const value = control.value;
      const options = String(value.optionsText ?? '')
        .split(',')
        .map(option => option.trim())
        .filter(Boolean);

      return {
        id: value.id,
        text: value.text,
        type: value.type,
        order: index,
        isRequired: value.isRequired,
        options: value.type === 'MULTIPLE_CHOICE' ? options : null,
        scaleMinLabel: value.type === 'SCALE' ? value.scaleMinLabel : null,
        scaleMaxLabel: value.type === 'SCALE' ? value.scaleMaxLabel : null,
      };
    });

    if (questions.some(question => question.type === 'MULTIPLE_CHOICE' && !question.options?.length)) {
      const message = 'Mehrfachauswahl-Fragen brauchen mindestens eine Option.';
      this.formError.set(message);
      this.notifications.error(message);
      return null;
    }

    const { teamIds, ...payload } = this.surveyForm.value;
    return this.teamLayerEnabled()
      ? { ...payload, teamIds: teamIds ?? [], questions }
      : { ...payload, questions };
  }

  private createQuestion(value: any = {}) {
    return this.fb.group({
      id: [value.id ?? null],
      text: [value.text ?? '', [Validators.required, Validators.minLength(3), Validators.maxLength(300)]],
      type: [value.type ?? 'SCALE', [Validators.required]],
      isRequired: [value.isRequired ?? true],
      optionsText: [(value.options ?? []).join(', ')],
      scaleMinLabel: [value.scaleMinLabel ?? 'Stimme nicht zu', [Validators.maxLength(80)]],
      scaleMaxLabel: [value.scaleMaxLabel ?? 'Stimme zu', [Validators.maxLength(80)]],
    });
  }

  private patchForm(survey: any) {
    while (this.questions.length > 0) this.questions.removeAt(0);
    for (const question of survey.questions ?? []) {
      this.questions.push(this.createQuestion(question));
    }
    if (this.questions.length === 0) this.questions.push(this.createQuestion());

    this.surveyForm.patchValue({
      title: survey.title,
      description: survey.description,
      startsAt: this.toDateTimeLocal(survey.startsAt),
      endsAt: this.toDateTimeLocal(survey.endsAt),
      isAnonymous: survey.isAnonymous,
      teamIds: this.teamLayerEnabled() ? (survey.teamIds ?? []) : [],
    });
  }

  private loadSurveys() {
    this.api.get<{ data: any[] }>('/company/surveys').subscribe({
      next: res => { this.surveys.set(res.data ?? []); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }

  private resetForm() {
    while (this.questions.length > 0) this.questions.removeAt(0);
    this.questions.push(this.createQuestion());
    this.surveyForm.reset({
      title: '',
      description: '',
      startsAt: null,
      endsAt: null,
      isAnonymous: true,
      teamIds: [],
    });
  }

  private upsertSurvey(survey: any) {
    this.surveys.update(surveys => {
      const exists = surveys.some(item => item.id === survey.id);
      return exists
        ? surveys.map(item => item.id === survey.id ? survey : item)
        : [survey, ...surveys];
    });
  }

  private toDateTimeLocal(value: string | null) {
    if (!value) return null;
    return value.slice(0, 16);
  }

  private validationMessage(err: any) {
    const errors = err.error?.errors;
    if (errors) return Object.values(errors).flat().join(' ');
    return err.error?.message || 'Umfrage konnte nicht gespeichert werden.';
  }
}
