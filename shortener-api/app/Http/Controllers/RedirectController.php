<?php

namespace App\Http\Controllers;

use App\Repositories\UrlRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class RedirectController extends Controller
{
    public function go(string $hash, UrlRepository $repo)
    {
        $row = $repo->findForRedirect($hash);
        if (!$row) {
            return response('Not found', 404);
        }

        if (!empty($row['expires_at']) && Carbon::parse($row['expires_at'])->isPast()) {
            // Passive cleanup 
            $repo->deleteIfExpired($hash);
            return response('Expired', 410);
        }

        // Analytics counter (very cheap)
        $repo->incrementClicks($hash);

        // Redirect (301 for permanent; many use 302/307 to keep flexibility)
        return redirect()->away($row['original_url'], 301);
    }
}
