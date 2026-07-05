import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { BadgeCategory, BadgeIconKey, EmployeeBadge } from '../../models/badge.model';
import { BADGE_CATEGORY_LABELS, EmployeeBadgesDemoService } from '../../services/employee-badges-demo.service';
import { BadgeMedallionComponent } from '../../components/badge-medallion.component';
import { BadgeDetailModalComponent } from '../../components/badge-detail-modal.component';

const CATEGORY_ORDER: BadgeCategory[] = ['STARTER', 'STREAK', 'QUEST', 'RECOVERY', 'INSIGHT', 'LAB', 'PREVENTION'];

const CATEGORY_META: Record<BadgeCategory, { iconKey: BadgeIconKey; tone: EmployeeBadge['tone']; explanation: string }> = {
  STARTER: {
    iconKey: 'check',
    tone: 'teal',
    explanation: 'Deine ersten Schritte in ELYO — Screening, erster Check-in und erste Maßnahme.',
  },
  STREAK: {
    iconKey: 'compass',
    tone: 'amber',
    explanation: 'Belohnt Regelmäßigkeit — Serien aus täglichen Check-ins und Routinen.',
  },
  QUEST: {
    iconKey: 'activity',
    tone: 'violet',
    explanation: 'Kurze Aufgaben wie Atem-, Entspannungs- oder Mobilitätsübungen.',
  },
  RECOVERY: {
    iconKey: 'pause',
    tone: 'amber',
    explanation: 'Würdigt kluge Pausen und den Wiedereinstieg nach einer Auszeit.',
  },
  INSIGHT: {
    iconKey: 'target',
    tone: 'violet',
    explanation: 'Für das bewusste Wahrnehmen von Körpersignalen und Mustern.',
  },
  LAB: {
    iconKey: 'flask',
    tone: 'blue',
    explanation: 'Rund um Laborwerte — verstehen und in Routinen übersetzen.',
  },
  PREVENTION: {
    iconKey: 'shield',
    tone: 'violet',
    explanation: 'Vollständige Präventionszyklen aus Screening und passenden Maßnahmen.',
  },
};

const CATEGORY_GLYPHS: Record<BadgeIconKey, string> = {
  check: '<polyline points="20 6 9 17 4 12"></polyline>',
  checkcircle: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>',
  flask: '<path d="M9 3h6M10 3v6l-4.6 8.5A2 2 0 0 0 7.2 21h9.6a2 2 0 0 0 1.8-3.5L14 9V3"></path>',
  compass: '<circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88"></polygon>',
  moon: '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>',
  wind: '<path d="M9.59 4.59A2 2 0 1 1 11 8H2m10.59 11.41A2 2 0 1 0 14 16H2m15.73-8.27A2.5 2.5 0 1 1 19.5 12H2"></path>',
  sun: '<circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path>',
  droplet: '<path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>',
  activity: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>',
  target: '<circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle>',
  shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>',
  pause: '<rect x="6" y="4" width="4" height="16" rx="1"></rect><rect x="14" y="4" width="4" height="16" rx="1"></rect>',
  refresh: '<polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>',
};

const TONE_GRADIENTS: Record<EmployeeBadge['tone'], string> = {
  teal: 'radial-gradient(circle at 34% 26%, #5eead4 0%, #14b8a6 48%, #0f766e 100%)',
  blue: 'radial-gradient(circle at 34% 26%, #93c5fd 0%, #3b82f6 48%, #1d4ed8 100%)',
  amber: 'radial-gradient(circle at 34% 26%, #fcd34d 0%, #f59e0b 48%, #b45309 100%)',
  violet: 'radial-gradient(circle at 34% 26%, #c4b5fd 0%, #8b5cf6 48%, #6d28d9 100%)',
  slate: 'radial-gradient(circle at 34% 26%, #cbd5e1 0%, #64748b 48%, #475569 100%)',
};

