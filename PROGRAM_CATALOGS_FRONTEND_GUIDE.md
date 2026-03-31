# Dynamic Program Catalogs — Frontend Integration Guide

## Overview

The backend API now provides dynamic program catalogs through the `ProgramaCatalogController`. This guide shows how to integrate these endpoints into your Next.js frontend forms.

## Endpoints

All endpoints require authentication (`auth:api` middleware).

### Base URL
```
GET /api/catalogs/programa/{tipo_programa_id}/<endpoint>
```

### Individual Endpoints

| Endpoint | Returns | Use Case |
|----------|---------|----------|
| `/campos` | Form fields grouped by stage | Render dynamic form inputs |
| `/documentos` | Required documents | Display upload requirements |
| `/criterios` | Evaluation criteria | Show scoring rubric |
| `/rubros` | Budget line items | Form budget allocation |
| `/etapas` | Project phases/stages | Timeline visualization |
| `/modalidades` | Program variants | Selection dropdown |
| `/{tipo_programa_id}` | All of above | Initial page load (complete config) |

---

## React / Next.js Integration

### Setup: API Client

File: `apps/web/lib/api.ts`

```typescript
import axios from 'axios';
import Cookies from 'js-cookie';

const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api',
});

// Add auth token to every request
api.interceptors.request.use((config) => {
  const token = Cookies.get('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// ===== PROGRAM CATALOG QUERIES =====

export async function getProgramaCatalog(tipoProgramaId: number) {
  const response = await api.get(`/catalogs/programa/${tipoProgramaId}`);
  return response.data.data;
}

export async function getProgramaCampos(tipoProgramaId: number) {
  const response = await api.get(`/catalogs/programa/${tipoProgramaId}/campos`);
  return response.data.data;
}

export async function getProgramaDocumentos(tipoProgramaId: number) {
  const response = await api.get(`/catalogs/programa/${tipoProgramaId}/documentos`);
  return response.data.data;
}

export async function getProgramaCriterios(tipoProgramaId: number) {
  const response = await api.get(`/catalogs/programa/${tipoProgramaId}/criterios`);
  return response.data.data;
}

export async function getProgramaRubros(tipoProgramaId: number) {
  const response = await api.get(`/catalogs/programa/${tipoProgramaId}/rubros`);
  return response.data.data;
}

export async function getProgramaEtapas(tipoProgramaId: number) {
  const response = await api.get(`/catalogs/programa/${tipoProgramaId}/etapas`);
  return response.data.data;
}

export async function getProgramaModalidades(tipoProgramaId: number) {
  const response = await api.get(`/catalogs/programa/${tipoProgramaId}/modalidades`);
  return response.data.data;
}

export default api;
```

---

## Component Examples

### 1. Dynamic Form Fields Component

File: `apps/web/src/components/FormField.tsx`

