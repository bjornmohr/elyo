import { CommonModule } from '@angular/common';
import { Component, OnInit, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { EmployeeService } from '../../services/employee.service';
import { NotificationService } from '../../../../shared/notifications/notification.service';

@Component({
  selector: 'app-employee-measure-checkin',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="mx-auto max-w-xl p-4">
      <div class="rounded-xl border border-slate-100 bg-white p-6">
        <h1 class="text-2xl font-bold text-slate-800">Maßnahme Check-in</h1>
        @if (loading()) {
          <p class="mt-3 text-sm text-slate-500">Check-in wird gespeichert…</p>
        } @else if (success()) {
          <p class="mt-3 text-sm text-emerald-700">Teilnahme wurde gespeichert.</p>
          <a routerLink="/employee/measures" class="mt-5 inline-flex min-h-11 items-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">
            Zu den Maßnahmen
          </a>
        } @else {
          <p class="mt-3 text-sm text-red-700">{{ errorMessage() }}</p>
          <a routerLink="/employee/measures" class="mt-5 inline-flex min-h-11 items-center rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">
            Zu den Maßnahmen
          </a>
        }
      </div>
    </div>
  `
})
export class EmployeeMeasureCheckinComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private employeeService = inject(EmployeeService);
  private notifications = inject(NotificationService);

  loading = signal(true);
  success = signal(false);
  errorMessage = signal('Check-in konnte nicht gespeichert werden.');

  ngOnInit() {
    const token = this.route.snapshot.paramMap.get('token');
    if (!token) {
      this.loading.set(false);
      this.errorMessage.set('Check-in-Link ist ungültig.');
      return;
    }

    this.employeeService.redeemMeasureCheckin(token).subscribe({
      next: () => {
        this.success.set(true);
        this.loading.set(false);
        this.notifications.success('Teilnahme wurde gespeichert.');
      },
      error: err => {
        const code = err.error?.error?.code;
        if (code === 'MEASURE_ALREADY_PARTICIPATED') {
          this.notifications.success('Teilnahme ist bereits gespeichert.');
          this.router.navigate(['/employee/measures']);
          return;
        }

        this.errorMessage.set(this.messageFor(code, err.status));
        this.notifications.error(this.errorMessage());
        this.loading.set(false);
      },
    });
  }

  private messageFor(code: string | undefined, status: number) {
    if (status === 404) return 'Check-in-Link ist ungültig oder für dich nicht verfügbar.';
    if (code === 'MEASURE_NOT_ACTIVE') return 'Diese Maßnahme ist aktuell nicht aktiv.';
    if (code === 'CHECKIN_TOKEN_REVOKED') return 'Dieser Check-in-Link wurde ersetzt.';
    if (code === 'CHECKIN_TOKEN_NOT_YET_VALID') return 'Dieser Check-in-Link ist noch nicht gültig.';
    if (code === 'CHECKIN_TOKEN_EXPIRED') return 'Dieser Check-in-Link ist abgelaufen.';
    return 'Check-in konnte nicht gespeichert werden.';
  }
}
