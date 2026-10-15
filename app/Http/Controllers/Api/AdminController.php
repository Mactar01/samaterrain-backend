<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Owner;
use App\Models\Field;
use App\Models\Reservation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Models\OwnerInvitation;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;

class AdminController extends Controller
{
    /**
     * Obtenir les statistiques du super admin.
     */
    public function getStats(): JsonResponse
    {
        return response()->json([
            'total_owners' => Owner::count(),
            'total_fields' => Field::count(),
            'total_players' => User::where('role', 'player')->count(),
            'total_reservations' => Reservation::count(),
        ]);
    }

    /**
     * Liste des partenaires loueurs.
     */
    public function getOwners(): JsonResponse
    {
        $owners = Owner::with('user')->get();
        return response()->json($owners);
    }

    /**
     * Crée un compte Loueur (Accessible uniquement par l'admin).
     */
    public function createOwner(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:100'],
            'email'                 => ['required', 'email', 'max:150', 'unique:users'],
            'phone'                 => ['nullable', 'string', 'max:20'],
            'password'              => ['required', 'confirmed', 'string', 'min:8'],
            'business_name'         => ['required', 'string', 'max:200']
        ], [
            'name.required' => 'Le nom est obligatoire.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.confirmed' => 'Les mots de passe ne correspondent pas.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'business_name.required' => 'Le nom du complexe est obligatoire.'
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'password'  => Hash::make($data['password']),
            'role'      => 'owner',
            'is_active' => true,
        ]);

        $owner = Owner::create([
            'user_id' => $user->id,
            'business_name' => $data['business_name'],
            'is_verified' => true
        ]);

        return response()->json([
            'message' => 'Compte loueur créé avec succès.',
            'user'    => $user->load('owner'),
        ], 201);
    }

    /**
     * Mettre à jour un partenaire.
     */
    public function updateOwner(Request $request, $id): JsonResponse
    {
        $owner = Owner::findOrFail($id);
        $user = $owner->user;

        $data = $request->validate([
            'name'          => ['sometimes', 'string', 'max:100'],
            'email'         => ['sometimes', 'email', 'max:150', 'unique:users,email,' . $user->id],
            'phone'         => ['nullable', 'string', 'max:20'],
            'business_name' => ['sometimes', 'string', 'max:200']
        ]);

        if (isset($data['name'])) $user->name = $data['name'];
        if (isset($data['email'])) $user->email = $data['email'];
        if (array_key_exists('phone', $data)) $user->phone = $data['phone'];
        $user->save();

        if (isset($data['business_name'])) {
            $owner->business_name = $data['business_name'];
            $owner->save();
        }

        return response()->json([
            'message' => 'Partenaire mis à jour avec succès.',
            'owner'   => $owner->load('user')
        ]);
    }

    /**
     * Activer/Désactiver un partenaire.
     */
    public function toggleOwnerStatus($id): JsonResponse
    {
        $owner = Owner::findOrFail($id);
        $user = $owner->user;
        
        $user->is_active = !$user->is_active;
        $user->save();

        $status = $user->is_active ? 'activé' : 'désactivé';

        return response()->json([
            'message' => "Le compte partenaire a été $status."
        ]);
    }

    /**
     * Supprimer un partenaire.
     */
    public function deleteOwner($id): JsonResponse
    {
        $owner = Owner::findOrFail($id);
        $user = $owner->user;
        
        $owner->delete();
        $user->delete();

        return response()->json([
            'message' => 'Partenaire supprimé avec succès.'
        ]);
    }

    public function getDetailedStats(): JsonResponse
    {
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        
        $reservationsByStatus = \App\Models\Reservation::select('status', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        if ($driver === 'sqlite') {
            $monthlyRevenue = \App\Models\Reservation::where('status', 'completed')
                ->selectRaw("strftime('%Y-%m', created_at) as month, SUM(total_price) as revenue")
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(6)
                ->get();
        } else {
            $monthlyRevenue = \App\Models\Reservation::where('status', 'completed')
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_price) as revenue")
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(6)
                ->get();
        }

        return response()->json([
            'reservations_by_status' => $reservationsByStatus,
            'monthly_revenue' => $monthlyRevenue
        ]);
    }

    public function getBilling(): JsonResponse
    {
        $globalRevenue = \App\Models\Reservation::whereIn('status', ['confirmed', 'completed'])->sum('total_price');
        $totalCommission = \App\Models\Reservation::whereIn('status', ['confirmed', 'completed'])->sum('commission');
        
        $pendingPayouts = $globalRevenue - $totalCommission;

        $recentTransactions = [];
        if (class_exists(\App\Models\Payment::class)) {
            $recentTransactions = \App\Models\Payment::with(['reservation.user', 'reservation.field.owner'])
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get();
        }

        return response()->json([
            'global_revenue' => (float) $globalRevenue,
            'total_commission' => (float) $totalCommission,
            'pending_payouts' => (float) $pendingPayouts,
            'recent_transactions' => $recentTransactions
        ]);
    }

    /**
     * Inviter un loueur par email — POST admin/owners/invite
     */
    public function inviteOwner(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'         => ['required', 'email', 'unique:users,email'],
            'business_name' => ['required', 'string', 'max:200'],
        ]);

        $token = Str::random(64);

        $invitation = OwnerInvitation::create([
            'email'           => $data['email'],
            'business_name'   => $data['business_name'],
            'invited_by_name' => $request->user()->name,
            'token'           => $token,
            'expires_at'      => now()->addDays(7),
        ]);

        $frontendUrl = config('app.frontend_url');
        $inviteLink  = $frontendUrl . '/register-owner?token=' . $token;

        try {
            Mail::to($data['email'])->send(new \App\Mail\OwnerInvitationMail($invitation, $inviteLink));
        } catch (\Exception $e) {
            // L'envoi d'email est optionnel — on continue même en cas d'échec
        }

        $whatsappLink = 'https://wa.me/?text=' . urlencode(
            'Bonjour, vous êtes invité à rejoindre myTerrain ! Cliquez sur ce lien pour créer votre compte loueur : ' . $inviteLink
        );

        return response()->json([
            'message'       => 'Invitation envoyée avec succès.',
            'invite_link'   => $inviteLink,
            'whatsapp_link' => $whatsappLink,
            'expires_at'    => $invitation->expires_at,
        ], 201);
    }

    /**
     * Valider un token d'invitation — GET public/owners/invite-check
     */
    public function validateInviteToken(Request $request): JsonResponse
    {
        $token = $request->query('token');

        $invitation = OwnerInvitation::where('token', $token)->first();

        if (!$invitation || !$invitation->isValid()) {
            return response()->json([
                'message' => "Ce lien d'invitation est invalide ou a expiré.",
            ], 404);
        }

        return response()->json([
            'email'           => $invitation->email,
            'business_name'   => $invitation->business_name,
            'invited_by_name' => $invitation->invited_by_name,
            'expires_at'      => $invitation->expires_at,
        ]);
    }

    /**
     * Créer un compte loueur depuis une invitation — POST public/owners/register-from-invite
     */
    public function registerFromInvitation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'         => ['required', 'string'],
            'name'          => ['required', 'string', 'max:100'],
            'phone'         => ['required', 'string', 'max:20'],
            'password'      => ['required', 'confirmed', 'string', 'min:8'],
            'business_name' => ['required', 'string', 'max:200'],
            'address'       => ['required', 'string', 'max:300'],
            'siret'         => ['required', 'string', 'max:50'],
        ]);

        $invitation = OwnerInvitation::where('token', $data['token'])->first();

        if (!$invitation || !$invitation->isValid()) {
            return response()->json([
                'message' => "Ce lien d'invitation est invalide ou a expiré.",
            ], 404);
        }

        if (User::where('email', $invitation->email)->exists()) {
            return response()->json([
                'message' => 'Un compte existe déjà avec cette adresse email.',
            ], 422);
        }

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $invitation->email,
            'phone'     => $data['phone'],
            'password'  => Hash::make($data['password']),
            'role'      => 'owner',
            'is_active' => true,
        ]);

        Owner::create([
            'user_id'       => $user->id,
            'business_name' => $data['business_name'],
            'address'       => $data['address'],
            'siret'         => $data['siret'],
            'is_verified'   => true,
        ]);

        $invitation->used_at = now();
        $invitation->save();

        $token = $user->createToken('owner-token')->plainTextToken;

        return response()->json([
            'message' => 'Compte loueur créé avec succès.',
            'user'    => $user->load('owner'),
            'token'   => $token,
        ], 201);
    }
}
