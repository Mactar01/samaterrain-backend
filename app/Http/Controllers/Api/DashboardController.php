<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Reservation;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user->isOwner()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $ownerId = $user->owner->id;

        // Base query for confirmed/completed reservations belonging to this owner
        $baseQuery = Reservation::query()
            ->join('time_slots', 'reservations.time_slot_id', '=', 'time_slots.id')
            ->join('fields', 'time_slots.field_id', '=', 'fields.id')
            ->where('fields.owner_id', $ownerId)
            ->whereIn('reservations.status', ['confirmed', 'completed']);

        // 1. Total Reservations
        $totalReservations = (clone $baseQuery)->count();

        // 2. Total Revenue
        $totalRevenue = (clone $baseQuery)->sum('reservations.total_price');

        // 3. Revenue by month
        $driver = DB::connection()->getDriverName();
        $monthlyQuery = clone $baseQuery;
        
        if ($driver === 'sqlite') {
            $monthlyRevenue = $monthlyQuery
                ->selectRaw("strftime('%Y-%m', time_slots.date) as month, SUM(reservations.total_price) as revenue")
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(6)
                ->get();
        } else {
            $monthlyRevenue = $monthlyQuery
                ->selectRaw("DATE_FORMAT(time_slots.date, '%Y-%m') as month, SUM(reservations.total_price) as revenue")
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(6)
                ->get();
        }

        // 4. Revenue by field
        $revenueByField = (clone $baseQuery)
            ->selectRaw("fields.id as field_id, fields.name, SUM(reservations.total_price) as revenue")
            ->groupBy('fields.id', 'fields.name')
            ->get();

        // 5. Revenue by payment method
        $revenueByMethod = DB::table('payments')
            ->join('reservations', 'payments.reservation_id', '=', 'reservations.id')
            ->join('time_slots', 'reservations.time_slot_id', '=', 'time_slots.id')
            ->join('fields', 'time_slots.field_id', '=', 'fields.id')
            ->where('fields.owner_id', $ownerId)
            ->where('payments.status', 'completed')
            ->selectRaw("payments.method, SUM(payments.amount) as total")
            ->groupBy('payments.method')
            ->get();

        // 6. Today's reservations (Matches happening today)
        $todayReservations = (clone $baseQuery)
            ->whereDate('time_slots.date', now()->toDateString())
            ->count();

        // 7. Détail des locations (créneau réservé, joueur, prix), les plus récentes d'abord
        $rentals = (clone $baseQuery)
            ->join('users', 'reservations.user_id', '=', 'users.id')
            ->select([
                'reservations.id',
                'fields.id as field_id',
                'fields.name as field_name',
                'time_slots.date',
                'time_slots.start_time',
                'time_slots.end_time',
                'reservations.total_price',
                'reservations.status',
                // Réservation manuelle : le client est noté sur la réservation (guest_*)
                DB::raw('COALESCE(reservations.guest_name, users.name) as player_name'),
                DB::raw('COALESCE(reservations.guest_phone, users.phone) as player_phone'),
            ])
            ->orderBy('time_slots.date', 'desc')
            ->orderBy('time_slots.start_time', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'rentals'            => $rentals,
            'total_reservations' => $totalReservations,
            'today_reservations' => $todayReservations,
            'total_revenue'      => $totalRevenue,
            'monthly_revenue'    => $monthlyRevenue,
            'revenue_by_field'   => $revenueByField,
            'revenue_by_method'  => $revenueByMethod
        ]);
    }
}
