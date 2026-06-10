import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, Router, convertToParamMap, provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { EmployeeService } from '../../services/employee.service';
import { NotificationService } from '../../../../shared/notifications/notification.service';
import { EmployeeMeasureCheckinComponent } from './measure-checkin.component';

describe('EmployeeMeasureCheckinComponent', () => {
  async function setup(token: string | null, redeemResult = of({ data: { id: 7 } })) {
    TestBed.resetTestingModule();

    const employeeService = {
      redeemMeasureCheckin: vi.fn(() => redeemResult),
    };
    const notifications = {
      success: vi.fn(),
      error: vi.fn(),
    };

    await TestBed.configureTestingModule({
      imports: [EmployeeMeasureCheckinComponent],
      providers: [
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              paramMap: convertToParamMap(token ? { token } : {}),
            },
          },
        },
        { provide: EmployeeService, useValue: employeeService },
        { provide: NotificationService, useValue: notifications },
      ],
    }).compileComponents();

    const router = TestBed.inject(Router);
    vi.spyOn(router, 'navigate').mockResolvedValue(true);

    const fixture = TestBed.createComponent(EmployeeMeasureCheckinComponent);
    fixture.detectChanges();

    return { fixture, employeeService, notifications, router };
  }

  it('reads the token from the route params and redeems it', async () => {
    const { employeeService } = await setup('checkin-token-123');

    expect(employeeService.redeemMeasureCheckin).toHaveBeenCalledWith('checkin-token-123');
  });

  it('shows the success state after redemption', async () => {
    const { fixture, notifications } = await setup('checkin-token-123');

    const text = fixture.nativeElement.textContent;
    expect(text).toContain('Teilnahme wurde gespeichert.');
    expect(text).toContain('Zu den Maßnahmen');
    expect(notifications.success).toHaveBeenCalledWith('Teilnahme wurde gespeichert.');
  });

  it('does not call the service when the token is missing', async () => {
    const { fixture, employeeService } = await setup(null);

    expect(employeeService.redeemMeasureCheckin).not.toHaveBeenCalled();
    expect(fixture.nativeElement.textContent).toContain('Check-in-Link ist ungültig.');
  });

  it('redirects duplicate participation conflicts back to measures', async () => {
    const { notifications, router } = await setup('checkin-token-123', throwError(() => ({
      error: {
        error: {
          code: 'MEASURE_ALREADY_PARTICIPATED',
        },
      },
    })));

    expect(notifications.success).toHaveBeenCalledWith('Teilnahme ist bereits gespeichert.');
    expect(router.navigate).toHaveBeenCalledWith(['/employee/measures']);
  });

  it.each([
    [404, undefined, 'Check-in-Link ist ungültig oder für dich nicht verfügbar.'],
    [422, 'MEASURE_NOT_ACTIVE', 'Diese Maßnahme ist aktuell nicht aktiv.'],
    [422, 'CHECKIN_TOKEN_REVOKED', 'Dieser Check-in-Link wurde ersetzt.'],
    [422, 'CHECKIN_TOKEN_NOT_YET_VALID', 'Dieser Check-in-Link ist noch nicht gültig.'],
    [422, 'CHECKIN_TOKEN_EXPIRED', 'Dieser Check-in-Link ist abgelaufen.'],
  ])('maps check-in errors to user-facing messages', async (status, code, message) => {
    const { fixture, notifications } = await setup('checkin-token-123', throwError(() => ({
      status,
      error: {
        error: {
          code,
        },
      },
    })));

    expect(fixture.nativeElement.textContent).toContain(message);
    expect(notifications.error).toHaveBeenCalledWith(message);
  });

  it('uses a generic fallback without exposing token or technical error details', async () => {
    const { fixture, notifications } = await setup('raw-secret-token', throwError(() => ({
      status: 500,
      error: {
        error: {
          code: 'SQLSTATE_INTERNAL_FAILURE',
          detail: 'stack trace details',
        },
      },
    })));

    const text = fixture.nativeElement.textContent;
    expect(text).toContain('Check-in konnte nicht gespeichert werden.');
    expect(text).not.toContain('raw-secret-token');
    expect(text).not.toContain('SQLSTATE_INTERNAL_FAILURE');
    expect(text).not.toContain('stack trace details');
    expect(notifications.error).toHaveBeenCalledWith('Check-in konnte nicht gespeichert werden.');
  });
});
