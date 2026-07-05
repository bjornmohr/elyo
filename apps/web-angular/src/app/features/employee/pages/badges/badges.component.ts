import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { BADGE_CATEGORY_LABELS, EmployeeBadgesDemoService } from '../../services/employee-badges-demo.service';
import { BadgeCategory, EmployeeBadge } from '../../models/badge.model';

@Component({
  selector: 'app-employee-badges',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="w-full max-w-[min(100%,76rem)] space-y-7">
      <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-4">
          <a routerLink="/employee/dashboard" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full p-2.5 text-slate-500 transition-colors hover:bg-slate-100">←</a>
          <div>
            <h1 class="text-[30px] font-bold leading-tight text-slate-800">Präventions-Badges</h1>
            <p class="mt-2 max-w-3xl text-base leading-7 text-slate-500">
              Badges zeigen deine Routinen, Meilensteine und Fortschritte in der Prävention.
            </p>
          </div>
        </div>
      </header>

      <section class="rounded-[24px] border border-teal-100 bg-teal-50/60 p-6">
        <h2 class="text-lg font-bold text-slate-800">Wie Badges funktionieren</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">
          Badges belohnen präventives Verhalten und regelmäßige Reflexion, nicht perfekte Gesundheitswerte. Deine Badge-Fortschritte sind für deine persönliche Motivation gedacht.
        </p>
      </section>

      <section class="space-y-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2 class="text-xl font-bold text-slate-800">In Arbeit</h2>
            <p class="text-sm text-slate-500">Aktive Routinen und Quests, die gerade Fortschritt sammeln.</p>
          </div>
          <span class="text-sm font-semibold text-slate-500">{{ inProgressBadges().length }} aktiv</span>
        </div>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,17rem),1fr))] gap-4">
          @for (badge of inProgressBadges(); track badge.id) {
            <article class="rounded-2xl border border-slate-100 bg-white p-5">
              <ng-container *ngTemplateOutlet="badgeHeader; context: { badge: badge }" />
              <p class="mt-3 text-sm leading-6 text-slate-500">{{ badge.description }}</p>
              <ng-container *ngTemplateOutlet="progressBlock; context: { badge: badge }" />
            </article>
          }
        </div>
      </section>

      <section class="space-y-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2 class="text-xl font-bold text-slate-800">Freigeschaltet</h2>
            <p class="text-sm text-slate-500">Meilensteine, die du bereits erreicht hast.</p>
          </div>
          <span class="text-sm font-semibold text-slate-500">{{ earnedBadges().length }} erreicht</span>
        </div>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,17rem),1fr))] gap-4">
          @for (badge of earnedBadges(); track badge.id) {
            <article class="rounded-2xl border border-teal-100 bg-teal-50/50 p-5">
              <ng-container *ngTemplateOutlet="badgeHeader; context: { badge: badge }" />
              <p class="mt-3 text-sm leading-6 text-slate-600">{{ badge.description }}</p>
              <p class="mt-4 rounded-xl bg-white px-3 py-2 text-sm font-semibold text-teal-700">
                Freigeschaltet am {{ formatDate(badge.earnedAt) }}
              </p>
            </article>
          }
        </div>
      </section>

      <section class="space-y-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2 class="text-xl font-bold text-slate-800">Noch offen</h2>
            <p class="text-sm text-slate-500">Badges, die später durch passende Routinen freigeschaltet werden.</p>
          </div>
          <span class="text-sm font-semibold text-slate-500">{{ lockedBadges().length }} offen</span>
        </div>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,17rem),1fr))] gap-4">
          @for (badge of lockedBadges(); track badge.id) {
            <article class="rounded-2xl border border-slate-100 bg-white p-5 opacity-90">
              <ng-container *ngTemplateOutlet="badgeHeader; context: { badge: badge }" />
              <p class="mt-3 text-sm leading-6 text-slate-500">{{ badge.description }}</p>
              <p class="mt-4 rounded-xl bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-600">
                Wird sichtbar, sobald passende Aktivitäten starten.
              </p>
            </article>
          }
        </div>
      </section>

      <ng-template #badgeHeader let-badge="badge">
        <div class="flex items-start gap-3">
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-base font-black" [ngClass]="badgeToneClass(badge)">
            {{ badge.icon }}
          </span>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-base font-bold text-slate-800">{{ badge.title }}</h3>
              <span class="rounded-full px-2.5 py-1 text-xs font-bold uppercase tracking-wide" [ngClass]="statusClass(badge)">
                {{ statusLabel(badge) }}
              </span>
            </div>
            <p class="mt-1 text-sm font-semibold text-slate-500">{{ categoryLabel(badge.category) }}</p>
          </div>
        </div>
      </ng-template>

      <ng-template #progressBlock let-badge="badge">
        <div class="mt-4">
          <div class="flex items-center justify-between gap-3 text-sm">
            <span class="font-semibold text-slate-700">{{ badge.progressCurrent }}/{{ badge.progressTarget }}</span>
            <span class="text-slate-500">{{ progressLabel(badge) }}</span>
          </div>
          <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-100" role="progressbar"
               [attr.aria-valuenow]="badge.progressCurrent" [attr.aria-valuemin]="0" [attr.aria-valuemax]="badge.progressTarget">
            <div class="h-full rounded-full bg-teal-500" [style.width.%]="badge.progressPercent"></div>
          </div>
        </div>
      </ng-template>
    </div>
  `,
})
export class EmployeeBadgesComponent {
  private badgeDemo = inject(EmployeeBadgesDemoService);

  badges = this.badgeDemo.getBadges();

  inProgressBadges(): EmployeeBadge[] {
    return this.badges.filter(badge => badge.status === 'IN_PROGRESS');
  }

  earnedBadges(): EmployeeBadge[] {
    return this.badges.filter(badge => badge.status === 'EARNED');
  }

  lockedBadges(): EmployeeBadge[] {
    return this.badges.filter(badge => badge.status === 'LOCKED');
  }

  categoryLabel(category: BadgeCategory): string {
    return BADGE_CATEGORY_LABELS[category];
  }

  statusLabel(badge: EmployeeBadge): string {
    switch (badge.status) {
      case 'EARNED': return 'frei';
      case 'IN_PROGRESS': return 'in Arbeit';
      default: return 'offen';
    }
  }

  statusClass(badge: EmployeeBadge): string {
    switch (badge.status) {
      case 'EARNED': return 'bg-teal-50 text-teal-700';
      case 'IN_PROGRESS': return 'bg-amber-50 text-amber-700';
      default: return 'bg-slate-100 text-slate-600';
    }
  }

  progressLabel(badge: EmployeeBadge): string {
    switch (badge.id) {
      case 'seven-day-compass': return 'Tage';
      case 'sleep-series': return 'Tage';
      case 'hydration-series': return 'Tage';
      case 'vitamin-d-routine': return 'Routinen';
      default: return 'Schritte';
    }
  }

  formatDate(date: string | undefined): string {
    if (!date) return 'Demo';
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(date));
  }

  badgeToneClass(badge: EmployeeBadge): string {
    switch (badge.tone) {
      case 'blue': return 'bg-blue-50 text-blue-700';
      case 'amber': return 'bg-amber-50 text-amber-700';
      case 'violet': return 'bg-violet-50 text-violet-700';
      case 'slate': return 'bg-slate-100 text-slate-700';
      default: return 'bg-teal-50 text-teal-700';
    }
  }
}
