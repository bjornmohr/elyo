import { EmployeeBadge } from '../models/badge.model';
import { EmployeeBadgesDemoService } from './employee-badges-demo.service';

describe('EmployeeBadgesDemoService badge prioritisation', () => {
  let service: EmployeeBadgesDemoService;

  beforeEach(() => {
    service = new EmployeeBadgesDemoService();
  });

  it('selects the in-progress badge with the highest progress as next goal', () => {
    const nextGoal = service.nextGoalBadge();

    expect(nextGoal?.id).toBe('seven-day-compass');
    expect(nextGoal?.progressPercent).toBe(71);
  });

  it('adds natural benefit copy used by the badge detail modal', () => {
    const compass = service.getBadges().find(badge => badge.id === 'seven-day-compass');

    expect(compass?.benefit).toBe('Regelmäßigkeit macht Veränderungen und Muster sichtbar.');
  });

  it('provides one featured dashboard badge and two secondary suggestions', () => {
    const featured = service.dashboardFeaturedBadge(5);
    const secondary = service.dashboardSecondaryBadges(5);

    expect(featured?.id).toBe('seven-day-compass');
    expect(featured?.dashboardReason).toBe('Deine 5-Tage-Serie läuft — heute einchecken sichert sie.');
    expect(secondary).toHaveLength(2);
    expect(secondary.every((badge: EmployeeBadge) => badge.id !== featured?.id)).toBe(true);
  });
});
