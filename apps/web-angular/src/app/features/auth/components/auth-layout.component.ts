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
          <div class="flex items-center gap-3 mb-16">
            <div class="w-10 h-10 rounded-2xl flex items-center justify-center" style="background: linear-gradient(135deg, #14b8a6, #0d9488)">
              <svg width="20" height="20" viewBox="0 0 16 16" fill="none">
                <path d="M8 2C8 2 3 5.5 3 9a5 5 0 0010 0C13 5.5 8 2 8 2z" fill="white" fill-opacity="0.9"/>
                <path d="M8 6v4M6 8h4" stroke="#0a4540" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
            </div>
            <span class="text-white text-xl font-semibold" style="font-family: 'Fraunces', Georgia, serif">
              Elyo
            </span>
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
          <div class="flex items-center gap-3 mb-10 lg:hidden">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: linear-gradient(135deg, #14b8a6, #0d9488)">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M8 2C8 2 3 5.5 3 9a5 5 0 0010 0C13 5.5 8 2 8 2z" fill="white" fill-opacity="0.9"/>
                <path d="M8 6v4M6 8h4" stroke="#0a4540" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
            </div>
            <span class="text-gray-900 text-lg font-semibold" style="font-family: 'Fraunces', Georgia, serif">
              Elyo
            </span>
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
