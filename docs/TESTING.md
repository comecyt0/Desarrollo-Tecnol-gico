# 🧪 Testing — COMECYT

> **Última actualización:** 2026-06-12

---

## 1. Resumen del estado de tests

| Capa | Framework | # tests | Coverage objetivo |
|---|---|---|---|
| Backend Laravel | Pest / PHPUnit | TBD | 70% |
| Frontend unit/integration | Vitest + Testing Library | **96 ✓** | 60% |
| Frontend a11y | axe-core via Vitest | incluidos en los 96 | 100% en componentes UI |
| Frontend E2E | Playwright | configurado | flujos críticos |

---

## 2. Correr los tests

### Backend (Pest / PHPUnit)

```bash
cd apps/api

# Todos
php artisan test

# Filtrar por nombre
php artisan test --filter LoginTest

# Con coverage
XDEBUG_MODE=coverage php artisan test --coverage --min=70

# Modo paralelo (más rápido)
php artisan test --parallel
```

### Frontend (Vitest)

```bash
cd apps/web

# Una pasada
npx vitest run

# Watch mode (re-ejecuta al cambiar archivos)
npx vitest

# UI interactivo
npx vitest --ui

# Coverage
npx vitest run --coverage

# Solo un archivo
npx vitest run src/lib/roles.test.ts
```

### E2E (Playwright)

```bash
cd apps/web

# Headless
npx playwright test

# UI mode (interactivo)
npx playwright test --ui

# Solo un browser
npx playwright test --project=chromium

# Debug
npx playwright test --debug

# Generar reporte HTML
npx playwright show-report
```

---

## 3. Estructura de tests

### Backend

```
apps/api/tests/
├── Pest.php                  # config Pest
├── TestCase.php              # base class
├── Feature/                  # tests de integración (HTTP, DB)
│   ├── Auth/
│   │   ├── LoginTest.php
│   │   ├── TwoFactorTest.php
│   │   └── PasswordResetTest.php
│   ├── Admin/
│   ├── Revisor/
│   ├── Evaluador/
│   └── Solicitante/
└── Unit/                     # tests de helpers, services
    ├── ConfigHelperTest.php
    └── ...
```

### Frontend

```
apps/web/src/
├── components/**/*.test.tsx       # tests de componentes (unit)
├── components/**/*.a11y.test.tsx  # tests de accesibilidad (axe-core)
├── lib/**/*.test.ts               # tests de helpers
├── hooks/**/*.test.ts             # tests de hooks
└── test/
    ├── setup.ts                   # Vitest setup
    └── axe-helper.ts              # wrapper de axe
```

### E2E

```
apps/web/tests-e2e/
├── login.spec.ts
├── crear-solicitud.spec.ts
├── flujo-revisor.spec.ts
└── ...
```

---

## 4. Escribir un test (ejemplos)

### Backend — Feature test (Pest)

```php
<?php

use App\Models\User;
use App\Models\Solicitud;

it('un solicitante puede crear borrador de solicitud', function () {
    $user = User::factory()->create(['rol_id' => 4]);
    $payload = [
        'convocatoria_id' => 1,
        'titulo_proyecto' => 'Proyecto de prueba',
        // ...
    ];

    $response = $this->actingAs($user)
        ->postJson('/api/solicitudes', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure(['id', 'folio', 'estado']);

    expect(Solicitud::count())->toBe(1);
});

it('un solicitante NO puede asignar evaluador a otro proyecto', function () {
    $user = User::factory()->create(['rol_id' => 4]);

    $response = $this->actingAs($user)
        ->postJson('/api/admin/asignaciones-evaluador', [
            'solicitud_id' => 1,
            'evaluador_id' => 99,
        ]);

    $response->assertStatus(403);
});
```

### Backend — Unit test

```php
<?php

use App\Helpers\ConfigHelper;

it('ConfigHelper::val devuelve el valor de config', function () {
    expect(ConfigHelper::val('roles.admin'))->toBe(1);
    expect(ConfigHelper::val('roles.solicitante'))->toBe(4);
});

it('ConfigHelper::msg devuelve mensaje localizado', function () {
    $msg = ConfigHelper::msg(\App\Enums\Message::AUTH_INVALID_CREDENTIALS);
    expect($msg)->toContain('credenciales');
});
```

### Frontend — Componente

```tsx
// src/components/ui/Badge.test.tsx
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Badge } from './badge';

describe('Badge', () => {
  it('renderiza children', () => {
    render(<Badge>BLOQUEADO</Badge>);
    expect(screen.getByText('BLOQUEADO')).toBeInTheDocument();
  });

  it('aplica variant destructive', () => {
    const { container } = render(<Badge variant="destructive">X</Badge>);
    expect(container.firstChild).toHaveClass('bg-destructive');
  });
});
```

### Frontend — Accesibilidad

```tsx
// src/components/ui/Badge.a11y.test.tsx
import { describe, it, expect } from 'vitest';
import { render } from '@testing-library/react';
import { checkA11y } from '@/test/axe-helper';
import { Badge } from './badge';

describe('Badge a11y', () => {
  it('cumple WCAG AA', async () => {
    const { container } = render(<Badge>Texto</Badge>);
    await checkA11y(container);
  });
});
```

