<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AccesoRechazadoMail;
use App\Models\Institucion;
use App\Models\SolicitudAcceso;
use App\Models\User;
use App\Notifications\AccesoAprobado;
use App\Notifications\NuevaSolicitudAcceso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class RegistroAccesoController extends Controller
{
    /**
     * Public endpoint — submit a new access request.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
                Rule::unique('solicitudes_acceso', 'email'),
            ],
            'password' => 'required|string|min:8|confirmed',
            'institucion_nombre' => 'required|string|max:255',
            'cargo' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'motivo' => 'nullable|string',
        ]);

        $solicitudAcceso = SolicitudAcceso::create([
            'nombre' => $request->nombre,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'institucion_nombre' => $request->institucion_nombre,
            'cargo' => $request->cargo,
            'telefono' => $request->telefono,
            'motivo' => $request->motivo,
            'estado' => 'pendiente',
        ]);

        // Notify all admin users
        $admins = User::where('rol_id', 1)->where('activo', true)->get();
        foreach ($admins as $admin) {
            $admin->notify(new NuevaSolicitudAcceso($solicitudAcceso));
        }

        return response()->json([
            'message' => 'Tu solicitud de acceso ha sido recibida. Te notificaremos por correo cuando sea revisada.',
            'solicitud_id' => $solicitudAcceso->id,
        ], 201);
    }

    /**
     * Admin only — list all access requests ordered by creation date.
     */
    public function index()
    {
        $solicitudes = SolicitudAcceso::with('revisadoPor:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($solicitudes);
    }

    /**
     * Admin only — approve an access request and create the user account.
     */
    public function aprobar(SolicitudAcceso $solicitudAcceso)
    {
        if ($solicitudAcceso->estado !== 'pendiente') {
            return response()->json([
                'message' => 'Esta solicitud ya fue procesada.',
            ], 422);
        }

        DB::transaction(function () use ($solicitudAcceso) {
            // Find or create institution
            $institucion = Institucion::firstOrCreate(
                ['nombre' => $solicitudAcceso->institucion_nombre],
                ['nombre' => $solicitudAcceso->institucion_nombre]
            );

            // Create the user account (rol_id=4 = solicitante)
            $user = User::create([
                'name' => $solicitudAcceso->nombre,
                'email' => $solicitudAcceso->email,
                'password' => $solicitudAcceso->password, // already hashed
                'rol_id' => config('comecyt.roles.solicitante'),
                'institucion_id' => $institucion->id,
                'activo' => true,
                'cargo' => $solicitudAcceso->cargo,
                'telefono' => $solicitudAcceso->telefono,
            ]);

            // Mark request as approved
            $solicitudAcceso->update([
                'estado' => 'aprobada',
                'revisado_por' => auth()->id(),
                'revisado_at' => now(),
            ]);

            // Notify applicant
            $user->notify(new AccesoAprobado($solicitudAcceso));
        });

        return response()->json([
            'message' => 'Solicitud aprobada. La cuenta de usuario ha sido creada.',
        ]);
    }

    /**
     * Admin only — reject an access request with a reason.
     */
    public function rechazar(Request $request, SolicitudAcceso $solicitudAcceso)
    {
        $request->validate([
            'motivo_rechazo' => 'required|string',
        ]);

        if ($solicitudAcceso->estado !== 'pendiente') {
            return response()->json([
                'message' => 'Esta solicitud ya fue procesada.',
            ], 422);
        }

        $solicitudAcceso->update([
            'estado' => 'rechazada',
            'motivo_rechazo' => $request->motivo_rechazo,
            'revisado_por' => auth()->id(),
            'revisado_at' => now(),
        ]);

        // Send rejection email directly via Mail facade (applicant has no User account yet)
        Mail::to($solicitudAcceso->email)
            ->queue(new AccesoRechazadoMail($solicitudAcceso));

        return response()->json([
            'message' => 'Solicitud rechazada. Se ha notificado al solicitante.',
        ]);
    }
}
