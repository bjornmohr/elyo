import { By } from '@angular/platform-browser';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { DashboardComponent } from './dashboard.component';
import { DashboardData, EmployeeService } from '../../services/employee.service';

describe('DashboardComponent badge challenge redesign', () => {
  let employeeService: {
    getDashboard: ReturnType<typeof vi.fn>;
  };

  const dashboardData: DashboardData = {
    recentEntries: [],
    streak: 5,
    points: 180,
    lastCheckin: null,
    todayCheckinCompleted: false,
    wellbeing: null,
    metrics: null,
    sleep: null,
    bodySignals: null,
    healthFlag: null,
    levers: [],
  };

  beforeEach(async () => {
    localStorage.clear();
    employeeService = {
      getDashboard: vi.fn(() => of(dashboardData)),
    };

    await TestBed.configureTestingModule({
      imports: [DashboardComponent],
      providers: [
        provideRouter([]),
        { provide: EmployeeService, useValue: employeeService },
      ],
    }).compileComponents();
  });

  it('renders the weekly badge challenge block without the old prevention-badges layout', () => {
    const fixture = TestBed.createComponent(DashboardComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent;
    expect(text).toContain('Deine Woche');
    expect(text).toContain('Ein Fokus, der jetzt am meisten bringt.');
    expect(text).toContain('CHALLENGE DER WOCHE');
    expect(text).toContain('Auch für dich dran');
    expect(text).not.toContain('Deine Präventions-Badges');
    expect(text).not.toContain('Aktive Fortschritte');
  });

  it('opens the centered badge detail modal from challenge and secondary suggestions', () => {
    const fixture = TestBed.createComponent(DashboardComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    component.openBadge(component.featuredDashboardBadge()!);
    fixture.detectChanges();

    let dialog = fixture.debugElement.query(By.css('[role="dialog"]'));
    expect(dialog).not.toBeNull();
    expect(dialog.nativeElement.closest('.fixed.inset-0')).not.toBeNull();
    expect(dialog.nativeElement.textContent).toContain('Warum das hilft');

    component.closeBadgeDetail();
    fixture.detectChanges();

    component.openBadge(component.secondaryDashboardBadges()[0]);
    fixture.detectChanges();

    dialog = fixture.debugElement.query(By.css('[role="dialog"]'));
    expect(dialog).not.toBeNull();
    expect(dialog.nativeElement.textContent).toContain('So schaltest du es frei');
  });

  it('toggles the prioritisation explanation', () => {
    const fixture = TestBed.createComponent(DashboardComponent);
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).not.toContain('der Bereich, der gerade am meisten Aufmerksamkeit braucht');

    fixture.componentInstance.toggleBadgePrioritisation();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('der Bereich, der gerade am meisten Aufmerksamkeit braucht');
    expect(fixture.nativeElement.textContent).toContain('Streak-Schutz');
  });
});
