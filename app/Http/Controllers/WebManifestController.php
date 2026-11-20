<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebManifestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $organisation = $request->user()?->organisations()
            ->where('organisations.status', 'active')
            ->whereKey($request->session()->get('organisation_id'))
            ->first(['organisations.id', 'organisations.workspace_branding']);
        $workspaceColour = $organisation?->workspace_branding['primary_color'] ?? null;
        $themeColour = is_string($workspaceColour) && preg_match('/^#[0-9a-fA-F]{6}$/', $workspaceColour)
            ? strtolower($workspaceColour)
            : '#ffffff';

        return response()->json([
            'name' => config('app.name'),
            'short_name' => config('app.name'),
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f8fafc',
            'theme_color' => $themeColour,
            'icons' => [
                ['src' => '/app-icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/app-icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'private, max-age=300']);
    }
}
