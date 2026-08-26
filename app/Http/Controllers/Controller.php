<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Auth\Muser;

abstract class Controller
{
    /**
     * Resolusi nama perusahaan (cperusahaan) secara otomatis dari Request / User
     */
    protected function resolveCompany(Request $request): ?string
    {
        $userId = $request->input('user_id')
            ?? $request->query('user_id')
            ?? $request->input('auditor_id')
            ?? $request->query('auditor_id')
            ?? $request->input('owner_id')
            ?? $request->query('owner_id')
            ?? $request->input('id');

        if ($userId) {
            $user = Muser::where('nid', $userId)->whereNull('dnonactive')->first();
            if ($user) {
                return $user->cperusahaan;
            }
        }

        if (auth()->check()) {
            return auth()->user()->cperusahaan;
        }

        $cperusahaan = $request->input('cperusahaan')
            ?? $request->query('cperusahaan')
            ?? $request->input('company')
            ?? $request->query('company');

        return $cperusahaan ? trim($cperusahaan) : null;
    }

    /**
     * Resolusi objek user (Muser) secara otomatis dari Request / Auth
     */
    protected function resolveUser(Request $request): ?Muser
    {
        $userId = $request->input('user_id')
            ?? $request->query('user_id')
            ?? $request->input('auditor_id')
            ?? $request->query('auditor_id')
            ?? $request->input('owner_id')
            ?? $request->query('owner_id')
            ?? $request->input('id');

        if ($userId) {
            $user = Muser::where('nid', $userId)->whereNull('dnonactive')->first();
            if ($user) {
                return $user;
            }
        }

        if (auth()->check()) {
            return auth()->user();
        }

        return null;
    }

    /**
     * Memastikan request berasal dari user dengan role Admin atau Owner
     */
    protected function authorizeAdmin(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $user = $this->resolveUser($request);

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak.'
            ], 403);
        }

        return null;
    }
}
