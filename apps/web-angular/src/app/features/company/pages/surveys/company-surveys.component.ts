import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ApiClient } from '../../../../core/services/api-client.service';

@Component({
  selector: 'app-company-surveys',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  template: `
    <div class="space-y-6">
      <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Umfragen</h1>
        <button type="button" (click)="toggleForm()" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700">
          {{ showForm() ? 'Schließen' : 'Umfrage hinzufügen' }}
        </button>
      </div>

      @if (showForm()) {
        <form [formGroup]="surveyForm" (ngSubmit)="submit()" class="bg-white rounded-xl border border-gray-200 p-5 space-y-5">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Titel <span class="text-red-500">*</span></span>
              <input formControlName="title" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" [class.border-red-300]="invalid('title')" />
              @if (invalid('title')) { <span class="text-xs text-red-600">Mindestens 3 Zeichen erforderlich.</span> }
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Teams (Zielgruppe)</span>
              <select formControlName="teamIds" multiple class="mt-1 h-24 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
                @for (team of teams(); track team.id) {
                  <option [ngValue]="team.id">{{ team.name }}</option>
                }
              </select>
            </label>
          </div>

          <label class="block">
            <span class="text-sm font-medium text-gray-700">Beschreibung</span>
            <textarea formControlName="description" rows="3" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"></textarea>
          </label>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Start</span>
              <input type="datetime-local" formControlName="startsAt" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Ende</span>
              <input type="datetime-local" formControlName="endsAt" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
            </label>
            <label class="flex items-center gap-2 pt-7 text-sm text-gray-700">
              <input type="checkbox" formControlName="isAnonymous" class="rounded border-gray-300 text-teal-600" />
              Anonym
            </label>
          </div>

          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <h2 class="text-sm font-semibold text-gray-900">Fragen <span class="text-red-500">*</span></h2>
              <button type="button" (click)="addQuestion()" class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50">Frage hinzufügen</button>
            </div>

            <div formArrayName="questions" class="space-y-3">
              @for (question of questions.controls; track $index; let i = $index) {
                <div [formGroupName]="i" class="rounded-xl border border-gray-200 p-4 space-y-3">
                  <div class="flex items-start justify-between gap-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1">
                      <label class="block">
                        <span class="text-sm font-medium text-gray-700">Fragetext <span class="text-red-500">*</span></span>
                        <input formControlName="text" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" [class.border-red-300]="questionInvalid(i, 'text')" />
                        @if (questionInvalid(i, 'text')) { <span class="text-xs text-red-600">Mindestens 3 Zeichen erforderlich.</span> }
                      </label>
                      <label class="block">
                        <span class="text-sm font-medium text-gray-700">Typ <span class="text-red-500">*</span></span>
                        <select formControlName="type" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
                          <option value="SCALE">Skala</option>
                          <option value="MULTIPLE_CHOICE">Mehrfachauswahl</option>
                          <option value="TEXT">Text</option>
                          <option value="YES_NO">Ja/Nein</option>
                        </select>
                      </label>
                    </div>
                    <button type="button" (click)="removeQuestion(i)" [disabled]="questions.length === 1" class="px-3 py-2 rounded-lg text-sm text-red-600 hover:bg-red-50 disabled:text-gray-300 disabled:hover:bg-transparent">Entfernen</button>
                  </div>

                  @if (question.get('type')?.value === 'SCALE') {
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <label class="block">
                        <span class="text-sm font-medium text-gray-700">Skala Minimum Label</span>
                        <input formControlName="scaleMinLabel" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
                      </label>
                      <label class="block">
                        <span class="text-sm font-medium text-gray-700">Skala Maximum Label</span>
                        <input formControlName="scaleMaxLabel" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
                      </label>
                    </div>
                  }

                  @if (question.get('type')?.value === 'MULTIPLE_CHOICE') {
                    <label class="block">
                      <span class="text-sm font-medium text-gray-700">Optionen <span class="text-red-500">*</span></span>
                      <input formControlName="optionsText" placeholder="Option A, Option B, Option C" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
                    </label>
                  }

                  <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" formControlName="isRequired" class="rounded border-gray-300 text-teal-600" />
                    Pflichtfrage
                  </label>
                </div>
              }
            </div>
          </div>

          @if (formError()) {
            <div class="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError() }}</div>
          }

          <div class="flex justify-end">
            <button type="submit" [disabled]="saving()" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700 disabled:bg-gray-300">
              {{ saving() ? 'Speichern…' : 'Umfrage speichern' }}
            </button>
          </div>
        </form>
      }

      @if (loading()) {
        <div class="flex justify-center py-12"><div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div></div>
      } @else if (surveys().length === 0) {
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center"><p class="text-gray-500 text-sm">Noch keine Umfragen vorhanden.</p></div>
      } @else {
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          @for (survey of surveys(); track survey.id) {
            <div class="bg-white rounded-xl border border-gray-200 p-5">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <h2 class="font-semibold text-gray-900">{{ survey.title }}</h2>
                  <p class="text-sm text-gray-500 mt-1">{{ survey.description || 'Keine Beschreibung' }}</p>
                </div>
                <span class="px-2 py-0.5 rounded-full text-xs bg-teal-50 text-teal-700">{{ survey.status }}</span>
              </div>
              <div class="flex gap-3 mt-4 text-xs text-gray-500">
                <span>{{ survey.questionsCount ?? 0 }} Fragen</span>
                <span>{{ survey.responsesCount ?? 0 }} Antworten</span>
              </div>
            </div>
          }
        </div>
      }
    </div>
  `
})
export class CompanySurveysComponent implements OnInit {
  private api = inject(ApiClient);
  private fb = inject(FormBuilder);

  surveys = signal<any[]>([]);
  teams = signal<any[]>([]);
  loading = signal(true);
  saving = signal(false);
  showForm = signal(false);
  formError = signal<string | null>(null);

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
    this.loadSurveys();
    this.api.get<{ data: any[] }>('/company/teams').subscribe({
      next: res => this.teams.set(res.data ?? []),
    });
  }

  toggleForm() {
    this.showForm.update(value => !value);
    this.formError.set(null);
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
    if (this.surveyForm.invalid) {
      this.surveyForm.markAllAsTouched();
      return;
    }

    const questions = this.questions.controls.map((control, index) => {
      const value = control.value;
      const options = String(value.optionsText ?? '')
        .split(',')
        .map(option => option.trim())
        .filter(Boolean);

      return {
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
      this.formError.set('Mehrfachauswahl-Fragen brauchen mindestens eine Option.');
      return;
    }

    const payload = { ...this.surveyForm.value, questions };

    this.saving.set(true);
    this.api.post<{ data: any }>('/company/surveys', payload).subscribe({
      next: res => {
        this.surveys.update(surveys => [res.data, ...surveys]);
        this.resetForm();
        this.showForm.set(false);
        this.saving.set(false);
      },
      error: err => {
        this.formError.set(this.validationMessage(err));
        this.saving.set(false);
      }
    });
  }

  private createQuestion() {
    return this.fb.group({
      text: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(300)]],
      type: ['SCALE', [Validators.required]],
      isRequired: [true],
      optionsText: [''],
      scaleMinLabel: ['Stimme nicht zu', [Validators.maxLength(80)]],
      scaleMaxLabel: ['Stimme zu', [Validators.maxLength(80)]],
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

  private validationMessage(err: any) {
    const errors = err.error?.errors;
    if (errors) return Object.values(errors).flat().join(' ');
    return err.error?.message || 'Umfrage konnte nicht gespeichert werden.';
  }
}
