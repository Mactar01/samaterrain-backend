<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Owner;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    // ————————————————————————————————————————————————————————————————————————————————————————————————————
    // POST /api/v1/auth/register
    // ————————————————————————————————————————————————————————————————————————————————————————————————————
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:100'],
            'email'                 => ['nullable', 'string', 'email', 'max:255', 'unique:users'],
            'phone'                 => ['required', 'string', 'max:20', 'unique:users'],
            'password'              => ['required', 'confirmed', 'string', 'min:8'],
        ]);

        // OTP TEMPORAIREMENT DESACTIVE (commenté)
        // $otp = rand(1000, 9999);

        $user = User::create([
            'name'           => $data['name'],
            'email'          => $data['email'] ?? null,
            'phone'          => $data['phone'],
            'password'       => Hash::make($data['password']),
            'role'           => 'player',
            'is_active'      => true,
            // 'otp_code'       => $otp,
            // 'otp_expires_at' => now()->addMinutes(10),
        ]);

        // \Log::info("=== SIMULATION SMS ===");
        // \Log::info("Numéro : {$user->phone}");
        // \Log::info("Code OTP : {$otp}");
        // \Log::info("======================");

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Compte créé avec succès.',
            'user'    => $this->userResource($user),
            'token'   => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['nullable', 'string'],
            'phone'    => ['nullable', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (empty($data['email']) && empty($data['phone'])) {
            throw ValidationException::withMessages([
                'email' => ['L\'email ou le numéro de téléphone est requis.'],
            ]);
        }

        $loginValue = !empty($data['email']) ? $data['email'] : $data['phone'];
        $user = User::where('email', $loginValue)->orWhere('phone', $loginValue)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants sont incorrects.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Votre compte a été bloqué ou désactivé par l\'administrateur. Veuillez contacter le support.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'user'    => $this->userResource($user),
            'token'   => $token,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone'    => ['required', 'string'],
            'otp_code' => ['required', 'string'],
        ]);

        $user = User::where('phone', $data['phone'])->first();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        if ($user->otp_code !== $data['otp_code']) {
            return response()->json(['message' => 'Code OTP incorrect.'], 400);
        }

        if ($user->otp_expires_at && now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['message' => 'Ce code OTP a expiré.'], 400);
        }

        // Code valide, on active le compte
        $user->is_active = true;
        $user->otp_code = null;
        $user->otp_expires_at = null;
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Numéro de téléphone vérifié avec succès.',
            'user'    => $this->userResource($user),
            'token'   => $token,
        ]);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $user = User::where('phone', $data['phone'])->first();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        if ($user->is_active) {
            return response()->json(['message' => 'Ce compte est déjà activé.'], 400);
        }

        // 6 chiffres via un générateur cryptographique (rand() est prévisible)
        $otp = (string) random_int(100000, 999999);
        $user->otp_code = $otp;
        $user->otp_expires_at = now()->addMinutes(10);
        $user->save();

        // TODO: envoyer $otp par SMS. Ne jamais l'écrire dans les logs.
        \Log::info("OTP régénéré pour l'utilisateur #{$user->id}");

        return response()->json([
            'message' => 'Un nouveau code OTP a été envoyé par SMS.',
        ]);
    }

    // ————————————————————————————————————————————————————————————————————————————————————————————————————
    // POST /api/v1/auth/logout
    // ————————————————————————————————————————————————————————————————————————————————————————————————————
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnecté avec succès.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // GET /api/v1/auth/me
    // ─────────────────────────────────────────────────────────────────
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('owner');

        return response()->json([
            'user' => $this->userResource($user),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // PUT /api/v1/auth/profile
    // ─────────────────────────────────────────────────────────────────
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'                     => ['sometimes', 'string', 'max:100'],
            'phone'                    => ['sometimes', 'nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'avatar'                   => ['sometimes', 'nullable', 'string'],
            'avatar_file'              => ['sometimes', 'nullable', 'image', 'max:5120'],
            'current_password'         => ['required_with:new_password', 'string'],
            'new_password'             => ['nullable', 'confirmed', 'string', 'min:8'],
        ]);

        // Vérification mot de passe actuel si changement demandé
        if (!empty($data['new_password'])) {
            if (!Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Le mot de passe actuel est incorrect.'],
                ]);
            }
            $user->password = Hash::make($data['new_password']);
            $user->must_change_password = false;
        }

        if (isset($data['name']))  $user->name  = $data['name'];
        if (array_key_exists('phone', $data)) $user->phone = $data['phone'];

        if ($request->hasFile('avatar_file')) {
            $file = $request->file('avatar_file');
            $path = $file->store('avatars', 'public');
            $user->avatar = \App\Models\Field::uploadedFileToDataUri($file, 500, 500, 88) ?: ('/storage/' . $path);
        } elseif (array_key_exists('avatar', $data)) {
            $rawAvatar = $data['avatar'];
            if ($rawAvatar && str_starts_with($rawAvatar, 'data:image')) {
                @list($type, $imgData) = explode(';', $rawAvatar);
                @list(, $imgData) = explode(',', $imgData);
                if ($imgData) {
                    $decoded = base64_decode($imgData);
                    $ext = 'jpg';
                    if (str_contains($type, 'png')) $ext = 'png';
                    elseif (str_contains($type, 'webp')) $ext = 'webp';
                    $filename = 'avatars/avatar_' . $user->id . '_' . time() . '.' . $ext;
                    Storage::disk('public')->put($filename, $decoded);
                }
                // Conserver directement le data URI en base (LONGTEXT) pour survivre à tout redéploiement
                $user->avatar = $rawAvatar;
            } else {
                $user->avatar = $rawAvatar;
            }
        }

        $user->save();

        return response()->json([
            'message' => 'Profil mis à jour avec succès.',
            'user'    => $this->userResource($user->fresh('owner')),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Helper : formater la réponse utilisateur
    // ─────────────────────────────────────────────────────────────────
    private function userResource(User $user): array
    {
        $avatar = $user->avatar ? \App\Models\Field::resolveImageUrl($user->avatar) : null;
        if ($user->avatar && $avatar === null) {
            $user->avatar = null;
            $user->saveQuietly();
        }

        $data = [
            'id'                   => $user->id,
            'name'                 => $user->name,
            'email'                => $user->email,
            'phone'                => $user->phone,
            'role'                 => $user->role,
            'avatar'               => $avatar,
            'is_active'            => $user->is_active,
            'must_change_password' => (bool) $user->must_change_password,
            'created_at'           => $user->created_at,
        ];

        $owner = $user->getOwner();
        if ($owner) {
            $collab = \App\Models\OwnerCollaborator::where('user_id', $user->id)->where('is_active', true)->first();
            $isPrimary = $user->owner !== null;

            $perms = ($user->isAdmin() || $isPrimary)
                ? array_column(\App\Models\OwnerCollaborator::availablePermissions(), 'key')
                : ($collab ? $collab->getEffectivePermissions() : []);

            $roleKey = $isPrimary ? 'owner_master' : ($collab?->role ?? 'collaborator');
            $roleLabels = [
                'owner_master' => 'Gérant Propriétaire (Principal)',
                'manager'      => 'Administrateur Complexe',
                'receptionist' => 'Accueil / Réceptionniste',
                'field_staff'  => 'Responsable Terrain',
                'accountant'   => 'Comptable / Gestionnaire',
                'custom'       => 'Rôle Sur-Mesure',
            ];
            $roleLabel = $roleLabels[$roleKey] ?? null;
            if (!$roleLabel && $owner) {
                foreach ($owner->getCustomRoles() as $cr) {
                    if (($cr['id'] ?? '') === $roleKey) {
                        $roleLabel = $cr['name'];
                        break;
                    }
                }
            }
            if (!$roleLabel) {
                $roleLabel = ucfirst(str_replace('_', ' ', $roleKey));
            }

            $rawSettings = $owner->getRawSettings();

            $data['owner'] = [
                'id'            => $owner->id,
                'business_name' => $owner->business_name,
                'is_verified'   => $owner->isVerified(),
                'is_primary'    => $isPrimary,
                'role'          => $roleKey,
                'role_label'    => $roleLabel,
                'permissions'   => $perms,
                'branding'      => $rawSettings['branding'] ?? null,
            ];
        }

        return $data;
    }
    public function updateFcmToken(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => ['required', 'string'],
        ]);

        $request->user()->update([
            'fcm_token' => $request->fcm_token,
        ]);

        return response()->json(['message' => 'FCM Token mis à jour avec succès.']);
    }

    public function getAdminContact(): JsonResponse
    {
        $admin = User::where('role', 'admin')->first();

        return response()->json([
            'name'  => $admin ? $admin->name : 'Super Administrateur myTerrain',
            'email' => $admin && $admin->email ? $admin->email : 'admin@myterrain.sn',
            'phone' => $admin && $admin->phone ? $admin->phone : '+221770000000',
        ]);
    }
}
