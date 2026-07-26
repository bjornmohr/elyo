import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { EmployeeService, DashboardData } from '../../services/employee.service';
import { getMoodEmoji, getScoreColor } from '../../wellbeing-scale';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="max-w-4xl mx-auto p-4 space-y-6">
      <header class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-slate-800">Hallo!</h1>
        <div class="flex items-center space-x-4">
          <div class="bg-teal-100 text-teal-700 px-3 py-1 rounded-full text-sm font-medium flex items-center">
            <span class="w-2 h-2 bg-teal-500 rounded-full mr-2 animate-pulse"></span>
            {{ data()?.points || 0 }} Punkte
          </div>
          <div class="bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-sm font-medium flex items-center">
             🔥 {{ data()?.streak || 0 }} Tage in Folge
          </div>
        </div>
      </header>

      <!-- Checkin Card -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col md:flex-row items-center justify-between gap-4">
        <div>
          <h2 class="text-lg font-semibold text-slate-800">Wie fühlst du dich heute?</h2>
          <p class="text-slate-500 text-sm">Tracke dein Wohlbefinden und sammle Punkte.</p>
        </div>
        @if (data()?.todayCheckinCompleted) {
          <span class="bg-slate-100 text-slate-500 px-6 py-2 rounded-xl font-medium">Heute erledigt</span>
        } @else {
          <a routerLink="/employee/checkin" class="bg-teal-600 hover:bg-teal-700 text-white px-6 py-2 rounded-xl transition-colors font-medium">
            Check-in starten
          </a>
        }
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Recent History -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
          <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold text-slate-800">Letzte Aktivitäten</h3>
            <a routerLink="/employee/history" class="text-teal-600 text-sm font-medium hover:underline">Alle anzeigen</a>
          </div>
          @if (data()?.recentEntries?.length) {
            <div class="space-y-4">
              @for (entry of data()?.recentEntries; track entry.id) {
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50">
                  <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-lg" [style.backgroundColor]="getScoreColor(entry.score) + '20'" [style.color]="getScoreColor(entry.score)">
                      {{ getMoodEmoji(entry.mood) }}
                    </div>
                    <div>
                      <div class="font-medium text-slate-800">Score {{ entry.score.toFixed(1) }}/5</div>
                      <div class="text-xs text-slate-500">{{ entry.createdAt | date:'MMM d, HH:mm' }}</div>
                    </div>
                  </div>
                  <div class="font-bold" [style.color]="getScoreColor(entry.score)">
                    {{ entry.score.toFixed(1) }}/5
                  </div>
                </div>
              }
            </div>
          } @else {
            <p class="text-center text-slate-400 py-8 italic">Noch keine Einträge vorhanden.</p>
          }
        </div>

        <!-- Surveys -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
          <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold text-slate-800">Verfügbare Umfragen</h3>
            <a routerLink="/employee/surveys" class="text-teal-600 text-sm font-medium hover:underline">Alle anzeigen</a>
          </div>
          <div class="space-y-4">
             <div class="p-4 rounded-xl border border-dashed border-slate-200 text-center text-slate-500">
               Schau später wieder vorbei für neue Umfragen.
             </div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex items-center justify-between">
        <div>
          <h3 class="font-semibold text-slate-800">Aktive Maßnahmen</h3>
          <p class="text-sm text-slate-500">Prüfe verfügbare Unternehmens- und Team-Maßnahmen.</p>
        </div>
        <a routerLink="/employee/measures" class="bg-slate-100 hover:bg-teal-600 hover:text-white text-slate-600 px-5 py-2 rounded-xl transition-colors font-medium">
          Anzeigen
        </a>
      </div>
    </div>
  `
})
export class DashboardComponent implements OnInit {
  private employeeService = inject(EmployeeService);
  data = signal<DashboardData | null>(null);

  protected readonly getScoreColor = getScoreColor;
  protected readonly getMoodEmoji = getMoodEmoji;

  ngOnInit() {
    this.employeeService.getDashboard().subscribe(data => {
      this.data.set(data);
    });
  }
}
