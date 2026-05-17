import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { EmployeeService } from '../../services/employee.service';

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

      <div *ngIf="loading()" class="flex justify-center py-12">
        <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
      </div>

      <div *ngIf="!loading() && measures().length === 0" class="bg-white rounded-3xl border border-slate-100 p-10 text-center text-slate-400">
        Aktuell sind keine aktiven Maßnahmen verfügbar.
      </div>

      <div *ngIf="!loading()" class="space-y-4">
        <div *ngFor="let measure of measures()" class="bg-white rounded-2xl border border-slate-100 p-6 space-y-2">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h2 class="font-bold text-slate-800">{{ measure.title }}</h2>
              <p class="text-sm text-slate-500 mt-1">{{ measure.description }}</p>
            </div>
            <span class="px-2 py-0.5 rounded-full text-xs bg-teal-50 text-teal-700">{{ measure.category }}</span>
          </div>
          <p class="text-xs text-slate-400">{{ measure.team?.name || 'Alle Teams' }}</p>
        </div>
      </div>
    </div>
  `
})
export class EmployeeMeasuresComponent implements OnInit {
  private employeeService = inject(EmployeeService);
  measures = signal<any[]>([]);
  loading = signal(true);

  ngOnInit() {
    this.employeeService.getMeasures().subscribe({
      next: measures => { this.measures.set(measures); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }
}
