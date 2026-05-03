import { BootstrapContext, bootstrapApplication } from '@angular/platform-browser';
import { AppComponent } from './app/app';
import { config } from './app/app.config.server';
// Source - https://stackoverflow.com/a/77758456
// Posted by Abhinav Akhil
// Retrieved 2026-05-03, License - CC BY-SA 4.0

import 'zone.js';

const bootstrap = (context: BootstrapContext) =>
    bootstrapApplication(AppComponent, config, context);

export default bootstrap;
