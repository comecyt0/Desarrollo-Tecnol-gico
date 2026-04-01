# COMECYT Design System - Guía Completa

**Versión:** 1.0
**Última actualización:** 31 Marzo 2026
**Estado:** ✅ Implementado

---

## 📋 Tabla de Contenidos

1. [Principios de Diseño](#principios-de-diseño)
2. [Paleta de Colores](#paleta-de-colores)
3. [Tipografía](#tipografía)
4. [Espaciado y Layout](#espaciado-y-layout)
5. [Componentes](#componentes)
6. [Animaciones y Transiciones](#animaciones-y-transiciones)
7. [Patrones de UX](#patrones-de-ux)
8. [Accesibilidad](#accesibilidad)
9. [Implementación](#implementación)

---

## 🎯 Principios de Diseño

### 1. **Simplicidad Profesional**
- Interfaz limpia y despejada
- Blanco negativo generoso
- Evitar sobrecarga visual

### 2. **Confianza y Autoridad**
- Usar paleta corporativa (Vino + Dorado)
- Tipografía clara y legible
- Jerarquía visual evidente

### 3. **Accesibilidad Prioritaria**
- Contraste WCAG AA mínimo
- Tamaños de texto legibles (14px mínimo)
- Navegación clara y consistente

### 4. **Velocidad Percibida**
- Animaciones suaves y rápidas (300ms)
- Loading states claros
- Feedback inmediato para acciones

### 5. **Coherencia**
- Componentes reutilizables
- Espaciado consistente
- Patrones repetidos

---

## 🎨 Paleta de Colores

### Colores Primarios

```
Primary (Vino):
- #6B1F3A - Core brand color
- HSL: 339 55% 27%
- Uso: Botones principales, headers, acentos primarios
- Fallback: #8A2049 en hover

Secondary (Dorado):
- #C9A96E - Accent color
- HSL: 39 45% 61%
- Uso: Badges, highlights, acentos secundarios
- Fallback: #D4B896 en hover
```

### Colores Semánticos

```
Success:
- Light: #E6F9F0 (background)
- Main: #10B981 (text/border)
- Dark: #059669 (hover)

Warning:
- Light: #FEF3C7
- Main: #F59E0B
- Dark: #D97706

Error/Destructive:
- Light: #FEE2E2
- Main: #EF4444
- Dark: #DC2626

Info:
- Light: #EFF6FF
- Main: #3B82F6
- Dark: #1D4ED8
```

### Neutrals (Grises)

```
Escala de grises neutral:
- 50: #FAFAFA (fondos claros)
- 100: #F5F5F5 (hover backgrounds)
- 150: #F0F0F0 (input backgrounds)
- 200: #E5E5E5 (borders)
- 300: #D4D4D4 (disabled)
- 400: #A3A3A3 (help text)
- 500: #737373 (secondary text)
- 600: #525252 (muted text)
- 700: #404040 (primary text)
- 800: #262626 (dark backgrounds)
- 900: #171717 (very dark)
```

### Uso en CSS

```css
/* En tailwind.config o globals.css */
:root {
  --primary: 339 55% 27%;
  --secondary: 39 45% 61%;
  --success: #10B981;
  --warning: #F59E0B;
  --error: #EF4444;
}

/* Aplicación en componentes */
<Button className="bg-primary text-primary-foreground">
  Acción principal
</Button>

<div className="border border-border bg-neutral-50">
  Contenedor neutro
</div>
```

---

## ✍️ Tipografía

### Font Stack

```
Sans Serif (UI):
font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;

Monospace (código):
font-family: 'Fira Code', 'Courier New', monospace;
```

### Escalas de Tamaño

| Clase | Tamaño | Línea | Uso |
|-------|--------|-------|-----|
| `text-xs` | 12px | 1rem | Labels pequeños, ayuda |
| `text-sm` | 14px | 1.25rem | Texto secundario, hints |
| `text-base` | 16px | 1.5rem | Texto body por defecto |
| `text-lg` | 18px | 1.75rem | Subtítulos, énfasis |
| `text-xl` | 20px | 1.75rem | Títulos pequeños |
| `text-2xl` | 24px | 2rem | Títulos medianos |
| `text-3xl` | 30px | 2.25rem | Títulos grandes |
| `text-4xl` | 36px | 2.5rem | Títulos muy grandes |
| `text-5xl` | 48px | 1.0 | Titulares principales |

### Pesos de Fuente

```
font-light (300)     - Fina, headings decorativos
font-normal (400)    - Regular, body text
font-medium (500)    - Labels, badges
font-semibold (600)  - Títulos secundarios
font-bold (700)      - Títulos principales
```

### Jerarquía Recomendada

```
H1: text-4xl font-bold text-primary
H2: text-3xl font-bold text-foreground
H3: text-2xl font-semibold text-foreground
H4: text-xl font-semibold text-foreground
P: text-base font-normal text-foreground
Label: text-sm font-semibold text-foreground
Hint: text-xs font-normal text-muted-foreground
```

---

## 📏 Espaciado y Layout

### Escala de Espaciado

```
xs:  4px  (0.25rem)
sm:  8px  (0.5rem)
md:  16px (1rem)
lg:  24px (1.5rem)
xl:  32px (2rem)
2xl: 48px (3rem)
3xl: 64px (4rem)
4xl: 96px (6rem)
```

### Patrones de Espaciado

```
Card interno:    padding: 1.5rem (md + lg)
Sección vertical: gap: 2rem (xl)
Formularios:      gap: 1rem (md)
Elementos inline: gap: 0.5rem (sm)
```

### Ejemplo de Estructura

```jsx
{/* Card principal con contenido */}
<Card className="p-6 space-y-4">
  <CardHeader className="pb-4">
    <h2 className="text-2xl font-bold">Título</h2>
  </CardHeader>

  <CardContent className="space-y-6">
    {/* Gap entre secciones principales */}
    <Section className="space-y-3">
      {/* Gap entre items */}
    </Section>
  </CardContent>
</Card>
```

---

## 🧩 Componentes

### Button (Botón)

```tsx
import { Button } from '@/components/ui/button';

// Variantes
<Button variant="default">Acción principal</Button>
<Button variant="secondary">Acción secundaria</Button>
<Button variant="outline">Acción neutra</Button>
<Button variant="ghost">Minimalista</Button>
<Button variant="destructive">Peligroso</Button>
<Button variant="link">Enlace</Button>

// Tamaños
<Button size="sm">Pequeño</Button>
<Button size="default">Normal</Button>
<Button size="lg">Grande</Button>

// Con ícono y loading
<Button icon={<PlusIcon />}>Crear</Button>
<Button isLoading>Guardando...</Button>

// Combinaciones
<Button variant="primary" size="lg" isLoading>
  <Loader2 className="mr-2" />
  Procesando...
</Button>
```

**Características:**
- ✅ Animación de escala en hover
- ✅ Animación de presión en click
- ✅ Loading state integrado
- ✅ Sombras profesionales
- ✅ Accesible (focus states)

### Input (Campo de entrada)

```tsx
import { FormField, Input } from '@/components/ui/form-field';

// Simple
<Input placeholder="Escribe aquí..." />

// Con validación
<FormField label="Email" required error="Email inválido">
  <Input
    type="email"
    error={hasError}
    placeholder="correo@ejemplo.com"
  />
</FormField>

// Con icono
<Input
  icon={<SearchIcon className="w-4 h-4" />}
  placeholder="Buscar..."
/>

// Password con reveal
<Input type="password" placeholder="Contraseña segura" />
// (Automáticamente muestra toggle de ver/ocultar)

// Con éxito
<Input success placeholder="Validado" />
```

**Características:**
- ✅ Borders animados en focus
- ✅ Soporta iconos a izquierda
- ✅ Password field con toggle visible
- ✅ Estados success/error/normal
- ✅ Disabled state visual

### FormField (Contenedor de formulario)

```tsx
import { FormField } from '@/components/ui/form-field';

<FormField
  label="Nombre"
  required
  error={errors.name}
  hint="Usa tu nombre legal"
>
  <Input
    value={name}
    onChange={(e) => setName(e.target.value)}
  />
</FormField>
```

**Incluye:**
- Label con asterisco si es requerido
- Error message animado
- Success indicator
- Hint text
- Transiciones suaves

### Card (Tarjeta)

```tsx
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';

<Card>
  <CardHeader>
    <CardTitle>Título de la tarjeta</CardTitle>
    <CardDescription>Descripción secundaria</CardDescription>
  </CardHeader>
  <CardContent>
    Contenido aquí
  </CardContent>
</Card>
```

**Estilos:**
- Fondo blanco con sombra suave
- Border 1px neutral
- Hover levanta la tarjeta
- Border radius 1rem

### Badge (Insignia)

```tsx
import { Badge } from '@/components/ui/badge';

<Badge variant="default">Activo</Badge>
<Badge variant="secondary">Pendiente</Badge>
<Badge variant="outline">Neutral</Badge>
<Badge variant="destructive">Error</Badge>
<Badge variant="success">Completado</Badge>
```

---

## ✨ Animaciones y Transiciones

### Animaciones en Framer Motion

```tsx
import { motion } from 'framer-motion';

// Fade in on mount
<motion.div
  initial={{ opacity: 0 }}
  animate={{ opacity: 1 }}
  transition={{ duration: 0.3 }}
>
  Contenido
</motion.div>

// Hover scale (para botones/tarjetas)
<motion.button
  whileHover={{ scale: 1.02 }}
  whileTap={{ scale: 0.98 }}
>
  Click aquí
</motion.button>

// Slide in from side
<motion.div
  initial={{ opacity: 0, x: -20 }}
  animate={{ opacity: 1, x: 0 }}
  transition={{ duration: 0.5, ease: 'easeOut' }}
>
  Desliza desde la izquierda
</motion.div>
```

### Durations Estándar

```
Fast:   150ms  (feedback inmediato)
Base:   300ms  (transiciones normales)
Slow:   500ms  (animaciones complejas)
Slower: 700ms  (entrada/salida de página)
```

### Easing Functions

```
smooth:  cubic-bezier(0.4, 0, 0.2, 1)   - Default suave
bounce:  cubic-bezier(0.34, 1.56, 0.64, 1) - Energético
sharp:   cubic-bezier(0.4, 0, 1, 1)    - Rápido
gentle:  cubic-bezier(0.22, 1, 0.36, 1) - Muy suave
```

### Page Transitions

```tsx
import PageTransition from '@/components/ui/PageTransition';

export default function Page() {
  return (
    <PageTransition>
      {/* Contenido de página */}
    </PageTransition>
  );
}

// Automáticamente anima entrada/salida con fade + slide
```

### Global Loader

```tsx
import { GlobalLoaderProvider, useGlobalLoader } from '@/components/ui/GlobalLoader';

// En root layout
<GlobalLoaderProvider>
  {children}
</GlobalLoaderProvider>

// Uso en componentes
const { setIsLoading, setMessage } = useGlobalLoader();

const handleSubmit = async () => {
  setIsLoading(true);
  setMessage('Procesando solicitud...');
  await api.post('/data');
  setIsLoading(false);
};
```

---

## 🎯 Patrones de UX

### Validación de Formularios

**Patrón: Validación en tiempo real + Mensajes claros**

```tsx
const [form, setForm] = useState({ email: '' });
const [errors, setErrors] = useState<Record<string, string>>({});

const validateEmail = (email: string) => {
  if (!email.includes('@')) {
    setErrors({ ...errors, email: 'Email inválido' });
  } else {
    setErrors({ ...errors, email: '' });
  }
};

// En el render
<FormField
  label="Email"
  required
  error={errors.email || ''}
>
  <Input
    type="email"
    value={form.email}
    onChange={(e) => {
      setForm({ ...form, email: e.target.value });
      validateEmail(e.target.value);
    }}
    error={!!errors.email}
  />
</FormField>
```

### Estados de Carga

```tsx
// Inline spinner en botón
<Button isLoading={isSaving}>
  {isSaving ? 'Guardando...' : 'Guardar'}
</Button>

// Global loader para operaciones pesadas
const { setIsLoading } = useGlobalLoader();

const handleLongOperation = async () => {
  setIsLoading(true);
  setMessage('Procesando datos...');
  await heavyComputation();
  setIsLoading(false);
};
```

### Mensajes de Error

```tsx
{error && (
  <AlertBox
    type="error"
    title="Error"
    message={error}
    details={[
      'Verifica tu conexión',
      'Intenta de nuevo'
    ]}
  />
)}
```

### Confirmación Antes de Acciones Destructivas

```tsx
const [showConfirm, setShowConfirm] = useState(false);

{showConfirm && (
  <motion.div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <Card className="max-w-sm">
      <CardHeader>
        <CardTitle>¿Estás seguro?</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <p>Esta acción no se puede deshacer.</p>
        <div className="flex gap-2 justify-end">
          <Button variant="outline" onClick={() => setShowConfirm(false)}>
            Cancelar
          </Button>
          <Button variant="destructive" onClick={handleDelete}>
            Eliminar
          </Button>
        </div>
      </CardContent>
    </Card>
  </motion.div>
)}
```

---

## ♿ Accesibilidad

### Requisitos Mínimos

✅ Contraste: Mínimo WCAG AA (4.5:1 para texto)
✅ Focus States: Todos los interactivos tienen focus visible
✅ Keyboard Nav: Funcional solo con teclado
✅ Labels: Todo input tiene label asociado
✅ ARIA: Uso correcto de roles y atributos

### Checklist para Componentes

```
□ Focus outline visible (ring-2 ring-primary)
□ Hover state distinto de focus
□ Disabled state visual
□ Label asociado a input (htmlFor)
□ Error mensajes descriptivos
□ Icons tienen aria-label si son funcionales
□ Contraste 4.5:1 o mejor
```

---

## 🛠️ Implementación

### Setup Inicial

```bash
# 1. Verificar que tailwind está instalado
npm ls tailwindcss

# 2. Verificar que framer-motion está instalado
npm ls framer-motion

# 3. Importar design tokens
import designTokens from '@/lib/design-system';
```

### En Root Layout

```tsx
import { ThemeProvider } from '@/contexts/ThemeContext';
import { GlobalLoaderProvider } from '@/components/ui/GlobalLoader';

export default function RootLayout({ children }) {
  return (
    <html>
      <body>
        <ThemeProvider>
          <GlobalLoaderProvider>
            {children}
          </GlobalLoaderProvider>
        </ThemeProvider>
      </body>
    </html>
  );
}
```

### Crear Nuevos Componentes

```tsx
import { motion } from 'framer-motion';
import designTokens from '@/lib/design-system';

export function MyComponent() {
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: designTokens.transitions.duration.base }}
      className="p-md rounded-lg shadow-soft bg-white"
    >
      Contenido
    </motion.div>
  );
}
```

### Uso de Design Tokens en CSS

```tsx
import { designTokens } from '@/lib/design-system';

const customStyles = {
  padding: designTokens.spacing.lg,
  color: designTokens.colors.primary[700],
  borderRadius: designTokens.borderRadius.lg,
  boxShadow: designTokens.shadows.md,
  transition: designTokens.transitions.common.all,
};
```

---

## 📚 Referencias Rápidas

### Importaciones Comunes

```tsx
import { Button } from '@/components/ui/button';
import { Input, Textarea } from '@/components/ui/input';
import { FormField } from '@/components/ui/form-field';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { AlertBox } from '@/components/ui/alert-box';
import { useGlobalLoader } from '@/components/ui/GlobalLoader';
import { useTheme } from '@/contexts/ThemeContext';
import designTokens from '@/lib/design-system';
import { motion } from 'framer-motion';
```

### Tailwind Classes Frecuentes

```
Padding:     p-4, px-6, py-3
Margin:      m-4, mb-2, mt-6
Gap:         gap-2, gap-4
Text:        text-sm, text-primary, font-bold
Colors:      bg-primary, text-foreground, border-border
Layout:      flex, grid, flex-col, items-center, justify-between
Shadow:      shadow-sm, shadow-md, shadow-lg
Border:      border, border-2, rounded-lg, rounded-xl
```

---

## ✅ Checklist de Calidad

- [ ] Todos los colores están en paleta definida
- [ ] Tipografía sigue jerarquía estándar
- [ ] Espaciado es múltiplo de 4px
- [ ] Animaciones duran 300ms o menos
- [ ] Focus states visibles
- [ ] Hover states diferenciados
- [ ] Error messages son claros
- [ ] Loading states existen
- [ ] Componentes reutilizables
- [ ] Dark mode compatible (si aplica)
- [ ] Responsive (móvil, tablet, desktop)
- [ ] Accesible (WCAG AA)

---

## 🔗 Archivos Relacionados

- `/src/lib/design-system.ts` - Tokens y configuración
- `/src/contexts/ThemeContext.tsx` - Gestión de tema
- `/src/components/ui/` - Componentes base
- `globals.css` - CSS custom properties
- `tailwind.config.ts` - Configuración de Tailwind

---

**Última actualización:** 31 Marzo 2026
**Mantenida por:** Equipo de Diseño COMECYT
