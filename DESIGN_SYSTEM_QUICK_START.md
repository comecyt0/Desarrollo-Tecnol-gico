# Design System COMECYT - Quick Start Guide

**Para desarrolladores que necesitan implementar vistas rápidamente**

---

## 🚀 Inicio Rápido

### 1. Estructura Básica de Página

```tsx
'use client';

import { motion } from 'framer-motion';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import PageTransition from '@/components/ui/PageTransition';
import designTokens from '@/lib/design-system';

export default function MyPage() {
  return (
    <PageTransition>
      <div className="min-h-screen bg-background p-6 md:p-8">
        {/* Contenedor con espaciado */}
        <div className="max-w-7xl mx-auto space-y-6">

          {/* Header */}
          <motion.div
            initial={{ opacity: 0, y: -20 }}
            animate={{ opacity: 1, y: 0 }}
          >
            <h1 className="text-4xl font-bold text-foreground">Título de Página</h1>
            <p className="text-lg text-muted-foreground mt-2">Descripción</p>
          </motion.div>

          {/* Contenido principal */}
          <Card>
            <CardHeader>
              <CardTitle>Sección Principal</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {/* Contenido aquí */}
            </CardContent>
          </Card>
        </div>
      </div>
    </PageTransition>
  );
}
```

### 2. Formulario Simple

```tsx
import { useState } from 'react';
import { FormField, Input } from '@/components/ui/form-field';
import { Button } from '@/components/ui/button';
import { useGlobalLoader } from '@/components/ui/GlobalLoader';

export function MyForm() {
  const [form, setForm] = useState({ email: '', name: '' });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const { setIsLoading } = useGlobalLoader();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);

    try {
      await api.post('/endpoint', form);
      // Success
    } catch (error) {
      setErrors({ form: 'Error al guardar' });
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6 max-w-md">
      <FormField label="Nombre" required error={errors.name}>
        <Input
          value={form.name}
          onChange={(e) => setForm({ ...form, name: e.target.value })}
          placeholder="Tu nombre"
        />
      </FormField>

      <FormField label="Email" required error={errors.email}>
        <Input
          type="email"
          value={form.email}
          onChange={(e) => setForm({ ...form, email: e.target.value })}
          placeholder="tu@email.com"
        />
      </FormField>

      <Button type="submit" size="lg" className="w-full">
        Guardar
      </Button>
    </form>
  );
}
```

### 3. Lista/Tabla con Animaciones

```tsx
import { motion } from 'framer-motion';
import { Button } from '@/components/ui/button';
import { Edit, Trash2 } from 'lucide-react';

export function ItemList({ items, onEdit, onDelete }) {
  const container = {
    hidden: { opacity: 0 },
    show: {
      opacity: 1,
      transition: {
        staggerChildren: 0.1,
      },
    },
  };

  const item = {
    hidden: { opacity: 0, y: 20 },
    show: { opacity: 1, y: 0 },
  };

  return (
    <motion.div
      variants={container}
      initial="hidden"
      animate="show"
      className="space-y-3"
    >
      {items.map((itemData) => (
        <motion.div
          key={itemData.id}
          variants={item}
          className="flex items-center justify-between p-4 bg-white border border-border rounded-lg hover:shadow-md transition-shadow"
        >
          <div>
            <h4 className="font-semibold text-foreground">{itemData.name}</h4>
            <p className="text-sm text-muted-foreground">{itemData.description}</p>
          </div>

          <div className="flex gap-2">
            <Button
              size="icon-sm"
              variant="outline"
              onClick={() => onEdit(itemData.id)}
            >
              <Edit className="w-4 h-4" />
            </Button>
            <Button
              size="icon-sm"
              variant="outline"
              className="text-destructive"
              onClick={() => onDelete(itemData.id)}
            >
              <Trash2 className="w-4 h-4" />
            </Button>
          </div>
        </motion.div>
      ))}
    </motion.div>
  );
}
```

### 4. Modal/Diálogo

```tsx
import { motion, AnimatePresence } from 'framer-motion';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { X } from 'lucide-react';

export function MyModal({ isOpen, onClose, onConfirm, title, children }) {
  return (
    <AnimatePresence>
      {isOpen && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
        >
          <motion.div
            initial={{ scale: 0.95, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            exit={{ scale: 0.95, opacity: 0 }}
          >
            <Card className="w-full max-w-md">
              <CardHeader className="flex flex-row justify-between items-center pb-4">
                <CardTitle>{title}</CardTitle>
                <button
                  onClick={onClose}
                  className="text-muted-foreground hover:text-foreground"
                >
                  <X className="w-5 h-5" />
                </button>
              </CardHeader>

              <CardContent className="space-y-4">
                {children}

                <div className="flex gap-2 justify-end pt-4 border-t border-border">
                  <Button variant="outline" onClick={onClose}>
                    Cancelar
                  </Button>
                  <Button onClick={onConfirm}>
                    Confirmar
                  </Button>
                </div>
              </CardContent>
            </Card>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
```

