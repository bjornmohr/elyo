import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { EmployeeService, WellbeingEntry } from '../../services/employee.service';
import { HistoryComponent } from './history.component';

describe('HistoryComponent', () => {
  it('displays all wellbeing values on the 1-5 scale', async () => {
    const entry = {
      id: '01JY0000000000000000000001',
      score: 4.3,
      mood: 5,
      stress: 1,
      energy: 5,
      createdAt: '2026-07-25T10:00:00Z',
    } as WellbeingEntry;

    await TestBed.configureTestingModule({
      imports: [HistoryComponent],
      providers: [
        provideRouter([]),
        {
          provide: EmployeeService,
          useValue: { getHistory: vi.fn(() => of([entry])) },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(HistoryComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent;
    const compactText = text.replace(/\s/g, '');
    expect(text).toContain('Mood 5/5');
    expect(compactText).toContain('Stress1/5');
    expect(compactText).toContain('Energy5/5');
    expect(text).toContain('4.3/5');
    expect(text).toContain('🤩');
  });
});
