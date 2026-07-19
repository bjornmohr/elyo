import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { EmployeeBadge } from '../models/badge.model';
import { BADGE_CATEGORY_LABELS } from '../services/employee-badges-demo.service';
import { BadgeMedallionComponent } from './badge-medallion.component';

@Component({
  selector: 'app-badge-detail-modal',
  standalone: true,
  imports: [CommonModule, BadgeMedallionComponent],
  template: `
    @if (badge) {
      <div class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-[3px]"
           role="dialog"
           aria-modal="true"
           [attr.aria-label]="badge.title"
           tabindex="-1"
           (click)="close.emit()"
           (keydown.escape)="close.emit()">
        <article class="relative max-h-[calc(100vh-2rem)] w-full max-w-[25rem] overflow-y-auto rounded-[24px] bg-white p-7 text-center shadow-[0_30px_70px_rgba(0,0,0,.32)] sm:p-8"
                 (click)="$event.stopPropagation()">
          <button type="button"
                  class="absolute right-6 top-6 inline-flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-xl leading-none text-slate-500 transition hover:bg-slate-200 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
                  aria-label="Badge-Details schließen"
                  (click)="close.emit()">
            ×
          </button>

          <div class="flex justify-center pt-5">
            <app-badge-medallion [tone]="badge.tone" [iconKey]="badge.iconKey" [status]="badge.status" [progressPercent]="badge.progressPercent" [size]="100" />
          </div>

          <p class="mt-5 text-sm font-bold uppercase tracking-wide text-slate-500">{{ categoryLabel(badge.category) }}</p>
          <h2 class="mt-2 text-[23px] font-bold leading-tight text-slate-800">{{ badge.title }}</h2>

          <div class="mt-6 space-y-3 text-left">
            <section class="rounded-[14px] bg-slate-50 p-4">
              <h3 class="text-sm font-bold text-slate-800">So schaltest du es frei</h3>
              <p class="mt-2 text-sm leading-6 text-slate-600">{{ badge.description }}</p>
            </section>

            <section class="rounded-[14px] bg-slate-50 p-4">
              <h3 class="text-sm font-bold text-slate-800">Warum das hilft</h3>
              <p class="mt-2 text-sm leading-6 text-slate-600">{{ badge.benefit }}</p>
            </section>
          </div>

          @if (badge.status === 'IN_PROGRESS') {
            <div class="mt-6 text-left">
              <div class="flex items-center justify-between gap-3 text-sm">
                <span class="font-bold text-slate-700">{{ badge.progressCurrent }}/{{ badge.progressTarget }} {{ progressUnit(badge) }}</span>
                <span class="text-slate-500">{{ badge.progressPercent }}%</span>
              </div>
              <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-100" role="progressbar"
                   [attr.aria-valuenow]="badge.progressCurrent" [attr.aria-valuemin]="0" [attr.aria-valuemax]="badge.progressTarget">
                <div class="h-full rounded-full bg-gradient-to-r from-amber-300 to-amber-500" [style.width.%]="badge.progressPercent"></div>
              </div>
            </div>
          } @else if (badge.status === 'EARNED') {
            <p class="mt-6 rounded-2xl bg-teal-50 px-4 py-3 text-sm font-semibold text-teal-700">Freigeschaltet am {{ formatShortDate(badge.earnedAt) }}</p>
          } @else {
            <p class="mt-6 rounded-2xl bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-600">Dieses Badge wird über passende Routinen sichtbar, sobald du damit startest.</p>
          }
        </article>
      </div>
    }
  `,
})
export class BadgeDetailModalComponent {
  @Input() badge: EmployeeBadge | null = null;
  @Output() close = new EventEmitter<void>();

  categoryLabel(category: EmployeeBadge['category']): string {
    return BADGE_CATEGORY_LABELS[category];
  }

  progressUnit(badge: EmployeeBadge): string {
    return badge.unit ?? 'geschafft';
  }

  formatShortDate(date: string | undefined): string {
    if (!date) return 'Demo';
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit' }).format(new Date(date));
  }
}
