import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { AssignedMeasure, EmployeeService } from '../../services/employee.service';
import { EmployeeMeasuresComponent } from './measures.component';

describe('EmployeeMeasuresComponent', () => {
  let employeeService: {
    getAssignedMeasures: ReturnType<typeof vi.fn>;
  };

  const assignedMeasure: AssignedMeasure = {
    id: 12,
    title: 'Nacken-Mobilität',
    category: 'MOBILITY',
    assignmentReason: 'aus Check-in „Nackenschmerzen“',
    exerciseCount: 4,
    estMinutes: 10,
    streakDays: 5,
    weeklyDone: 3,
    weeklyTarget: 4,
    effect: { metric: 'pain', unit: 'nrs_0_10', before: 6, after: 3, direction: 'down' },
    locationTags: ['office', 'plant'],
    postureTags: ['standing'],
    requiresFloor: false,
  };

  const floorMeasure: AssignedMeasure = {
    ...assignedMeasure,
    id: 13,
    title: 'Boden-Programm',
    requiresFloor: true,
  };

  beforeEach(async () => {
    localStorage.clear();
    employeeService = {
      getAssignedMeasures: vi.fn(() => of([assignedMeasure, floorMeasure])),
    };

    await TestBed.configureTestingModule({
      imports: [EmployeeMeasuresComponent],
      providers: [
        provideRouter([]),
        { provide: EmployeeService, useValue: employeeService },
      ],
    }).compileComponents();
  });

  it('renders assigned measures with category, reason, progress and effect badge', () => {
    const fixture = TestBed.createComponent(EmployeeMeasuresComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent;
    expect(text).toContain('Nacken-Mobilität');
    expect(text).toContain('Mobilität');
    expect(text).toContain('aus Check-in „Nackenschmerzen“');
    expect(text).toContain('3/4');
    expect(text).toContain('Schmerz 6 → 3 ↓');
    expect(text).toContain('🔥 5 Tage');
  });

  it('hides floor-based measures at the office and shows the filter hint', () => {
    const fixture = TestBed.createComponent(EmployeeMeasuresComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent;
    expect(text).toContain('Heute: Büro');
    expect(text).not.toContain('Boden-Programm');
    expect(text).toContain('1 Maßnahme mit Bodenübungen ausgeblendet');
  });

  it('shows floor-based measures when the local demo check-in says home', () => {
    const key = `elyo.demo.checkin.${new Date().toISOString().slice(0, 10)}`;
    localStorage.setItem(key, JSON.stringify({ location: 'home' }));

    const fixture = TestBed.createComponent(EmployeeMeasuresComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent;
    expect(text).toContain('Heute: Home-Office');
    expect(text).toContain('Boden-Programm');
  });

  it('shows an empty state when no measures are assigned', () => {
    employeeService.getAssignedMeasures.mockReturnValue(of([]));

    const fixture = TestBed.createComponent(EmployeeMeasuresComponent);
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Dir sind aktuell keine Maßnahmen zugewiesen.');
  });
});
