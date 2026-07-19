import { By } from '@angular/platform-browser';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { EmployeeBadgesComponent } from './badges.component';

describe('EmployeeBadgesComponent redesign', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [EmployeeBadgesComponent],
      providers: [provideRouter([])],
    }).compileComponents();
  });

  it('renders the streak strip, next-goal spotlight and category collections', () => {
    const fixture = TestBed.createComponent(EmployeeBadgesComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent;
    expect(text).toContain('DEINE SERIE');
    expect(text).toContain('5 Tage in Folge');
    expect(text).toContain('DEIN NÄCHSTES ZIEL');
    expect(text).toContain('automatisch gewählt · am nächsten dran');
    expect(text).toContain('Deine Sammlungen');
    expect(text).toContain('Routine');
    expect(text).not.toContain('In Arbeit');
    expect(text).not.toContain('Noch offen');
  });

  it('opens a centered detail dialog from earned, in-progress, locked and spotlight badges', () => {
    const fixture = TestBed.createComponent(EmployeeBadgesComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    const badges = component.badges;

    for (const badgeId of ['baseline-set', 'seven-day-compass', 'smart-pause']) {
      component.openBadge(badges.find(badge => badge.id === badgeId)!);
      fixture.detectChanges();

      const dialog = fixture.debugElement.query(By.css('[role="dialog"]'));
      expect(dialog, `dialog opens for ${badgeId}`).not.toBeNull();
      expect(dialog.nativeElement.textContent).toContain('So schaltest du es frei');
      expect(dialog.nativeElement.textContent).toContain('Warum das hilft');
      expect(dialog.nativeElement.closest('.fixed.inset-0')).not.toBeNull();

      component.closeBadgeDetail();
      fixture.detectChanges();
    }

    component.openBadge(component.nextGoalBadge()!);
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('7-Tage-Kompass');
  });

  it('toggles category accordions independently', () => {
    const fixture = TestBed.createComponent(EmployeeBadgesComponent);
    fixture.detectChanges();

    expect(fixture.componentInstance.isCategoryOpen('STREAK')).toBe(true);

    fixture.componentInstance.toggleCategory('STREAK');
    fixture.detectChanges();

    expect(fixture.componentInstance.isCategoryOpen('STREAK')).toBe(false);
    expect(fixture.nativeElement.textContent).not.toContain('Schlaf-Serie');
  });
});
