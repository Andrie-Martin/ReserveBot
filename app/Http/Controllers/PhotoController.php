<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Photos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Phase 9: shows a profile photo. Only the account itself and the lab staff and admins of its
 * department can see it (staff use it to check who picks up a key). Everyone else gets "not found".
 */
class PhotoController extends Controller
{
    public function show(Request $request, User $user): BinaryFileResponse
    {
        $viewer = $request->user();
        $allowed = $viewer->is($user) || ($viewer->role !== 'rep' && $viewer->department_id === $user->department_id);
        abort_unless($allowed && $user->photo_path && Storage::disk(Photos::DISK)->exists($user->photo_path), 404);

        // Browsers may keep it a week (its address changes whenever the photo does), but shared caches may not
        $response = response()->file(Storage::disk(Photos::DISK)->path($user->photo_path));
        $response->setPrivate();
        $response->setMaxAge(604800);

        return $response;
    }
}