```typescript
import React from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

interface FormFieldProps {
  field: any;
  value: any;
  onChange: (value: any) => void;
  error?: string;
}

export default function FormField({
  field,
  value,
  onChange,
  error,
}: FormFieldProps) {
  const { tipo_campo, etiqueta, nombre_campo, requerido, opciones_json, reglas_validacion_json } =
    field;

  const commonProps = {
    required: requerido,
    disabled: false,
  };

  const renderField = () => {
    switch (tipo_campo) {
      case 'text':
        return (
          <Input
            type="text"
            placeholder={etiqueta}
            value={value || ''}
            onChange={(e) => onChange(e.target.value)}
            maxLength={reglas_validacion_json?.maxLength || 255}
            {...commonProps}
          />
        );

      case 'number':
        return (
          <Input
            type="number"
            placeholder={etiqueta}
            value={value || ''}
            onChange={(e) => onChange(e.target.value ? Number(e.target.value) : '')}
            min={reglas_validacion_json?.min || 0}
            max={reglas_validacion_json?.max || 999999}
            step={reglas_validacion_json?.step || 1}
            {...commonProps}
          />
        );

      case 'date':
        return (
          <Input
            type="date"
            value={value || ''}
            onChange={(e) => onChange(e.target.value)}
            {...commonProps}
          />
        );

      case 'textarea':
        return (
          <Textarea
            placeholder={etiqueta}
            value={value || ''}
            onChange={(e) => onChange(e.target.value)}
            maxLength={reglas_validacion_json?.maxLength || 1000}
            rows={reglas_validacion_json?.rows || 4}
            {...commonProps}
          />
        );

      case 'select':
        return (
          <Select value={value || ''} onValueChange={onChange}>
            <SelectTrigger {...commonProps}>
              <SelectValue placeholder={etiqueta} />
            </SelectTrigger>
            <SelectContent>
              {opciones_json?.map((opcion: any) => (
                <SelectItem key={opcion.id} value={String(opcion.id)}>
                  {opcion.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        );

      case 'file':
        return (
          <Input
            type="file"
            onChange={(e) => onChange(e.target.files?.[0] || null)}
            {...commonProps}
          />
        );

      default:
        return <Input type="text" {...commonProps} />;
    }
  };

  return (
    <div className="space-y-2">
      <Label htmlFor={nombre_campo}>
        {etiqueta}
        {requerido && <span className="text-red-500 ml-1">*</span>}
      </Label>
      {renderField()}
      {error && <p className="text-sm text-red-500">{error}</p>}
    </div>
  );
}
```

---

### 2. Dynamic Form Container

File: `apps/web/src/components/SolicitudForm.tsx`

```typescript
'use client';

import React, { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import FormField from './FormField';
import { Button } from '@/components/ui/button';
import { getProgramaCatalog } from '@/lib/api';
import toast from 'react-hot-toast';

interface SolicitudFormProps {
  tipoProgramaId: number;
}

export default function SolicitudForm({ tipoProgramaId }: SolicitudFormProps) {
  const router = useRouter();
  const [programa, setProgramma] = useState<any>(null);
  const [campos, setCampos] = useState<any[]>([]);
  const [etapas, setEtapas] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [formData, setFormData] = useState<Record<string, any>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [submitting, setSubmitting] = useState(false);

  // Fetch program catalog on mount
  useEffect(() => {
    const loadCatalog = async () => {
      try {
        setLoading(true);
        const data = await getProgramaCatalog(tipoProgramaId);

        setProgramma(data.programa);
        setCampos(data.campos);
        setEtapas(data.etapas);

        // Initialize form with empty values
        const initialForm: Record<string, any> = {};
        data.campos.forEach((campo: any) => {
          initialForm[campo.nombre_campo] = '';
        });
        setFormData(initialForm);
      } catch (error: any) {
        console.error('Error loading catalog:', error);
        toast.error('Error al cargar el formulario');
      } finally {
        setLoading(false);
      }
    };

    loadCatalog();
  }, [tipoProgramaId]);

  // Handle field value changes
  const handleFieldChange = (nombreCampo: string, value: any) => {
    setFormData((prev) => ({
      ...prev,
      [nombreCampo]: value,
    }));

    // Clear error for this field
    if (errors[nombreCampo]) {
      setErrors((prev) => ({
        ...prev,
        [nombreCampo]: '',
      }));
    }
  };

  // Validate form data
  const validateForm = (): boolean => {
    const newErrors: Record<string, string> = {};

    campos.forEach((campo) => {
      if (campo.requerido && !formData[campo.nombre_campo]) {
        newErrors[campo.nombre_campo] = `${campo.etiqueta} es requerido`;
      }
    });

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  // Submit form
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validateForm()) {
      toast.error('Por favor completa todos los campos requeridos');
      return;
    }

    try {
      setSubmitting(true);

      // TODO: Call API to save solicitud
      const response = await fetch('/api/solicitudes', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          tipo_programa_id: tipoProgramaId,
          ...formData,
        }),
      });

      if (!response.ok) {
        throw new Error('Error al guardar solicitud');
      }

      toast.success('Solicitud guardada exitosamente');
      router.push('/solicitudes');
    } catch (error: any) {
      console.error('Error submitting form:', error);
      toast.error(error.message || 'Error al guardar');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return <div>Cargando formulario...</div>;
  }

  if (!programa) {
    return <div>Programa no encontrado</div>;
  }

  // Group campos by etapa
  const camposPorEtapa: Record<number | string, any[]> = {
    'sin_etapa': campos.filter((c) => !c.etapa_id),
  };

  etapas.forEach((etapa) => {
    camposPorEtapa[etapa.id] = campos.filter((c) => c.etapa_id === etapa.id);
  });

  return (
    <form onSubmit={handleSubmit} className="space-y-8 max-w-2xl">
      {/* Program Info */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h2 className="text-xl font-bold">{programa.nombre}</h2>
        <p className="text-sm text-gray-600 mt-1">{programa.descripcion}</p>
        <p className="text-sm text-gray-700 mt-2">
          Monto máximo: <strong>${programa.monto_maximo?.toLocaleString()}</strong>
        </p>
      </div>

      {/* Form Fields (grouped by etapa if multi-stage) */}
      {programa.tiene_etapas
        ? etapas.map((etapa) => (
            <div key={etapa.id}>
              <h3 className="text-lg font-semibold mb-4">{etapa.nombre}</h3>
              <div className="space-y-4">
                {(camposPorEtapa[etapa.id] || []).map((campo) => (
                  <FormField
                    key={campo.id}
                    field={campo}
                    value={formData[campo.nombre_campo]}
                    onChange={(value) =>
                      handleFieldChange(campo.nombre_campo, value)
                    }
                    error={errors[campo.nombre_campo]}
                  />
                ))}
              </div>
            </div>
          ))
        : // Non-staged form
          campos.map((campo) => (
            <FormField
              key={campo.id}
              field={campo}
              value={formData[campo.nombre_campo]}
              onChange={(value) => handleFieldChange(campo.nombre_campo, value)}
              error={errors[campo.nombre_campo]}
            />
          ))}

      {/* Submit Button */}
      <div className="flex gap-2 justify-end">
        <Button
          type="button"
          variant="outline"
          onClick={() => router.back()}
        >
          Cancelar
        </Button>
        <Button type="submit" disabled={submitting}>
          {submitting ? 'Guardando...' : 'Guardar Solicitud'}
        </Button>
      </div>
    </form>
  );
}
```

