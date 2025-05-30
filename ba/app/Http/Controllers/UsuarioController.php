<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PhpParser\Node\Stmt\TryCatch;
use Illuminate\Support\Facades\Log;
use App\Models\Alumno;
use App\Models\Docente;

class UsuarioController extends Controller
{
    public function verUsuarios()
    {
        try {
            $usuarios = User::with('rol')->get();
            return response()->json([$usuarios], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener Usuarios',
            ], 500);
        }
    }

    public function crearUsuario(Request $request)
    {
        // Log para depuración
        Log::info('Intentando crear usuario con correo: ' . $request->correo);
        Log::info('Correos existentes en users:', User::pluck('correo')->toArray());
        $validator = Validator::make($request->all(), [
            /*
            'nombre' => 'required|string|min:2|max:100',
            'apellido' => 'required|string|min:5|max:100',
            'fecha_nacimiento' => 'required|date',
            'correo' => 'required|email|min:5|max:50|unique',
            'password' => 'required|min:8',
            'rol_id' => 'required'
            */

            'nombre' => 'required|string|min:2|max:100',
            'apellido' => 'required|string|min:5|max:100', // Corregido: 'apellido'
            'fecha_nacimiento' => 'required|date',
            'correo' => 'required|email|min:5|max:50|unique:users,correo',
            'password' => 'required|min:8',
            'rol_id' => 'required|exists:roles,id' // Validar que el rol exista
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        try {
            $nuveoUsuario = new User();
            $nuveoUsuario->nombre = $request->nombre;
            $nuveoUsuario->apellido = $request->apellido;
            $nuveoUsuario->fecha_nacimiento = $request->fecha_nacimiento;
            $nuveoUsuario->correo = $request->correo;
            $nuveoUsuario->password = bcrypt($request->password);
            // Si la petición viene de la pestaña Alumnos, forzar rol_id a 2
            if ($request->has('desde_alumnos') && $request->desde_alumnos) {
                $nuveoUsuario->rol_id = 2;
            } else {
                $nuveoUsuario->rol_id = $request->rol_id;
            }
            $nuveoUsuario->save();
            // Si el rol es alumno (rol_id = 2), crear registro en alumnos
            if ((int)$nuveoUsuario->rol_id === 2) {
                Alumno::create(['id' => $nuveoUsuario->id]);
            }
            // Si el rol es docente (rol_id = 3), crear registro en docentes
            if ((int)$nuveoUsuario->rol_id === 3) {
                Docente::create(['id' => $nuveoUsuario->id]);
            }
            return response()->json(["message" => 'Usuario Creado'], 201);
        } catch (\Exception $e) {
            //throw $th;

            $errorData = [
                'error' => 'Error en la creación de usuario',
                'code' => 'user_creation_error'
            ];

            // Solo en desarrollo mostrar detalles técnicos
            if (config('app.debug')) {
                $errorData['debug'] = [
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ];
            }

            return response()->json($errorData, 500);
            //return response()->json(['error'=>'Error Usuario No CREADO', $e], 500);
        }
    }
    public function actualizarUsuario(Request $request, $id)
    {
        \Log::info('Datos recibidos para actualizar usuario:', $request->all());
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:2|max:100',
            'apellido' => 'required|string|min:5|max:100',
            'fecha_nacimiento' => 'required|date',
            'correo' => 'required|email|min:5|max:50|unique:users,correo,' . $id,
            'rol_id' => 'required|exists:roles,id',
            'password' => 'nullable|min:8'
        ]);
        if ($validator->fails()) {
            \Log::warning('Validación fallida al actualizar usuario:', $validator->errors()->toArray());
            return response()->json(['error' => $validator->errors()], 422);
        }
        try {
            $usuarioEncontrado = User::find($id);
            if (!$usuarioEncontrado) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }

            $data = [
                'nombre' => $request->nombre,
                'apellido' => $request->apellido,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'correo' => $request->correo,
                'rol_id' => $request->rol_id
            ];
            if ($request->filled('password')) {
                $data['password'] = bcrypt($request->password);
            }

            $usuarioEncontrado->update($data);
            \Log::info('Usuario actualizado:', $usuarioEncontrado->toArray());
            $usuarioEncontrado->refresh();

            // --- GESTIÓN DE ROLES Y TABLAS ---
            $rolNuevo = (int)$request->rol_id;
            if ($rolNuevo === 2) {
                \App\Models\Alumno::firstOrCreate(['id' => $usuarioEncontrado->id]);
                \App\Models\Docente::where('id', $usuarioEncontrado->id)->delete();
            } elseif ($rolNuevo === 3) {
                \App\Models\Docente::firstOrCreate(['id' => $usuarioEncontrado->id]);
                \App\Models\Alumno::where('id', $usuarioEncontrado->id)->delete();
            } else {
                \App\Models\Alumno::where('id', $usuarioEncontrado->id)->delete();
                \App\Models\Docente::where('id', $usuarioEncontrado->id)->delete();
            }
            // --- FIN GESTIÓN DE ROLES ---

            return response()->json([
                'message' => 'Usuario actualizado',
                'usuario' => $usuarioEncontrado->load('rol')
            ], 200);
        } catch (\Throwable $th) {
            \Log::error('Error al actualizar usuario:', ['exception' => $th->getMessage()]);
            return response()->json([
                'error' => 'Error al Actualizar el Usuario',
            ], 500);
        }
    }
    public function eliminarUsuario($id)
    {
        try {
            $usuarioEncontrado = User::find($id);
            if (!$usuarioEncontrado) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }
            $usuarioEncontrado->delete();
            return response()->json(['message' => 'Usuario Eliminado'], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'Error al Eliminar el Usuario',
            ], 500);
        }
    }
}
