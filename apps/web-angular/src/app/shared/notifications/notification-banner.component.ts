import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { NotificationService } from './notification.service';

@Component({
  selector: 'app-notification-banner',
  standalone: true,
  imports: [CommonModule],
  template: `
    @if (notifications.current(); as notification) {
      <div
        role="status"
        class="mb-6 rounded-lg border px-4 py-3 text-sm font-medium"
        [ngClass]="{
          'border-green-200 bg-green-50 text-green-800': notification.kind === 'success',
          'border-red-200 bg-red-50 text-red-800': notification.kind === 'error'
        }"
      >
        {{ notification.message }}
      </div>
    }
  `
})
export class NotificationBannerComponent {
  notifications = inject(NotificationService);
}
