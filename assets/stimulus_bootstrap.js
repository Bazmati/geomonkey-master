import { startStimulusApp } from '@symfony/stimulus-bundle/loader';

// Registers Stimulus controllers from Symfony UX packages and your own controllers
// located in "assets/controllers.json" and "assets/controllers/"
export const app = startStimulusApp();

// Register any Stimulus controllers here
// Example: app.register('hello', HelloController);