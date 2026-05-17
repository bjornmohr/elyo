import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ApiClient } from '../../../../core/services/api-client.service';
import { NotificationService } from '../../../../shared/notifications/notification.service';

@Component({
  selector: 'app-admin-points',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  template: `
    <div class="max-w-3xl">
      <h1 class="text-2xl font-semibold text-gray-900 mb-6" style="font-family: 'Fraunces', Georgia, serif">Punkte-Konfiguration</h1>

      @if (loading()) {
        <div class="flex justify-center py-12">
          <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
        </div>
      } @else {
        <form [formGroup]="form" (ngSubmit)="save()" class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
          @for (field of fields; track field.key) {
            <label class="block">
              <span class="text-sm font-medium text-gray-700">{{ field.label }} <span class="text-red-500">*</span></span>
              <input type="number" min="0" formControlName="{{ field.key }}" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" />
            </label>
          }

          @if (error()) {
            <div class="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error() }}</div>
          }
          @if (success()) {
            <div class="rounded-lg border border-green-100 bg-green-50 px-3 py-2 text-sm text-green-700">Punktwerte gespeichert.</div>
          }

          <div class="flex justify-end">
            <button type="submit" [disabled]="saving() || form.invalid" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700 disabled:bg-gray-300">
              {{ saving() ? 'Speichern…' : 'Speichern' }}
            </button>
          </div>
        </form>
      }
    </div>
  `
})
export class AdminPointsComponent implements OnInit {
  private api = inject(ApiClient);
  private fb = inject(FormBuilder);
  private notifications = inject(NotificationService);

  fields = [
    { key: 'daily_checkin', label: 'Täglicher Check-in' },
    { key: 'streak_7days', label: '7-Tage-Streak' },
    { key: 'streak_30days', label: '30-Tage-Streak' },
    { key: 'anamnesis_completed', label: 'Anamnese abgeschlossen' },
    { key: 'medical_document_upload', label: 'Medizinisches PDF hochgeladen' },
  ];

  form = this.fb.group({
    daily_checkin: [0, [Validators.required, Validators.min(0)]],
    streak_7days: [0, [Validators.required, Validators.min(0)]],
    streak_30days: [0, [Validators.required, Validators.min(0)]],
    anamnesis_completed: [0, [Validators.required, Validators.min(0)]],
    medical_document_upload: [0, [Validators.required, Validators.min(0)]],
  });

  loading = signal(true);
  saving = signal(false);
  error = signal<string | null>(null);
  success = signal(false);

  ngOnInit() {
    this.api.get<{ data: any }>('/admin/points-config').subscribe({
      next: res => {
        this.form.patchValue(res.data ?? {});
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Punktwerte konnten nicht geladen werden.');
        this.loading.set(false);
      }
    });
  }

  save() {
    this.error.set(null);
    this.success.set(false);
    if (this.form.invalid) return;

    this.saving.set(true);
    this.api.put<{ data: any }>('/admin/points-config', this.form.value).subscribe({
      next: res => {
        this.form.patchValue(res.data ?? {});
        this.success.set(true);
        this.notifications.success('Punktwerte wurden gespeichert.');
        this.saving.set(false);
      },
      error: err => {
        const message = err.error?.message ?? 'Punktwerte konnten nicht gespeichert werden.';
        this.error.set(message);
        this.notifications.error(message);
        this.saving.set(false);
      }
    });
  }
}