@Component({
  selector: 'app-employee-badges',
  standalone: true,
  imports: [CommonModule, BadgeMedallionComponent, BadgeDetailModalComponent],
  styles: [`
    @keyframes badge-flicker {
      0%, 100% { transform: scale(1) rotate(-3deg); }
      50% { transform: scale(1.1) rotate(3deg); }
    }

    @keyframes badge-pulse {
      0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, .45); }
      70% { box-shadow: 0 0 0 16px rgba(245, 158, 11, 0); }
      100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
    }
  `],
  template: `
    <div class="mx-auto w-full max-w-[min(100%,76rem)] space-y-7">
      <header>
        <h1 class="text-[30px] font-bold leading-tight text-slate-800">Präventions-Badges</h1>
        <p class="mt-2 text-base leading-7 text-slate-500">Deine Routinen, Serien und Meilensteine an einem Ort.</p>
      </header>

      <section class="flex flex-col gap-4 rounded-[18px] bg-gradient-to-br from-orange-400 to-orange-600 px-5 py-4 text-white shadow-sm sm:flex-row sm:items-center">
        <div class="grid h-[54px] w-[54px] shrink-0 place-items-center rounded-full bg-white/20" style="animation: badge-flicker 2.6s ease-in-out infinite">
          <svg viewBox="0 0 24 24" width="28" height="28" fill="rgba(255,255,255,.28)" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"></path>
          </svg>
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-xs font-bold uppercase tracking-wide text-white/85">DEINE SERIE</p>
          <h2 class="text-[21px] font-bold leading-tight">{{ streakDays() }} Tage in Folge</h2>
          <p class="mt-1 text-sm text-white/90">{{ streakHeadline() }}</p>
        </div>
        <div class="flex gap-1.5">
          @for (dot of weekDots(); track dot.index) {
            <span class="grid h-[22px] w-[22px] place-items-center rounded-full text-xs font-bold"
                  [ngClass]="dot.done ? 'bg-white text-orange-600' : 'border-2 border-white/50 bg-white/10 text-white/85'">
              {{ dot.label }}
            </span>
          }
        </div>
      </section>

      @if (nextGoalBadge(); as badge) {
        <button type="button"
                class="w-full rounded-[20px] border border-slate-100 bg-white px-6 py-[22px] text-left shadow-[0_6px_20px_rgba(15,23,42,.05)] transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
                (click)="openBadge(badge)">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <span class="text-xs font-bold uppercase tracking-wide text-teal-700">DEIN NÄCHSTES ZIEL</span>
            <span class="text-xs text-slate-500">automatisch gewählt · am nächsten dran</span>
          </div>
          <div class="mt-5 flex flex-col gap-5 sm:flex-row sm:items-center">
            <div class="grid shrink-0 place-items-center rounded-full" style="animation: badge-pulse 2.4s ease-out infinite">
              <app-badge-medallion [tone]="badge.tone" [iconKey]="badge.iconKey" [status]="badge.status" [progressPercent]="badge.progressPercent" [size]="104" />
            </div>
            <div class="min-w-0 flex-1">
              <h3 class="text-[20px] font-bold leading-tight text-slate-800">{{ badge.title }}</h3>
              <p class="mt-2 text-sm leading-6 text-slate-500">{{ nextGoalCopy(badge) }}</p>
              <div class="mt-4 flex items-baseline justify-between gap-3 text-sm">
                <span class="font-bold text-slate-700">{{ badge.progressCurrent }}/{{ badge.progressTarget }} {{ progressUnit(badge) }}</span>
                <span class="text-slate-500">{{ badge.progressPercent }}%</span>
              </div>
              <div class="mt-2 h-[9px] overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-gradient-to-r from-amber-300 to-amber-500" [style.width.%]="badge.progressPercent"></div>
              </div>
            </div>
          </div>
          <p class="mt-5 border-t border-slate-100 pt-4 text-sm leading-6 text-slate-500">
            Hier steht das Badge, dem du gerade am nächsten bist ({{ badge.progressPercent }}%).
          </p>
        </button>
      }

      <section>
        <h2 class="text-[22px] font-bold leading-tight text-slate-800">Deine Sammlungen</h2>
        <p class="mt-1 text-sm text-slate-500">Tippe auf eine Sammlung, um zu sehen, wofür sie steht.</p>

        <div class="mt-4 space-y-3">
          @for (group of categoryGroups(); track group.category) {
            <article class="overflow-hidden rounded-[18px] border border-slate-100 bg-white">
              <button type="button"
                      class="flex w-full items-center gap-3 px-[18px] py-4 text-left focus:outline-none focus:ring-2 focus:ring-inset focus:ring-teal-500"
                      [attr.aria-expanded]="isCategoryOpen(group.category)"
                      (click)="toggleCategory(group.category)">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-[14px] shadow-[0_4px_10px_rgba(15,23,42,.12)]" [style.background]="categoryGradient(group.category)">
                  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" [innerHTML]="categoryGlyph(group.category)"></svg>
                </span>
                <span class="min-w-0 flex-1">
                  <span class="block text-base font-semibold text-slate-800">{{ categoryLabel(group.category) }}</span>
                  <span class="mt-0.5 block text-sm text-slate-500">{{ group.earnedCount }}/{{ group.badges.length }} freigeschaltet</span>
                </span>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                     class="shrink-0 transition-transform" [class.rotate-180]="isCategoryOpen(group.category)">
                  <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
              </button>

              @if (isCategoryOpen(group.category)) {
                <div class="px-[18px] pb-[18px]">
                  <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-500">{{ group.explanation }}</p>
                  <div class="mt-3 grid grid-cols-[repeat(auto-fit,minmax(min(100%,13.5rem),1fr))] gap-3">
                    @for (badge of group.badges; track badge.id) {
                      <button type="button"
                              class="flex items-center gap-3 rounded-2xl border p-3 text-left transition hover:-translate-y-0.5 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
                              [ngClass]="badgeCardClass(badge)"
                              (click)="openBadge(badge)">
                        <app-badge-medallion [tone]="badge.tone" [iconKey]="badge.iconKey" [status]="badge.status" [progressPercent]="badge.progressPercent" [size]="60" />
                        <span class="min-w-0 flex-1">
                          <span class="block text-[15px] font-semibold leading-tight text-slate-800">{{ badge.title }}</span>
                          <span class="mt-1 block text-xs font-semibold text-slate-500">{{ statusText(badge) }}</span>
                        </span>
                      </button>
                    }
                  </div>
                </div>
              }
            </article>
          }
        </div>
      </section>

      <app-badge-detail-modal [badge]="selectedBadge()" (close)="closeBadgeDetail()" />
    </div>
  `,
})
export class EmployeeBadgesComponent {
  private badgeDemo = inject(EmployeeBadgesDemoService);
  private sanitizer = inject(DomSanitizer);

