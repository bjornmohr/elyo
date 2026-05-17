import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ApiClient } from '../../../../core/services/api-client.service';
import { NotificationService } from '../../../../shared/notifications/notification.service';

@Component({
  selector: 'app-company-measures',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  template: `
    <div class="space-y-6">
      <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Maßnahmen</h1>
        <button type="button" (click)="toggleForm()" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700">
          {{ showForm() ? 'Schließen' : 'Maßnahme hinzufügen' }}
        </button>
      </div>

      @if (showForm()) {
        <form [formGroup]="measureForm" (ngSubmit)="submit()" class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Titel <span class="text-red-500">*</span></span>
              <input formControlName="title" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" [class.border-red-300]="invalid('title')" />
              @if (invalid('title')) { <span class="text-xs text-red-600">Mindestens 3 Zeichen erforderlich.</span> }
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Kategorie <span class="text-red-500">*</span></span>
              <select formControlName="category" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" [class.border-red-300]="invalid('category')">
                <option value="">Kategorie wählen</option>
                <option value="workshop">Workshop</option>
                <option value="flexibility">Flexibilität</option>
                <option value="sport">Sport</option>
                <option value="mental">Mental</option>
                <option value="nutrition">Ernährung</option>
              </select>
              @if (invalid('category')) { <span class="text-xs text-red-600">Kategorie ist erforderlich.</span> }
            </label>
          </div>

          <label class="block">
            <span class="text-sm font-medium text-gray-700">Beschreibung <span class="text-red-500">*</span></span>
            <textarea formControlName="description" rows="3" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" [class.border-red-300]="invalid('description')"></textarea>
            @if (invalid('description')) { <span class="text-xs text-red-600">Mindestens 10 Zeichen erforderlich.</span> }
          </label>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Team</span>
              <select formControlName="teamId" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
                <option [ngValue]="null">Alle Teams</option>
                @for (team of teams(); track team.id) {
                  <option [ngValue]="team.id">{{ team.name }}</option>
                }
              </select>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Status</span>
              <select formControlName="status" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
                <option value="ACTIVE">Aktiv</option>
                <option value="SUGGESTED">Vorgeschlagen</option>
              </select>
            </label>
          </div>

          @if (formError()) {
            <div class="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError() }}</div>
          }

          <div class="flex justify-end">
            <button type="submit" [disabled]="saving()" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700 disabled:bg-gray-300">
              {{ saving() ? 'Speichern…' : 'Maßnahme speichern' }}
            </button>
          </div>
        </form>
      }

      @if (loading()) {
        <div class="flex justify-center py-12"><div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div></div>
      } @else if (measures().length === 0) {
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center"><p class="text-gray-500 text-sm">Noch keine Maßnahmen vorhanden.</p></div>
      } @else {
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 border-b border-gray-100">
              <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Titel</th>
              <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Kategorie</th>
              <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Team</th>
              <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Status</th>
            </tr></thead>
            <tbody>
              @for (measure of measures(); track measure.id) {
                <tr class="border-b border-gray-50">
                  <td class="px-4 py-3 font-medium text-gray-900">{{ measure.title }}</td>
                  <td class="px-4 py-3 text-gray-500">{{ measure.category }}</td>
                  <td class="px-4 py-3 text-gray-500">{{ measure.team?.name || 'Alle Teams' }}</td>
                  <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs bg-teal-50 text-teal-700">{{ measure.status }}</span></td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      }
    </div>
  `
})
export class CompanyMeasuresComponent implements OnInit {
  private api = inject(ApiClient);
  private fb = inject(FormBuilder);
  private notifications = inject(NotificationService);

  measures = signal<any[]>([]);
  teams = signal<any[]>([]);
  loading = signal(true);
  saving = signal(false);
  showForm = signal(false);
  formError = signal<string | null>(null);

  measureForm = this.fb.group({
    title: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(100)]],
    category: ['', [Validators.required]],
    description: ['', [Validators.required, Validators.minLength(10), Validators.maxLength(500)]],
    teamId: [null as number | null],
    status: ['ACTIVE', [Validators.required]],
  });

  ngOnInit() {
    this.loadMeasures();
    this.api.get<{ data: any[] }>('/company/teams').subscribe({
      next: res => this.teams.set(res.data ?? []),
    });
  }

  toggleForm() {
    this.showForm.update(value => !value);
    this.formError.set(null);
  }

  invalid(control: string) {
    const field = this.measureForm.get(control);
    return !!field && field.invalid && (field.dirty || field.touched);
  }

  submit() {
    this.formError.set(null);
    if (this.measureForm.invalid) {
      this.measureForm.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    this.api.post<{ data: any }>('/company/measures', this.measureForm.value).subscribe({
      next: res => {
        this.measures.update(measures => [res.data, ...measures]);
        this.measureForm.reset({ title: '', category: '', description: '', teamId: null, status: 'ACTIVE' });
        this.showForm.set(false);
        this.notifications.success('Maßnahme wurde gespeichert.');
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

  private loadMeasures() {
    this.api.get<{ data: any[] }>('/company/measures').subscribe({
      next: res => { this.measures.set(res.data ?? []); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }

  private validationMessage(err: any) {
    const errors = err.error?.errors;
    if (errors) return Object.values(errors).flat().join(' ');
    return err.error?.message || 'Maßnahme konnte nicht gespeichert werden.';
  }
}
