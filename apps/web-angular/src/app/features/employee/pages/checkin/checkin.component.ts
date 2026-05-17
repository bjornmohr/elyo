import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { EmployeeService } from '../../services/employee.service';
import { NotificationService } from '../../../../shared/notifications/notification.service';

@Component({
  selector: 'app-checkin',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="max-w-xl mx-auto p-4 space-y-8">
      <header class="flex items-center space-x-4">
        <a routerLink="/employee" class="p-2 hover:bg-slate-100 rounded-full transition-colors text-slate-500">
           ←
        </a>
        <h1 class="text-xl font-bold text-slate-800">Check-in</h1>
      </header>

      <div *ngIf="loading()" class="flex justify-center py-12">
        <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
      </div>

      <div *ngIf="!loading() && alreadyDone()" class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8 text-center space-y-4">
        <div class="text-5xl">✓</div>
        <h2 class="text-2xl font-bold text-slate-800">Check-in bereits abgeschlossen</h2>
        <p class="text-slate-500">Du kannst nur einen Wohlbefinden-Check-in pro Tag abgeben. Komm morgen für den nächsten zurück.</p>
        <a routerLink="/employee" class="inline-block bg-teal-600 text-white px-6 py-3 rounded-2xl font-bold">Zurück zur Übersicht</a>
      </div>

      <!-- Progress -->
      <div *ngIf="!loading() && !alreadyDone()" class="flex justify-between items-center px-2">
        <div *ngFor="let s of [0, 1, 2, 3]; let i = index"
             class="h-1 flex-1 mx-1 rounded-full transition-colors duration-500"
             [class.bg-teal-500]="i <= step()"
             [class.bg-slate-200]="i > step()">
        </div>
      </div>

      <div *ngIf="!loading() && !alreadyDone()" class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8 min-h-[400px] flex flex-col justify-between">
        <!-- Step 0: Mood -->
        <div *ngIf="step() === 0" class="space-y-6 text-center animate-in fade-in slide-in-from-bottom-4 duration-500">
          <h2 class="text-2xl font-bold text-slate-800">Wie fühlst du dich?</h2>
          <div class="text-6xl my-8">{{ getMoodEmoji(mood()) }}</div>
          <input type="range" min="1" max="10" [(ngModel)]="mood" class="w-full h-2 bg-slate-100 rounded-lg appearance-none cursor-pointer accent-teal-600">
          <div class="flex justify-between text-xs text-slate-400 font-medium uppercase tracking-wider">
            <span>Schlecht</span>
            <span>Sehr gut</span>
          </div>
        </div>

        <!-- Step 1: Stress -->
        <div *ngIf="step() === 1" class="space-y-6 text-center animate-in fade-in slide-in-from-right-4 duration-500">
          <h2 class="text-2xl font-bold text-slate-800">Stresslevel</h2>
          <div class="text-6xl my-8">{{ getStressEmoji(stress()) }}</div>
          <input type="range" min="1" max="10" [(ngModel)]="stress" class="w-full h-2 bg-slate-100 rounded-lg appearance-none cursor-pointer accent-teal-600">
          <div class="flex justify-between text-xs text-slate-400 font-medium uppercase tracking-wider">
            <span>Entspannt</span>
            <span>Gestresst</span>
          </div>
        </div>

        <!-- Step 2: Sleep -->
        <div *ngIf="step() === 2" class="space-y-6 text-center animate-in fade-in slide-in-from-right-4 duration-500">
          <h2 class="text-2xl font-bold text-slate-800">Energieniveau</h2>
          <div class="text-6xl my-8">⚡</div>
          <input type="range" min="1" max="10" [(ngModel)]="energy" class="w-full h-2 bg-slate-100 rounded-lg appearance-none cursor-pointer accent-teal-600">
          <div class="flex justify-between text-xs text-slate-400 font-medium uppercase tracking-wider">
            <span>Niedrig</span>
            <span>Hoch</span>
          </div>
        </div>

        <!-- Step 3: Notes -->
        <div *ngIf="step() === 3" class="space-y-6 animate-in fade-in slide-in-from-right-4 duration-500">
          <h2 class="text-2xl font-bold text-slate-800 text-center">Anmerkungen?</h2>
          <textarea [(ngModel)]="notes"
                    placeholder="Optional: Wie war dein Tag?"
                    class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-teal-500 focus:border-transparent outline-none min-h-[150px] resize-none"></textarea>
        </div>

        <!-- Actions -->
        <div class="flex space-x-3 mt-8">
          <button *ngIf="step() > 0"
                  (click)="prev()"
                  class="flex-1 py-4 px-6 rounded-2xl bg-slate-100 text-slate-600 font-semibold hover:bg-slate-200 transition-colors">
            Zurück
          </button>
          <button (click)="next()"
                  class="flex-[2] py-4 px-6 rounded-2xl bg-teal-600 text-white font-semibold hover:bg-teal-700 transition-all shadow-lg shadow-teal-200 disabled:opacity-50">
            {{ step() === 3 ? 'Abschließen' : 'Weiter' }}
          </button>
        </div>
        <p *ngIf="error()" class="text-sm text-red-600 text-center mt-3">{{ error() }}</p>
      </div>
    </div>
  `
})
export class CheckinComponent implements OnInit {
  private employeeService = inject(EmployeeService);
  private router = inject(Router);
  private notifications = inject(NotificationService);

  step = signal(0);
  mood = signal(5);
  stress = signal(5);
  energy = signal(5);
  notes = signal('');
  loading = signal(true);
  alreadyDone = signal(false);
  error = signal<string | null>(null);

  ngOnInit() {
    this.employeeService.getCheckinStatus().subscribe({
      next: status => {
        this.alreadyDone.set(status.completed);
        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });
  }

  next() {
    if (this.step() < 3) {
      this.step.set(this.step() + 1);
    } else {
      this.submit();
    }
  }

  prev() {
    if (this.step() > 0) {
      this.step.set(this.step() - 1);
    }
  }

  submit() {
    this.error.set(null);
    this.employeeService.submitCheckin({
      mood: this.mood(),
      stress: this.stress(),
      energy: this.energy(),
      notes: this.notes()
    }).subscribe({
      next: () => {
        this.notifications.success('Check-in wurde gespeichert.');
        this.router.navigate(['/employee']);
      },
      error: err => {
        if (err.status === 409) {
          this.alreadyDone.set(true);
        } else {
          const message = 'Check-in konnte nicht gespeichert werden.';
          this.error.set(message);
          this.notifications.error(message);
        }
      }
    });
  }

  getMoodEmoji(val: number) {
    if (val >= 9) return '🤩';
    if (val >= 7) return '😊';
    if (val >= 4) return '😐';
    if (val >= 2) return '😟';
    return '😫';
  }

  getStressEmoji(val: number) {
    if (val >= 9) return '💥';
    if (val >= 7) return '😫';
    if (val >= 4) return '😐';
    if (val >= 2) return '😌';
    return '🧘';
  }
}
