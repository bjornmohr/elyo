import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { EmployeeMeasure, EmployeeService } from '../../services/employee.service';
import { NotificationService } from '../../../../shared/notifications/notification.service';

@Component({
  selector: 'app-employee-measures',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="max-w-3xl mx-auto p-4 space-y-6">
      <header class="flex items-center space-x-4">
        <a routerLink="/employee" class="p-2 hover:bg-slate-100 rounded-full transition-colors text-slate-500">←</a>
        <h1 class="text-xl font-bold text-slate-800">Maßnahmen</h1>
      </header>

      @if (loading()) {
        <div class="flex justify-center py-12">
          <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
        </div>
      } @else if (measures().length === 0) {
        <div class="bg-white rounded-3xl border border-slate-100 p-10 text-center text-slate-400">
          Aktuell sind keine aktiven Maßnahmen verfügbar.
        </div>
      } @else {
        <div class="space-y-4">
          @for (measure of measures(); track measure.id) {
            <div class="bg-white rounded-2xl border border-slate-100 p-6 space-y-2">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <h2 class="font-bold text-slate-800">{{ measure.title }}</h2>
                  <p class="text-sm text-slate-500 mt-1">{{ measure.description }}</p>
                </div>
                <div class="flex flex-col items-end gap-1">
                  <span class="px-2 py-0.5 rounded-full text-xs bg-teal-50 text-teal-700">{{ measure.category }}</span>
                  @if (measure.deliveryType) {
                    <span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600">{{ label(measure.deliveryType) }}</span>
                  }
                </div>
              </div>
              <div class="grid gap-1 text-xs text-slate-500 sm:grid-cols-2">
                @if (measure.executionType) {
                  <p>{{ label(measure.executionType) }}</p>
                }
                @if (measure.startsAt || measure.endsAt) {
                  <p>{{ scheduleLabel(measure) }}</p>
                }
                @if ((measure.deliveryType === 'ONSITE' || measure.deliveryType === 'HYBRID') && (measure.locationName || measure.locationAddress)) {
                  <p>{{ measure.locationName || measure.locationAddress }}</p>
                }
                @if (measure.durationMinutes) {
                  <p>{{ measure.durationMinutes }} Minuten</p>
                }
                @if (measure.verificationRequirement) {
                  <p>Nachweis: {{ label(measure.verificationRequirement) }}</p>
                }
              </div>
              @if (measure.instructions) {
                <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">{{ measure.instructions }}</p>
              }
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-400">{{ measure.team?.name || 'Alle Teams' }}</p>

                @if (hasParticipated(measure)) {
                  <div class="flex flex-col items-start gap-1 sm:items-end">
                    <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                      Teilgenommen
                    </span>
                    @if (measure.participation?.participatedAt) {
                      <span class="text-xs text-slate-400">
                        {{ measure.participation?.participatedAt | date:'dd.MM.yyyy' }}
                      </span>
                    }
                  </div>
                } @else {
                  <button type="button"
                          (click)="participate(measure)"
                          [disabled]="isParticipating(measure.id)"
                          class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-teal-700 disabled:bg-slate-300">
                    {{ isParticipating(measure.id) ? 'Wird gespeichert…' : 'Teilnehmen' }}
                  </button>
                }
              </div>
            </div>
          }
        </div>
      }
    </div>
  `
})
export class EmployeeMeasuresComponent implements OnInit {
  private employeeService = inject(EmployeeService);
  private notifications = inject(NotificationService);

  measures = signal<EmployeeMeasure[]>([]);
  loading = signal(true);
  participatingMeasureIds = signal<Set<number>>(new Set());

  ngOnInit() {
    this.employeeService.getMeasures().subscribe({
      next: measures => { this.measures.set(measures); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }

  hasParticipated(measure: EmployeeMeasure) {
    return measure.participation?.isParticipating === true;
  }

  isParticipating(measureId: number) {
    return this.participatingMeasureIds().has(measureId);
  }

  participate(measure: EmployeeMeasure) {
    this.setParticipating(measure.id, true);

    this.employeeService.participateInMeasure(measure.id).subscribe({
      next: res => {
        this.applyParticipatedMeasure(measure.id, res.data);
        this.notifications.success('Teilnahme wurde gespeichert.');
        this.setParticipating(measure.id, false);
      },
      error: err => {
        const code = this.errorCode(err);

        if (code === 'MEASURE_ALREADY_PARTICIPATED') {
          this.markMeasureParticipated(measure.id);
          this.notifications.success('Teilnahme ist bereits gespeichert.');
        } else if (code === 'MEASURE_NOT_ACTIVE') {
          this.notifications.error('Diese Maßnahme ist aktuell nicht aktiv.');
        } else {
          this.notifications.error('Teilnahme konnte nicht gespeichert werden.');
        }

        this.setParticipating(measure.id, false);
      },
    });
  }

  label(value: string) {
    return value.toLowerCase().replace(/_/g, ' ');
  }

  scheduleLabel(measure: EmployeeMeasure) {
    const startsAt = measure.startsAt ? new Date(measure.startsAt).toLocaleString('de-DE') : null;
    const endsAt = measure.endsAt ? new Date(measure.endsAt).toLocaleString('de-DE') : null;

    if (startsAt && endsAt) return `${startsAt} - ${endsAt}`;
    return startsAt ?? endsAt ?? '';
  }

  private applyParticipatedMeasure(measureId: number, updatedMeasure: EmployeeMeasure | null | undefined) {
    if (updatedMeasure?.id) {
      this.measures.update(measures => measures.map(measure => measure.id === measureId ? updatedMeasure : measure));
      return;
    }

    this.markMeasureParticipated(measureId);
  }

  private markMeasureParticipated(measureId: number) {
    this.measures.update(measures => measures.map(measure => measure.id === measureId ? {
      ...measure,
      participation: {
        isParticipating: true,
        participatedAt: measure.participation?.participatedAt ?? null,
      },
    } : measure));
  }

  private setParticipating(measureId: number, loading: boolean) {
    this.participatingMeasureIds.update(ids => {
      const next = new Set(ids);
      if (loading) {
        next.add(measureId);
      } else {
        next.delete(measureId);
      }
      return next;
    });
  }

  private errorCode(err: any) {
    return err.error?.error?.code ?? err.error?.code;
  }
}
