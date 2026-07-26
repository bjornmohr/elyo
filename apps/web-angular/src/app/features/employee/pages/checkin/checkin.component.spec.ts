import { TestBed } from '@angular/core/testing';
import { ComponentFixture } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { NotificationService } from '../../../../shared/notifications/notification.service';
import { EmployeeService } from '../../services/employee.service';
import { CheckinComponent } from './checkin.component';

describe('CheckinComponent', () => {
  let employeeService: {
    getCheckinStatus: ReturnType<typeof vi.fn>;
    submitCheckin: ReturnType<typeof vi.fn>;
  };
  let notifications: {
    success: ReturnType<typeof vi.fn>;
    error: ReturnType<typeof vi.fn>;
  };

  beforeEach(async () => {
    employeeService = {
      getCheckinStatus: vi.fn(() => of({ completed: false, entry: null })),
      submitCheckin: vi.fn(() => of({ success: true })),
    };
    notifications = { success: vi.fn(), error: vi.fn() };

    await TestBed.configureTestingModule({
      imports: [CheckinComponent],
      providers: [
        provideRouter([]),
        { provide: EmployeeService, useValue: employeeService },
        { provide: NotificationService, useValue: notifications },
      ],
    }).compileComponents();
  });

  /** Walks the three steps and triggers the submit on the last one. */
  function completeCheckin(fixture: ComponentFixture<CheckinComponent>, values: string[]) {
    for (const value of values) {
      const input = fixture.nativeElement.querySelector('input[type="range"]') as HTMLInputElement;
      input.value = value;
      input.dispatchEvent(new Event('input'));
      fixture.detectChanges();

      fixture.componentInstance.next();
      fixture.detectChanges();
    }
  }

  it('presents exactly three integer inputs bounded to the 1-5 scale', () => {
    const fixture = TestBed.createComponent(CheckinComponent);
    fixture.detectChanges();

    for (let step = 0; step < 3; step += 1) {
      const input = fixture.nativeElement.querySelector('input[type="range"]') as HTMLInputElement;
      expect(input.min).toBe('1');
      expect(input.max).toBe('5');
      expect(input.step).toBe('1');
      expect(fixture.nativeElement.querySelector('textarea')).toBeNull();

      if (step < 2) {
        fixture.componentInstance.next();
        fixture.detectChanges();
      }
    }

    expect(fixture.nativeElement.textContent).toContain('Abschließen');
  });

  it('submits the three selected values after the energy step', () => {
    vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
    const fixture = TestBed.createComponent(CheckinComponent);
    fixture.detectChanges();

    completeCheckin(fixture, ['5', '1', '4']);

    expect(employeeService.submitCheckin).toHaveBeenCalledWith({
      mood: 5,
      stress: 1,
      energy: 4,
    });
  });

  it('shows the completed state instead of the form when the daily check-in already exists', () => {
    employeeService.getCheckinStatus.mockReturnValue(of({ completed: true, entry: null }));

    const fixture = TestBed.createComponent(CheckinComponent);
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Check-in bereits abgeschlossen');
    expect(fixture.nativeElement.querySelector('input[type="range"]')).toBeNull();
  });

  it('switches to the completed state when the submit is rejected as already done', () => {
    employeeService.submitCheckin.mockReturnValue(throwError(() => ({ status: 409 })));

    const fixture = TestBed.createComponent(CheckinComponent);
    fixture.detectChanges();

    completeCheckin(fixture, ['5', '1', '4']);

    expect(notifications.error).toHaveBeenCalledWith('Für heute liegt bereits ein Check-in vor.');
    expect(fixture.nativeElement.textContent).toContain('Check-in bereits abgeschlossen');
    expect(fixture.nativeElement.querySelector('input[type="range"]')).toBeNull();
  });

  it('shows a generic error that leaks no health values when the submit fails', () => {
    employeeService.submitCheckin.mockReturnValue(throwError(() => ({ status: 500 })));

    const fixture = TestBed.createComponent(CheckinComponent);
    fixture.detectChanges();

    completeCheckin(fixture, ['5', '1', '4']);

    const message = 'Check-in konnte nicht gespeichert werden.';
    expect(fixture.nativeElement.textContent).toContain(message);
    expect(notifications.error).toHaveBeenCalledWith(message);
    expect(notifications.success).not.toHaveBeenCalled();
    // The form stays available for a retry, and no submitted value is echoed back.
    expect(fixture.nativeElement.querySelector('input[type="range"]')).not.toBeNull();
  });
});