### E2E — Playwright

```ts
// tests-e2e/login.spec.ts
import { test, expect } from '@playwright/test';

test('login admin lleva al dashboard', async ({ page }) => {
  await page.goto('http://localhost:3000/login');
  await page.fill('input[name="email"]', 'admin@comecyt.gob.mx');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');

  await expect(page).toHaveURL(/\/admin\/dashboard/);
  await expect(page.getByText('Bienvenido')).toBeVisible();
});

test('login con credenciales incorrectas muestra error', async ({ page }) => {
  await page.goto('http://localhost:3000/login');
  await page.fill('input[name="email"]', 'admin@comecyt.gob.mx');
  await page.fill('input[name="password"]', 'mal_password');
  await page.click('button[type="submit"]');

  await expect(page.getByRole('alert')).toContainText(/credenciales/i);
});
```

---

## 5. Tests críticos a mantener

### Auth
- ✅ Login exitoso
- ✅ Login con password mal
- ✅ Rate limit tras 5 intentos
- ✅ Account lockout tras 10 intentos
- ✅ 2FA setup + login + recovery code
- ✅ Logout invalida JWT
- ✅ Cookie HttpOnly emitida correctamente
- ✅ JWT refresh

### Autorización
- ✅ Solicitante no puede acceder a `/admin/*`
- ✅ Revisor no puede aprobar como evaluador
- ✅ Evaluador no ve solicitudes ajenas
- ✅ IDOR: usuario no puede leer solicitud de otro user_id
- ✅ Mass-assignment: no puede asignarse `is_admin=true`

### Validación
- ✅ Solicitud sin convocatoria_id → 422
- ✅ Email malformado en solicitar-acceso → 422
- ✅ Upload de archivo > 5MB → 422
- ✅ Upload de tipo MIME falso (e.g. .exe renombrado a .pdf) → 422

### Flujo de negocio
- ✅ Solicitud en `borrador` se puede editar
- ✅ Solicitud en `enviada` NO se puede editar
- ✅ Transiciones de estado válidas
- ✅ Empresa en lista negra no ve convocatorias activas
- ✅ Evaluador no puede iniciar evaluación sin aceptar carta de imparcialidad

---

## 6. Mocks y fixtures

### Backend

`database/factories/UserFactory.php`:
```php
public function definition(): array {
    return [
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'rol_id' => 4,
    ];
}

public function admin() {
    return $this->state(['rol_id' => 1]);
}
```

Uso:
```php
$admin = User::factory()->admin()->create();
```

### Frontend

```tsx
// src/test/fixtures/solicitud.ts
export const mockSolicitud = {
  id: 1,
  folio: 'PRY-2026-001',
  estado: 'borrador',
  titulo_proyecto: 'Test',
  // ...
};
```

```tsx
// En test:
import { mockSolicitud } from '@/test/fixtures/solicitud';

render(<SolicitudCard solicitud={mockSolicitud} />);
```

---

## 7. CI/CD — Tests en pipeline

`.github/workflows/security-sast.yml` corre en cada push:
- ESLint con plugin security
- Semgrep OWASP Top 10
- Gitleaks (secret scanning)
- npm audit (high+)
- composer audit

**Recomendado agregar:** un workflow `tests.yml` que corra Vitest + Pest en cada PR. (Pendiente — backlog).

---

## 8. Mutation testing (opcional)

Si quieres aumentar la calidad de los tests:

```bash
# Backend
composer require --dev infection/infection
./vendor/bin/infection --threads=4

# Frontend (Stryker)
npm install --save-dev @stryker-mutator/core @stryker-mutator/vitest-runner
npx stryker run
```

---

## 9. Performance testing (opcional)

### Backend

```bash
# k6 (script en JS contra el API)
k6 run tests-perf/login-load.js
```

```js
// tests-perf/login-load.js
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '30s', target: 50 },
    { duration: '1m', target: 50 },
    { duration: '10s', target: 0 },
  ],
};

export default function () {
  const res = http.post('http://localhost:8000/api/auth/login', {
    email: 'admin@comecyt.gob.mx',
    password: 'password123',
  });
  check(res, { 'status is 200': (r) => r.status === 200 });
  sleep(1);
}
```

### Frontend

Lighthouse CLI:
```bash
npm install -g @lhci/cli
lhci autorun --collect.url=http://localhost:3000
```

---

## 10. Antes de mergear un PR

Checklist mental:

- [ ] `php artisan test` pasa (si tocaste backend)
- [ ] `npx vitest run` pasa (si tocaste frontend)
- [ ] `./vendor/bin/pint --test` pasa
- [ ] `npm run lint` pasa (0 errors)
- [ ] Si tocaste API nueva, ¿documentaste en `docs/API.md`?
- [ ] Si cambiaste schema, ¿hay migración + actualizaste `docs/DATABASE.md`?
- [ ] ¿Hay tests nuevos para el código nuevo?
- [ ] ¿Pasa la skill de auditoría de seguridad si es código sensible?

---

> **Para escribir tests nuevos:** ver ejemplos existentes en `tests/Feature/` y `src/**/*.test.tsx`.
