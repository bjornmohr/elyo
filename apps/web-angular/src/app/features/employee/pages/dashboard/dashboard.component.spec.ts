import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { DashboardData, EmployeeService } from '../../services/employee.service';
import { DashboardComponent } from './dashboard.component';

describe('DashboardComponent', () => {
  it('displays recent wellbeing scores and mood on the 1-5 scale', async () => {
    const data: DashboardData = {
      recentEntries: [{
        id: '01JY0000000000000000000001',
        score: 4.3,
        mood: 5,
        stress: 1,
        energy: 5,
        createdAt: '2026-07-25T10:00:00Z',
      }],
      streak: 2,
      points: 10,
      lastCheckin: '2026-07-25T10:00:00Z',
      todayCheckinCompleted: true,
    };

    await TestBed.configureTestingModule({
      imports: [DashboardComponent],
      providers: [
        provideRouter([]),
        {
          provide: EmployeeService,
          useValue: { getDashboard: vi.fn(() => of(data)) },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(DashboardComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent;
    expect(text).toContain('Score 4.3/5');
    // Same face as the history view for the same value (shared wellbeing-scale).
    expect(text).toContain('🤩');
  });
});