---

## 📚 Componentes Disponibles

### Componentes Base

```tsx
// Botón
<Button variant="default|secondary|outline|ghost|destructive|link" size="sm|default|lg">
  Texto
</Button>

// Input
<Input type="text|email|password" error={bool} success={bool} />

// Textarea
<Textarea error={bool} maxLength={100} showCharCount />

// FormField (envoltorio para validación)
<FormField label="Label" required error="Error message">
  <Input />
</FormField>

// Card
<Card>
  <CardHeader>
    <CardTitle>Título</CardTitle>
    <CardDescription>Descripción</CardDescription>
  </CardHeader>
  <CardContent>Contenido</CardContent>
  <CardFooter>Footer</CardFooter>
</Card>

// Badge
<Badge variant="default|secondary|outline|destructive">Label</Badge>

// AlertBox
<AlertBox type="error|warning|success|info" title="Título" message="Mensaje" />
```

### Contextos y Hooks

```tsx
// Tema
const { mode, isDark, setMode, toggleTheme } = useTheme();

// Loader global
const { isLoading, setIsLoading, setMessage } = useGlobalLoader();

// Design tokens
import designTokens from '@/lib/design-system';
```

---

## 🎨 Usar Colores

```tsx
// Tailwind classes
<div className="bg-primary text-primary-foreground">
  Texto en color primario
</div>

<div className="bg-secondary text-secondary-foreground">
  Texto en color secundario
</div>

<div className="bg-success-light text-success-main">
  Estado de éxito
</div>

// Direct tokens (en JS/TS)
import designTokens from '@/lib/design-system';
const color = designTokens.colors.primary[700];
```

---

## 📏 Espaciado

```tsx
// Padding
<div className="p-4">              {/* 16px */}
<div className="px-6 py-3">         {/* 24px x, 12px y */}

// Margin
<div className="m-4 mt-6">          {/* 16px general, 24px top */}

// Gap
<div className="flex gap-2">        {/* 8px entre items */}

// Space (para vertical stacking)
<div className="space-y-4">         {/* 16px entre children */}
```

---

## 🎬 Animaciones Comunes

```tsx
// Fade in on mount
<motion.div
  initial={{ opacity: 0 }}
  animate={{ opacity: 1 }}
  transition={{ duration: 0.3 }}
/>

// Slide in from left
<motion.div
  initial={{ opacity: 0, x: -20 }}
  animate={{ opacity: 1, x: 0 }}
/>

// Scale on hover
<motion.button
  whileHover={{ scale: 1.05 }}
  whileTap={{ scale: 0.95 }}
/>

// Stagger children
<motion.div>
  {items.map((item, i) => (
    <motion.div
      key={i}
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      transition={{ delay: i * 0.1 }}
    />
  ))}
</motion.div>
```

---

## ✅ Checklist para Nueva Página/Componente

```
□ Usa PageTransition para páginas
□ Inputs tienen FormField wrapper
□ Botones principales usan variant="default"
□ Colores de paleta definida (no hardcodeados)
□ Espaciado es múltiplo de 4px (4, 8, 12, 16, 24, etc.)
□ Loading states implementados
□ Error states con mensajes claros
□ Focus states visibles (automáticos en componentes base)
□ Responsive (funciona en móvil, tablet, desktop)
□ Animaciones suaves (300ms típico)
□ Accesible (labels, colors contrast, etc.)
□ Documentado
```

---

## 🐛 Troubleshooting

### Los colores se ven grises/claros

**Causa:** Tailwind CSS no está compilando las clases de color
**Solución:** Reinicia el servidor (`npm run dev`)

### Las animaciones se sienten lentas

**Causa:** Duration muy largo o easing incorrecto
**Solución:** Usa `duration: 300` o `designTokens.transitions.duration.base`

### El GlobalLoader no aparece

**Causa:** No está envuelto en GlobalLoaderProvider
**Solución:** Verifica que root layout tiene `<GlobalLoaderProvider>`

### Input no muestra error

**Causa:** No tiene `FormField` wrapper o no tiene prop `error={bool}`
**Solución:** Usa `<FormField error={errors.field}><Input error={!!errors.field} /></FormField>`

---

## 📖 Más Información

**Ver:** `DESIGN_SYSTEM.md` para documentación completa, todos los tokens y patrones avanzados.

---

**Última actualización:** 31 Marzo 2026
