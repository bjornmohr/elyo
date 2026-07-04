import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { CheckinStepperComponent } from './checkin-stepper.component';

describe('CheckinStepperComponent', () => {
  let httpClient: { get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    localStorage.clear();
    httpClient = { get: vi.fn(), post: vi.fn() };

    await TestBed.configureTestingModule({
      imports: [CheckinStepperComponent],
      providers: [
        provideRouter([]),
        { provide: HttpClient, useValue: httpClient },
      ],
    }).compileComponents();
  });

  function createComponent() {
    const fixture = TestBed.createComponent(CheckinStepperComponent);
    fixture.detectChanges();
    return fixture;
  }

  it('starts with the location step and blocks Weiter until a choice is made', () => {
    const fixture = createComponent();
    const component = fixture.componentInstance;

    expect(fixture.nativeElement.textContent).toContain('Wo bist du heute?');
    expect(component.stepValid()).toBe(false);

    component.draft.location = 'office';
    expect(component.stepValid()).toBe(true);
  });

  it('pulls in the sleep questions when energy is low', () => {
    const fixture = createComponent();
    const component = fixture.componentInstance;

    component.draft.energy = 2;
    expect(component.sleepActive()).toBe(true);
    // With the sleep branch open, recovery becomes required.
    component.step.set('energy' as any);
    expect(component.stepValid()).toBe(false);

    component.draft.sleepRecovery = 3;
    expect(component.stepValid()).toBe(true);
  });

  it('keeps the sleep questions hidden for high energy unless requested manually', () => {
    const fixture = createComponent();
    const component = fixture.componentInstance;

    component.draft.energy = 4;
    expect(component.sleepActive()).toBe(false);

    component.draft.sleepWanted = true;
    expect(component.sleepActive()).toBe(true);
  });

  it('gates the sick branch: illness subs required once a type is picked', () => {
    const fixture = createComponent();
    const component = fixture.componentInstance;
    component.step.set('signals' as any);

    component.setSick(true);
    expect(component.stepValid()).toBe(true);

    component.setIllnessType('cold');
    expect(component.stepValid()).toBe(false);

    component.toggleIllnessSub('Halsschmerzen');
    expect(component.stepValid()).toBe(true);
  });

  it('clears illness details when sick is answered with no', () => {
    const fixture = createComponent();
    const component = fixture.componentInstance;

    component.setSick(true);
    component.setIllnessType('cold');
    component.toggleIllnessSub('Husten');

    component.setSick(false);
    expect(component.draft.illnessType).toBeNull();
    expect(component.draft.illnessSubs).toEqual([]);
  });

  it('saves to localStorage only — no HTTP call — and shows the faked points screen', () => {
    const fixture = createComponent();
    const component = fixture.componentInstance;

    component.draft.location = 'office';
    component.draft.mood = 4;
    component.draft.energy = 3;
    component.draft.stress = 2;
    component.setSick(false);

    component.save();
    fixture.detectChanges();

    const key = `elyo.demo.checkin.${new Date().toISOString().slice(0, 10)}`;
    const stored = JSON.parse(localStorage.getItem(key)!);
    expect(stored.mood).toBe(4);
    expect(stored.illness.sick).toBe(false);

    expect(httpClient.get).not.toHaveBeenCalled();
    expect(httpClient.post).not.toHaveBeenCalled();
    expect(fixture.nativeElement.textContent).toContain('+10 Punkte');
  });
});
