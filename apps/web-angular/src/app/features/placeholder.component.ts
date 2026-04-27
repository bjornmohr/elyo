import { Component } from '@angular/core';

@Component({
  selector: 'app-placeholder',
  standalone: true,
  template: `
    <div class="p-8">
      <h1 class="text-2xl font-bold">{{ title }}</h1>
      <p class="mt-4 text-elyo-ink-soft">This is a placeholder for the {{ title }} area.</p>
    </div>
  `
})
export class PlaceholderComponent {
  title = 'Page';
}
