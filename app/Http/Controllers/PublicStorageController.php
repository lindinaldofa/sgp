<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves files from the "public" disk directly through the app, as a
 * fallback for hosts where `php artisan storage:link` can't create the
 * public/storage symlink (both `symlink()` and `exec()` disabled via
 * `disable_functions`, common on locked-down shared hosting).
 *
 * When the symlink DOES exist, the web server resolves /storage/* as a
 * static file before the request ever reaches Laravel's router, so this
 * route never runs — it only kicks in when there's no physical file to
 * find, making it a transparent fallback rather than a second code path
 * to keep in sync.
 *
 * This must be registered explicitly in routes/web.php, ahead of the SPA
 * catch-all — Laravel also auto-registers an equivalent route for any disk
 * with `serve => true`, but only inside an `app()->booted()` callback that
 * runs after web.php's routes are already loaded, so it always loses to a
 * catch-all `{any}` route and never actually gets reached.
 */
class PublicStorageController extends Controller
{
    public function show(string $path): StreamedResponse
    {
        try {
            if (! Storage::disk('public')->exists($path)) {
                abort(404);
            }
        } catch (FilesystemException) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }
}