---

### 3. Document Upload Component

File: `apps/web/src/components/DocumentUpload.tsx`

```typescript
'use client';

import React, { useEffect, useState } from 'react';
import { getProgramaDocumentos } from '@/lib/api';
import toast from 'react-hot-toast';

interface DocumentUploadProps {
  tipoProgramaId: number;
  solicitudId: number;
}

export default function DocumentUpload({
  tipoProgramaId,
  solicitudId,
}: DocumentUploadProps) {
  const [documentos, setDocumentos] = useState<any[]>([]);
  const [etapas, setEtapas] = useState<any[]>([]);
  const [uploads, setUploads] = useState<Record<number, File | null>>({});
  const [uploading, setUploading] = useState<Record<number, boolean>>({});

  useEffect(() => {
    const loadDocumentos = async () => {
      try {
        const data = await getProgramaDocumentos(tipoProgramaId);
        setDocumentos(data.documentos);
        setEtapas(data.etapas);
      } catch (error) {
        console.error('Error loading documents:', error);
        toast.error('Error al cargar documentos');
      }
    };

    loadDocumentos();
  }, [tipoProgramaId]);

  const handleFileSelect = (documentoId: number, file: File | null) => {
    setUploads((prev) => ({
      ...prev,
      [documentoId]: file,
    }));
  };

  const handleUpload = async (documentoId: number) => {
    const file = uploads[documentoId];
    if (!file) {
      toast.error('Selecciona un archivo');
      return;
    }

    try {
      setUploading((prev) => ({ ...prev, [documentoId]: true }));

      const formData = new FormData();
      formData.append('documento_id', String(documentoId));
      formData.append('archivo', file);

      const response = await fetch(
        `/api/solicitudes/${solicitudId}/documentos`,
        {
          method: 'POST',
          body: formData,
        }
      );

      if (!response.ok) {
        throw new Error('Error al subir documento');
      }

      toast.success('Documento subido exitosamente');
      setUploads((prev) => ({ ...prev, [documentoId]: null }));
    } catch (error: any) {
      toast.error(error.message || 'Error al subir documento');
    } finally {
      setUploading((prev) => ({ ...prev, [documentoId]: false }));
    }
  };

  // Group by etapa
  const docsPorEtapa: Record<string, any[]> = { 'sin_etapa': [] };
  etapas.forEach((etapa) => {
    docsPorEtapa[etapa.id] = [];
  });

  documentos.forEach((doc) => {
    if (doc.etapa_id) {
      docsPorEtapa[doc.etapa_id]?.push(doc);
    } else {
      docsPorEtapa['sin_etapa'].push(doc);
    }
  });

  return (
    <div className="space-y-6">
      <h3 className="text-lg font-semibold">Documentos Requeridos</h3>

      {etapas.length > 0 ? (
        etapas.map((etapa) => (
          <div key={etapa.id}>
            <h4 className="font-medium mb-3">{etapa.nombre}</h4>
            <div className="space-y-3">
              {(docsPorEtapa[etapa.id] || []).map((doc) => (
                <div
                  key={doc.id}
                  className="border rounded-lg p-4 flex items-center justify-between"
                >
                  <div className="flex-1">
                    <p className="font-medium">{doc.nombre}</p>
                    <p className="text-sm text-gray-600">{doc.descripcion}</p>
                    <p className="text-xs text-gray-500 mt-1">
                      Formato: {doc.formato_permitido} | Máximo:{' '}
                      {doc.tamaño_maximo_mb}MB
                      {doc.obligatorio && (
                        <span className="text-red-500 ml-2">*Obligatorio</span>
                      )}
                    </p>
                  </div>

                  <div className="flex gap-2 items-center">
                    <input
                      type="file"
                      id={`doc-${doc.id}`}
                      onChange={(e) =>
                        handleFileSelect(doc.id, e.target.files?.[0] || null)
                      }
                      accept={doc.formato_permitido}
                      className="hidden"
                    />
                    <label
                      htmlFor={`doc-${doc.id}`}
                      className="cursor-pointer px-3 py-1 bg-blue-100 text-blue-700 rounded text-sm"
                    >
                      {uploads[doc.id]?.name || 'Seleccionar'}
                    </label>
                    <button
                      type="button"
                      onClick={() => handleUpload(doc.id)}
                      disabled={!uploads[doc.id] || uploading[doc.id]}
                      className="px-3 py-1 bg-green-600 text-white rounded text-sm disabled:opacity-50"
                    >
                      {uploading[doc.id] ? 'Subiendo...' : 'Subir'}
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))
      ) : (
        <div className="space-y-3">
          {docsPorEtapa['sin_etapa'].map((doc) => (
            <div
              key={doc.id}
              className="border rounded-lg p-4 flex items-center justify-between"
            >
              {/* ... same as above ... */}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
```

