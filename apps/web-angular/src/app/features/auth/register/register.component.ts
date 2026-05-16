import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AuthLayoutComponent } from '../components/auth-layout.component';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [RouterLink, AuthLayoutComponent],
  template: `
    <app-auth-layout>
      <div class="animate-fade-up">
        <div class="mb-8">
          <h2 class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">
            Zugang nur per Einladung
          </h2>
          <p class="text-gray-400 text-sm mt-1">
            ELYO ist derzeit nur über eine Einladung zugänglich.
          </p>
        </div>

        <div class="rounded-xl p-6" style="background: #f0fdf9; border: 1px solid #ccfbef">
          <p class="text-sm text-gray-700 leading-relaxed">
            Unternehmen werden von einem ELYO-Administrator angelegt.
            Mitarbeiter erhalten eine persönliche Einladung von ihrem Unternehmen.
          </p>
          <p class="text-sm text-gray-500 mt-3">
            Falls du einen Einladungslink erhalten hast, klicke bitte direkt auf den Link in deiner E-Mail.
          </p>
        </div>

        <p class="text-center text-sm text-gray-400 mt-6">
          Bereits ein Konto?
          <a routerLink="/auth/login" class="font-semibold hover:underline" style="color: #14b8a6">
            Anmelden
          </a>
        </p>
      </div>
    </app-auth-layout>
  `
})
export class RegisterComponent {}
