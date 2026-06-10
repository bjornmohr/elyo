import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { EmployeeMeasure, EmployeeService } from '../../services/employee.service';
import { NotificationService } from '../../../../shared/notifications/notification.service';
import { EmployeeMeasuresComponent } from './measures.component';

describe('EmployeeMeasuresComponent', () => {
  let employeeService: {
    getMeasures: ReturnType<typeof vi.fn>;
    participateInMeasure: ReturnType<typeof vi.fn>;
  };
  let notifications: {
    success: ReturnType<typeof vi.fn>;
    error: ReturnType<typeof vi.fn>;
  };

  const availableMeasure: EmployeeMeasure = {
    id: 7,
    title: 'Workshop',
    description: 'Stressbewusster Arbeitsalltag',
    category: 'mental',
    team: null,
    participation: {
      isParticipating: false,
      participatedAt: null,
    },
  };

  beforeEach(async () => {
    employeeService = {
      getMeasures: vi.fn(() => of([availableMeasure])),
      participateInMeasure: vi.fn(),
    };
    notifications = {
      success: vi.fn(),
      error: vi.fn(),
    };

    await TestBed.configureTestingModule({
      imports: [EmployeeMeasuresComponent],
      providers: [
        provideRouter([]),
        { provide: EmployeeService, useValue: employeeService },
        { provide: NotificationService, useValue: notifications },
      ],
    }).compileComponents();
  });

  it('shows a participation action for a measure that has not been joined', () => {
    const fixture = TestBed.createComponent(EmployeeMeasuresComponent);
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Teilnehmen');
  });

  it('marks a measure as participated after successful participation', () => {
    employeeService.participateInMeasure.mockReturnValue(of({
      data: {
        ...availableMeasure,
        participation: {
          isParticipating: true,
          participatedAt: '2026-06-01T10:00:00Z',
        },
      },
    }));
    const fixture = TestBed.createComponent(EmployeeMeasuresComponent);
    fixture.detectChanges();

    fixture.componentInstance.participate(availableMeasure);
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Teilgenommen');
    expect(notifications.success).toHaveBeenCalledWith('Teilnahme wurde gespeichert.');
  });

  it('treats duplicate participation as already participated', () => {
    employeeService.participateInMeasure.mockReturnValue(throwError(() => ({
      error: {
        error: {
          code: 'MEASURE_ALREADY_PARTICIPATED',
        },
      },
    })));
    const fixture = TestBed.createComponent(EmployeeMeasuresComponent);
    fixture.detectChanges();

    fixture.componentInstance.participate(availableMeasure);
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Teilgenommen');
    expect(notifications.success).toHaveBeenCalledWith('Teilnahme ist bereits gespeichert.');
    expect(notifications.error).not.toHaveBeenCalledWith('Teilnahme konnte nicht gespeichert werden.');
  });

  it('does not pass identity or timestamp fields when participating', () => {
    employeeService.participateInMeasure.mockReturnValue(of({ data: availableMeasure }));
    const fixture = TestBed.createComponent(EmployeeMeasuresComponent);
    fixture.detectChanges();

    fixture.componentInstance.participate(availableMeasure);

    expect(employeeService.participateInMeasure).toHaveBeenCalledWith(availableMeasure.id);
    expect(employeeService.participateInMeasure.mock.calls[0].length).toBe(1);
  });
});
