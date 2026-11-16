<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Photos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Phase 9: shows a profile photo. Only the account itself and the lab staff and admins of its
 * department can see it (staff use it to check who picks up a key), plus the College admin for the
 * department Admins it looks after. Everyone else gets "not found".
 */
class PhotoController extends Controller
{
    public function show(Request $request, User $user): BinaryFileResponse
    {
        $viewer = $request->user();
        $allowed = $viewer->is($user)
            || (in_array($viewer->role, ['lab_staff', 'admin'], true) && $viewer->department_id === $user->department_id)
            || ($viewer->isCollegeAdmin() && $user->role === 'admin');
        abort_unless($allowed && $user->photo_path && Storage::disk(Photos::DISK)->exists($user->photo_path), 404);

        // Not kept by the browser at all: a kept copy would still show after logging out, or to the next account
        // that logs in on the same browser, without asking the site who may see it (QA ACC-13).
        $response = response()->file(Storage::disk(Photos::DISK)->path($user->photo_path));
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