---

### 4. Evaluation Criteria Display Component

File: `apps/web/src/components/CriteriosList.tsx`

```typescript
'use client';

import React, { useEffect, useState } from 'react';
import { getProgramaCriterios } from '@/lib/api';
import { Progress } from '@/components/ui/progress';

interface CriteriosListProps {
  tipoProgramaId: number;
}

export default function CriteriosList({ tipoProgramaId }: CriteriosListProps) {
  const [criterios, setCriterios] = useState<any[]>([]);
  const [etapas, setEtapas] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const loadCriterios = async () => {
      try {
        const data = await getProgramaCriterios(tipoProgramaId);
        setCriterios(data.criterios);
        setEtapas(data.etapas);
      } catch (error) {
        console.error('Error loading criteria:', error);
      } finally {
        setLoading(false);
      }
    };

    loadCriterios();
  }, [tipoProgramaId]);

  if (loading) return <div>Cargando criterios...</div>;

  return (
    <div className="space-y-6">
      <h3 className="text-lg font-semibold">Criterios de Evaluación</h3>

      {etapas.length > 0 ? (
        etapas.map((etapa) => (
          <div key={etapa.id}>
            <h4 className="font-medium mb-3">{etapa.nombre}</h4>
            <div className="space-y-3">
              {etapa.criterios_en_etapa?.map((criterio: any) => (
                <div key={criterio.id} className="bg-white border rounded-lg p-4">
                  <div className="flex justify-between items-start mb-2">
                    <p className="font-medium">{criterio.nombre}</p>
                    <span className="text-sm bg-blue-100 text-blue-700 px-2 py-1 rounded">
                      {criterio.ponderacion}%
                    </span>
                  </div>
                  <p className="text-sm text-gray-600 mb-2">{criterio.descripcion}</p>
                  <div className="text-xs text-gray-500">
                    Puntaje máximo: {criterio.puntaje_maximo}
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))
      ) : (
        <div className="space-y-3">
          {criterios.map((criterio) => (
            <div key={criterio.id} className="bg-white border rounded-lg p-4">
              <div className="flex justify-between items-start mb-2">
                <p className="font-medium">{criterio.nombre}</p>
                <span className="text-sm bg-blue-100 text-blue-700 px-2 py-1 rounded">
                  {criterio.ponderacion}%
                </span>
              </div>
              <p className="text-sm text-gray-600">{criterio.descripcion}</p>
            </div>
          ))}
        </div>
      )}

      <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <p className="text-sm text-gray-700">
          <strong>Puntaje mínimo aprobatorio:</strong> 80/100
        </p>
      </div>
    </div>
  );
}
```

