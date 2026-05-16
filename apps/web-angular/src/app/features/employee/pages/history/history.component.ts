import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { EmployeeService, WellbeingEntry } from '../../services/employee.service';

@Component({
  selector: 'app-history',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="max-w-2xl mx-auto p-4 space-y-6">
      <header class="flex items-center space-x-4">
        <a routerLink="/employee" class="p-2 hover:bg-slate-100 rounded-full transition-colors text-slate-500">
           ←
        </a>
        <h1 class="text-xl font-bold text-slate-800">Check-in History</h1>
      </header>

      <div class="space-y-4" *ngIf="entries().length; else emptyState">
        <div *ngFor="let entry of entries()" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 space-y-3">
          <div class="flex justify-between items-start">
            <div class="flex items-center space-x-3">
              <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl"
                   [style.backgroundColor]="getScoreColor(entry.score) + '20'"
                   [style.color]="getScoreColor(entry.score)">
                {{ getMoodEmoji(entry.mood) }}
              </div>
              <div>
                <div class="font-bold text-slate-800">Mood {{ entry.mood ?? '-' }}/10</div>
                <div class="text-sm text-slate-400">{{ entry.createdAt | date:'EEEE, d. MMMM' }}</div>
              </div>
            </div>
            <div class="text-right">
              <div class="text-2xl font-black" [style.color]="getScoreColor(entry.score)">
                {{ entry.score.toFixed(1) }}
              </div>
              <div class="text-[10px] font-bold uppercase tracking-widest text-slate-300">Score</div>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-50">
             <div class="text-center p-2 rounded-xl bg-slate-50">
               <div class="text-xs text-slate-400">Stress</div>
               <div class="font-bold text-slate-700">{{ entry.stress ?? '-' }}/10</div>
             </div>
             <div class="text-center p-2 rounded-xl bg-slate-50">
               <div class="text-xs text-slate-400">Energy</div>
               <div class="font-bold text-slate-700">{{ entry.energy ?? '-' }}/10</div>
             </div>
             <div class="text-center p-2 rounded-xl bg-slate-50">
               <div class="text-xs text-slate-400">Mood</div>
               <div class="font-bold text-slate-700">{{ entry.mood ?? '-' }}/10</div>
             </div>
          </div>

          <div *ngIf="entry.notes" class="bg-teal-50/50 p-3 rounded-xl text-sm text-slate-600 italic border-l-2 border-teal-200">
            "{{ entry.notes }}"
          </div>
        </div>
      </div>

      <ng-template #emptyState>
        <div class="bg-white rounded-3xl p-12 text-center border border-dashed border-slate-200">
          <div class="text-4xl mb-4">📜</div>
          <h3 class="text-lg font-bold text-slate-800 mb-2">No history yet</h3>
          <p class="text-slate-500 mb-6">Complete your first check-in to see your history here.</p>
          <a routerLink="/employee/checkin" class="inline-block bg-teal-600 text-white px-8 py-3 rounded-2xl font-bold shadow-lg shadow-teal-100">
            Start Now
          </a>
        </div>
      </ng-template>
    </div>
  `
})
export class HistoryComponent implements OnInit {
  private employeeService = inject(EmployeeService);
  entries = signal<WellbeingEntry[]>([]);

  ngOnInit() {
    this.employeeService.getHistory().subscribe(entries => {
      this.entries.set(entries);
    });
  }

  getScoreColor(score: number) {
    if (score >= 7.5) return "#14b8a6";
    if (score >= 6) return "#4c8448";
    if (score >= 4.5) return "#d97706";
    return "#ef4444";
  }

  getMoodEmoji(mood: string | null) {
    switch (mood) {
      case 'GREAT': return '🤩';
      case 'GOOD': return '😊';
      case 'OKAY': return '😐';
      case 'BAD': return '😟';
      case 'TERRIBLE': return '😫';
      default: return '✨';
    }
  }
}
