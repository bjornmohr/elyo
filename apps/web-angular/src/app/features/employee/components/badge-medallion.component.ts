import { CommonModule } from '@angular/common';
import { Component, Input, inject } from '@angular/core';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { BadgeIconKey, BadgeStatus, BadgeTone } from '../models/badge.model';

const TONES: Record<BadgeTone, { base: string; dark: string; light: string; glow: string }> = {
  teal: { base: '#14b8a6', dark: '#0f766e', light: '#5eead4', glow: 'rgba(20,184,166,.45)' },
  blue: { base: '#3b82f6', dark: '#1d4ed8', light: '#93c5fd', glow: 'rgba(59,130,246,.40)' },
  amber: { base: '#f59e0b', dark: '#b45309', light: '#fcd34d', glow: 'rgba(245,158,11,.40)' },
  violet: { base: '#8b5cf6', dark: '#6d28d9', light: '#c4b5fd', glow: 'rgba(139,92,246,.40)' },
  slate: { base: '#64748b', dark: '#475569', light: '#cbd5e1', glow: 'rgba(100,116,139,.35)' },
};

const GLYPHS: Record<BadgeIconKey, string> = {
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

@Component({
  selector: 'app-badge-medallion',
  standalone: true,
  imports: [CommonModule],
  template: `
    <span class="relative grid shrink-0 place-items-center rounded-full"
          [style.width.px]="size"
          [style.height.px]="size"
          [style.background]="ringBackground()">
      <span class="grid place-items-center rounded-full"
            [style.width.px]="innerSize()"
            [style.height.px]="innerSize()"
            [style.background]="status === 'EARNED' ? '#f0fdfa' : '#ffffff'">
        <span class="grid place-items-center rounded-full"
              [style.width.px]="medalSize()"
              [style.height.px]="medalSize()"
              [style.background]="medalBackground()"
              [style.box-shadow]="medalShadow()">
          <svg viewBox="0 0 24 24" fill="none" [attr.width]="glyphSize()" [attr.height]="glyphSize()"
               [attr.stroke]="status === 'LOCKED' ? '#94a3b8' : '#ffffff'"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
               [style.filter]="status === 'LOCKED' ? 'none' : 'drop-shadow(0 1px 1px rgba(0,0,0,.25))'"
               [innerHTML]="glyphMarkup()"></svg>
        </span>
      </span>

      @if (status === 'EARNED') {
        <span class="absolute bottom-0 right-0 grid rounded-full border-2 border-white bg-teal-700 text-white"
              [style.width.px]="sealSize()" [style.height.px]="sealSize()" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.6" stroke-linecap="round" stroke-linejoin="round" class="m-auto h-3 w-3">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
        </span>
      } @else if (status === 'LOCKED') {
        <span class="absolute bottom-0 right-0 grid rounded-full border-2 border-white bg-slate-400 text-white"
              [style.width.px]="sealSize()" [style.height.px]="sealSize()" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" class="m-auto h-3 w-3">
            <rect x="4" y="11" width="16" height="10" rx="2"></rect>
            <path d="M8 11V7a4 4 0 0 1 8 0v4"></path>
          </svg>
        </span>
      }
    </span>
  `,
})
export class BadgeMedallionComponent {
  private sanitizer = inject(DomSanitizer);

  @Input({ required: true }) tone!: BadgeTone;
  @Input({ required: true }) iconKey!: BadgeIconKey;
  @Input({ required: true }) status!: BadgeStatus;
  @Input() progressPercent = 0;
  @Input() size = 60;

  ringBackground(): string {
    const tone = this.toneValues();
    const pct = Math.max(0, Math.min(100, this.progressPercent));
    if (this.status === 'LOCKED') return '#e5e7eb';
    if (this.status === 'EARNED') {
      return `conic-gradient(from -90deg, ${tone.light}, ${tone.base}, ${tone.dark}, ${tone.base}, ${tone.light})`;
    }

    return `conic-gradient(${tone.base} 0% ${pct}%, #e5e7eb ${pct}% 100%)`;
  }

  medalBackground(): string {
    const tone = this.toneValues();
    if (this.status === 'LOCKED') {
      return 'radial-gradient(circle at 34% 26%, #f8fafc 0%, #cbd5e1 55%, #94a3b8 100%)';
    }

    return `radial-gradient(circle at 34% 26%, ${tone.light} 0%, ${tone.base} 48%, ${tone.dark} 100%)`;
  }

  medalShadow(): string {
    const tone = this.toneValues();
    if (this.status === 'EARNED') return `0 10px 24px ${tone.glow}, inset 0 2px 3px rgba(255,255,255,.5)`;
    if (this.status === 'LOCKED') return 'inset 0 2px 3px rgba(255,255,255,.6)';
    return '0 6px 16px rgba(15,23,42,.1), inset 0 2px 3px rgba(255,255,255,.45)';
  }

  glyphMarkup(): SafeHtml {
    return this.sanitizer.bypassSecurityTrustHtml(GLYPHS[this.iconKey]);
  }

  innerSize(): number {
    return Math.round(this.size * 0.87);
  }

  medalSize(): number {
    return Math.round(this.size * 0.73);
  }

  glyphSize(): number {
    return Math.max(18, Math.round(this.size * 0.36));
  }

  sealSize(): number {
    return Math.max(20, Math.round(this.size * 0.33));
  }

  private toneValues() {
    return TONES[this.tone];
  }
}