---

## Response Structure Examples

### `/campos` Response
```json
{
  "data": {
    "programa": {
      "id": 1,
      "clave": "PFPI",
      "nombre": "Programa de Formación de Personal de Investigación",
      "tiene_etapas": true,
      "num_etapas": 2,
      "monto_maximo": 60000.00,
      "porcentaje_aportacion_solicitante": 10.00
    },
    "campos": [
      {
        "id": 1,
        "nombre_campo": "titulo_proyecto",
        "etiqueta": "Título del Proyecto",
        "tipo_campo": "text",
        "orden": 1,
        "requerido": true,
        "etapa_id": null,
        "etapa": null,
        "opciones_json": null,
        "reglas_validacion_json": { "maxLength": 500 }
      },
      {
        "id": 2,
        "nombre_campo": "monto_solicitado",
        "etiqueta": "Monto Solicitado (MXN)",
        "tipo_campo": "number",
        "orden": 2,
        "requerido": true,
        "etapa_id": 1,
        "etapa": { "id": 1, "numero_etapa": 1, "nombre": "Etapa 1" },
        "opciones_json": null,
        "reglas_validacion_json": { "min": 0, "max": 60000 }
      }
    ],
    "etapas": [
      {
        "id": 1,
        "numero_etapa": 1,
        "nombre": "Formulación de Propuesta",
        "descripcion": "Presentación de idea"
      },
      {
        "id": 2,
        "numero_etapa": 2,
        "nombre": "Evaluación Técnica",
        "descripcion": "Revisión de propuesta"
      }
    ]
  },
  "message": "Campos del programa obtenidos exitosamente"
}
```

