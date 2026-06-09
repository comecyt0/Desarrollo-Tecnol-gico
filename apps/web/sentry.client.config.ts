import * as Sentry from '@sentry/nextjs';

// Sólo activamos Sentry si el DSN está definido. En dev local NO debe estarlo
// (el .env.example deja el DSN vacío a propósito); si se setea en dev, mostramos
// un warning para que el dev sepa que sus errores y stack traces se están
// enviando a un proyecto externo de Sentry.
const dsn = process.env.NEXT_PUBLIC_SENTRY_DSN;
const env = process.env.NODE_ENV;

if (dsn && env !== 'production' && typeof window !== 'undefined') {
   
  console.warn(
    `[Sentry] DSN activo en entorno "${env}". Los errores de este navegador se enviarán a Sentry. ` +
    `Para desactivar en dev, elimina NEXT_PUBLIC_SENTRY_DSN de .env.local.`
  );
}

Sentry.init({
  dsn,
  enabled: !!dsn,
  tracesSampleRate: Number(process.env.NEXT_PUBLIC_SENTRY_TRACES_SAMPLE_RATE) || 0.1,
  environment: env,
  debug: false,
});
