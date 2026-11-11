<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every field on this site carries one value, except the few named in LISTS. A hand-edited address such as
 * /today?date[]=1 sends a list where one value is expected, which used to end in a server error (500) when the
 * value was read as text. Such a field is now treated as not sent, so the page falls back to its default or the
 * form's own "required" message. Uploaded files are not touched.
 */
class SingleValueInputs
{
    /** Fields that really are lists: equipment quantities on the booking form, open days on the rules form */
    private const LISTS = ['qty', 'open_days'];

    public function handle(Request $request, Closure $next): Response
    {
        foreach ([$request->query, $request->request] as $bag) {
            foreach ($bag->all() as $key => $value) {
                if (is_array($value) && ! in_array($key, self::LISTS, true)) {
                    $bag->remove($key);
                }
            }
        }

        return $next($request);
    }
}
