import { Component } from '@angular/core';

@Component({
  selector: 'app-company-surveys',
  standalone: true,
  template: `
    <div>
      <h1 class="text-2xl font-semibold text-gray-900 mb-6" style="font-family: 'Fraunces', Georgia, serif">Umfragen</h1>
      <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
        <p class="text-gray-500 text-sm">Noch keine Daten vorhanden.</p>
      </div>
    </div>
  `
})
export class CompanySurveysComponent {}
