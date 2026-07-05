import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-auth-layout',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="min-h-screen flex" style="background: hsl(40, 20%, 97%)">
      <!-- Left panel — branding -->
      <div class="hidden lg:flex flex-col justify-between w-[420px] p-12 flex-shrink-0" style="background: #064e4b">
        <div>
          <!-- Logo -->
          <div class="mb-16 inline-flex rounded-2xl bg-white px-4 py-3">
            <img src="assets/brand/elyo-logo.png" alt="ELYO" class="h-auto w-32 object-contain" />
          </div>
          <!-- Headline -->
          <div class="space-y-4">
            <h1 class="text-4xl font-semibold leading-tight text-white" style="font-family: 'Fraunces', Georgia, serif">
              Wellbeing,<br />das wirklich<br />wirkt.
            </h1>
            <p class="text-sm leading-relaxed" style="color: rgba(255,255,255,0.5)">
              Anonymisierte Einblicke. Persönliches Wohlbefinden.<br />
              Datenschutz by Design.
            </p>
          </div>
        </div>
        <!-- Feature pills -->
        <div class="space-y-3">
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm" style="background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.7)">
            <span>🔒</span> Daten immer anonymisiert
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm" style="background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.7)">
            <span>📊</span> Wöchentliche Check-ins
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm" style="background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.7)">
            <span>🌱</span> Team-Gesundheit im Blick
          </div>
        </div>
      </div>
      <!-- Right panel — form -->
      <div class="flex-1 flex items-center justify-center p-6">
        <div class="w-full max-w-sm">
          <!-- Mobile logo -->
          <div class="mb-10 lg:hidden">
            <img src="assets/brand/elyo-logo.png" alt="ELYO" class="h-auto w-32 object-contain" />
          </div>
          <ng-content></ng-content>
        </div>
      </div>
    </div>
  `,
  styles: [`
    :host {
      display: block;
      width: 100%;
    }
  `]
})
export class AuthLayoutComponent {}
