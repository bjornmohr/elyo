import { Injectable, signal } from '@angular/core';

export type NotificationKind = 'success' | 'error';

export interface ViewNotification {
  kind: NotificationKind;
  message: string;
}

@Injectable({
  providedIn: 'root'
})
export class NotificationService {
  readonly current = signal<ViewNotification | null>(null);
  private timeoutId: ReturnType<typeof setTimeout> | null = null;

  success(message = 'Die Daten wurden gespeichert.'): void {
    this.show('success', message);
  }

  error(message = 'Die Daten konnten nicht gespeichert werden.'): void {
    this.show('error', message);
  }

  clear(): void {
    if (this.timeoutId) {
      clearTimeout(this.timeoutId);
      this.timeoutId = null;
    }
    this.current.set(null);
  }

  private show(kind: NotificationKind, message: string): void {
    if (this.timeoutId) {
      clearTimeout(this.timeoutId);
    }

    this.current.set({ kind, message });
    this.timeoutId = setTimeout(() => this.clear(), 5000);
  }
}
