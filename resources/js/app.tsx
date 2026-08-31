import '../css/app.css';
import './bootstrap';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';

const appName = import.meta.env.VITE_APP_NAME || 'Attendance Web';

console.log('Inertia app starting...');

const pages = import.meta.glob('./Pages/**/*.tsx');
console.log('Available pages:', Object.keys(pages));

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: async (name) => {
    console.log('Resolving page:', name);
    const path = `./Pages/${name}.tsx`;
    console.log('Looking for:', path);

    if (!(path in pages)) {
      throw new Error(`Page not found: ${path}`);
    }

    const module = await pages[path]() as { default: React.ComponentType };
    return module.default;
  },
  setup({ el, App, props }) {
    console.log('Setting up Inertia app', { el, props });
    const root = createRoot(el);
    root.render(<App {...props} />);
  },
  progress: {
    color: '#4B5563',
  },
}).catch((error) => {
  console.error('Inertia app error:', error);
});