### `/criterios` Response
```json
{
  "data": {
    "programa": {
      "id": 1,
      "clave": "PFPI",
      "nombre": "Programa de Formación...",
      "requiere_evaluacion_tecnica": true,
      "puntaje_minimo_aprobatorio": 80.00
    },
    "criterios": [
      {
        "id": 1,
        "nombre": "Viabilidad Técnica",
        "descripcion": "Evaluación de factibilidad técnica del proyecto",
        "ponderacion": 25.00,
        "puntaje_maximo": 100,
        "orden": 1,
        "etapa_id": null
      }
    ],
    "etapas": [
      {
        "id": 1,
        "numero_etapa": 1,
        "nombre": "Evaluación Inicial",
        "criterios_en_etapa": [
          { "id": 1, "nombre": "Viabilidad Técnica", "ponderacion": 25.00 }
        ]
      }
    ],
    "suma_ponderaciones": 100.00,
    "num_criterios": 4
  },
  "message": "Criterios de evaluación obtenidos exitosamente"
}
```

---

## Type Definitions

File: `apps/web/types/programa.ts`

```typescript
export interface TipoPrograma {
  id: number;
  clave: string;
  nombre: string;
  descripcion?: string;
  tipo_apoyo: 'reembolso' | 'concurrente' | 'honorarios';
  tiene_etapas: boolean;
  num_etapas: number;
  requiere_evaluacion_tecnica: boolean;
  requiere_fianza: boolean;
  monto_maximo: number;
  porcentaje_aportacion_solicitante: number;
  puntaje_minimo_aprobatorio: number;
  activo: boolean;
}

export interface ProgramaCampo {
  id: number;
  tipo_programa_id: number;
  etapa_id?: number;
  nombre_campo: string;
  etiqueta: string;
  tipo_campo: 'text' | 'number' | 'select' | 'date' | 'textarea' | 'file';
  opciones_json?: Array<{ id: number; label: string }>;
  reglas_validacion_json?: Record<string, any>;
  orden: number;
  requerido: boolean;
  activo: boolean;
  etapa?: ProgramaEtapa;
}

export interface ProgramaDocumento {
  id: number;
  tipo_programa_id: number;
  etapa_id?: number;
  nombre: string;
  descripcion?: string;
  formato_permitido?: string;
  tamaño_maximo_mb: number;
  obligatorio: boolean;
  orden: number;
  activo: boolean;
  etapa?: ProgramaEtapa;
}

export interface ProgramaCriterioEvaluacion {
  id: number;
  tipo_programa_id: number;
  etapa_id?: number;
  nombre: string;
  descripcion?: string;
  ponderacion: number;
  puntaje_maximo: number;
  orden: number;
  activo: boolean;
  etapa?: ProgramaEtapa;
}

export interface ProgramaRubro {
  id: number;
  tipo_programa_id: number;
  clave: string;
  nombre: string;
  descripcion?: string;
  porcentaje_maximo?: number;
  activo: boolean;
}

export interface ProgramaEtapa {
  id: number;
  tipo_programa_id: number;
  numero_etapa: number;
  nombre: string;
  descripcion?: string;
  duracion_meses?: number;
  es_evaluacion_tecnica: boolean;
  puntaje_minimo?: number;
  activo: boolean;
}

export interface ProgramaModalidad {
  id: number;
  tipo_programa_id: number;
  clave: string;
  nombre: string;
  descripcion?: string;
  monto_maximo_especifico?: number;
  activo: boolean;
}
```

---

## Best Practices

1. **Cache at page level** — Use React Query or SWR to cache catalog responses
2. **Validate before submit** — Check required fields match `requerido` flag
3. **Handle validation rules** — Apply `reglas_validacion_json` constraints to inputs
4. **Display error messages** — Map validation errors to specific fields
5. **Group fields by etapa** — If `tiene_etapas = true`, organize form by stage
6. **Show document requirements** — Display file size, format, and obligatorio status
7. **Render evaluation criteria** — Show ponderacion as percentage bars
8. **Handle loading states** — Show spinner while loading catalog

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| 401 Unauthorized | Ensure token is in `Authorization: Bearer <token>` header |
| 404 Not Found | Verify `tipo_programa_id` exists and is active |
| CORS errors | Backend should have CORS enabled for your frontend domain |
| Fields not showing | Check `activo = true` for campos; inactive ones are filtered |
| Caching stale data | Backend invalidates cache after 5 minutes; restart server to clear manually |