  badges = this.badgeDemo.getBadges();
  private selectedBadgeSignal = signal<EmployeeBadge | null>(null);
  private openCategoriesSignal = signal<Record<BadgeCategory, boolean>>(this.defaultOpenCategories());

  nextGoalBadge(): EmployeeBadge | null {
    return this.badgeDemo.nextGoalBadge(this.streakDays());
  }

  selectedBadge(): EmployeeBadge | null {
    return this.selectedBadgeSignal();
  }

  openBadge(badge: EmployeeBadge): void {
    this.selectedBadgeSignal.set(badge);
  }

  closeBadgeDetail(): void {
    this.selectedBadgeSignal.set(null);
  }

  toggleCategory(category: BadgeCategory): void {
    this.openCategoriesSignal.set({
      ...this.openCategoriesSignal(),
      [category]: !this.isCategoryOpen(category),
    });
  }

  isCategoryOpen(category: BadgeCategory): boolean {
    return this.openCategoriesSignal()[category] ?? false;
  }

  categoryGroups(): Array<{ category: BadgeCategory; badges: EmployeeBadge[]; earnedCount: number; explanation: string }> {
    return CATEGORY_ORDER
      .map(category => {
        const badges = this.badges.filter(badge => badge.category === category);
        return {
          category,
          badges,
          earnedCount: badges.filter(badge => badge.status === 'EARNED').length,
          explanation: CATEGORY_META[category].explanation,
        };
      })
      .filter(group => group.badges.length > 0);
  }

  streakDays(): number {
    const compass = this.badges.find(badge => badge.id === 'seven-day-compass');
    return Math.max(0, Math.min(7, compass?.progressCurrent ?? 0));
  }

  streakHeadline(): string {
    const remaining = Math.max(0, 7 - this.streakDays());
    return remaining > 0
      ? `Noch ${remaining} ${remaining === 1 ? 'Tag' : 'Tage'} bis zum 7-Tage-Kompass.`
      : '7-Tage-Kompass erreicht.';
  }

  weekDots(): Array<{ index: number; done: boolean; label: string }> {
    const labels = ['M', 'D', 'M', 'D', 'F', 'S', 'S'];
    return labels.map((label, index) => ({
      index,
      done: index < this.streakDays(),
      label: index < this.streakDays() ? '✓' : label,
    }));
  }

  nextGoalCopy(badge: EmployeeBadge): string {
    const remaining = Math.max(0, badge.progressTarget - badge.progressCurrent);
    if (remaining <= 0) return 'Geschafft — dieses Badge ist erreicht.';
    if (badge.id === 'seven-day-compass') {
      return `Noch ${remaining} ${remaining === 1 ? 'Check-in' : 'Check-ins'} bis zum Badge.`;
    }

    return `Noch ${remaining} ${this.progressUnit(badge)} bis zum Badge.`;
  }

  categoryLabel(category: BadgeCategory): string {
    return BADGE_CATEGORY_LABELS[category];
  }

  categoryGradient(category: BadgeCategory): string {
    return TONE_GRADIENTS[CATEGORY_META[category].tone];
  }

  categoryGlyph(category: BadgeCategory): SafeHtml {
    return this.sanitizer.bypassSecurityTrustHtml(CATEGORY_GLYPHS[CATEGORY_META[category].iconKey]);
  }

  statusText(badge: EmployeeBadge): string {
    if (badge.status === 'EARNED') return `Frei · ${this.formatShortDate(badge.earnedAt)}`;
    if (badge.status === 'LOCKED') return 'Noch gesperrt';
    return `${badge.progressCurrent}/${badge.progressTarget} ${this.progressUnit(badge)}`;
  }

  progressUnit(badge: EmployeeBadge): string {
    return badge.unit ?? 'geschafft';
  }

  badgeCardClass(badge: EmployeeBadge): string {
    switch (badge.status) {
      case 'EARNED': return 'border-teal-200 bg-teal-50/70';
      case 'LOCKED': return 'border-slate-100 bg-slate-50/90';
      default: return 'border-slate-100 bg-white';
    }
  }

  formatShortDate(date: string | undefined): string {
    if (!date) return 'Demo';
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit' }).format(new Date(date));
  }

  private defaultOpenCategories(): Record<BadgeCategory, boolean> {
    return CATEGORY_ORDER.reduce((state, category) => ({
      ...state,
      [category]: this.badges.some(badge => badge.category === category && badge.status === 'IN_PROGRESS'),
    }), {} as Record<BadgeCategory, boolean>);
  }
}
