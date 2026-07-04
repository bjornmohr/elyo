import { TestBed } from '@angular/core/testing';
import { CheckinDemoStorageService, DemoCheckin } from './checkin-demo-storage.service';

describe('CheckinDemoStorageService', () => {
  let service: CheckinDemoStorageService;

  const checkin: DemoCheckin = {
    date: '2026-07-04',
    location: 'office',
    mood: 4,
    energy: 3,
    stress: 2,
    sleep: { hours: 6.5, recovery: 3 },
    symptoms: [{ key: 'neck', region: 'Nacken', severity: 3 }],
    illness: { sick: true, cold: { subs: ['Halsschmerzen', 'Schnupfen'], severity: 2 } },
  };

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({});
    service = TestBed.inject(CheckinDemoStorageService);
  });

  it('saves under the elyo.demo.checkin.<date> key with the handoff JSON shape', () => {
    service.save(checkin);

    const raw = localStorage.getItem('elyo.demo.checkin.2026-07-04');
    expect(raw).not.toBeNull();

    const parsed = JSON.parse(raw!);
    expect(parsed.location).toBe('office');
    expect(parsed.sleep).toEqual({ hours: 6.5, recovery: 3 });
    expect(parsed.symptoms[0]).toEqual({ key: 'neck', region: 'Nacken', severity: 3 });
    expect(parsed.illness.cold.subs).toContain('Halsschmerzen');
  });

  it('survives reload semantics — load returns the persisted check-in', () => {
    service.save(checkin);

    const restored = service.load('2026-07-04');
    expect(restored).toEqual(checkin);
  });

  it('does not touch foreign localStorage keys', () => {
    localStorage.setItem('someone.elses.key', 'untouched');

    service.save(checkin);

    expect(localStorage.getItem('someone.elses.key')).toBe('untouched');
    expect(localStorage.length).toBe(2);
  });

  it('reports todayCompleted only when a check-in for today exists', () => {
    expect(service.todayCompleted()).toBe(false);

    service.save({ ...checkin, date: service.todayKey() });

    expect(service.todayCompleted()).toBe(true);
  });

  it('returns null for corrupt entries instead of throwing', () => {
    localStorage.setItem('elyo.demo.checkin.2026-07-04', '{not json');

    expect(service.load('2026-07-04')).toBeNull();
  });
});
