<?php

use App\Support\TrustedProxies;

/*
|--------------------------------------------------------------------------
| Trusted proxies
|--------------------------------------------------------------------------
|
| Empty by default: no proxy is trusted, which is right while the site runs
| on your own PC (http://localhost:8000).
|
| When the site goes online behind a tunnel or proxy (ngrok, a campus
| reverse proxy), every visitor reaches Laravel from that proxy's address.
| Put that address in .env so Laravel reads each visitor's real address
| from the proxy instead, e.g.
|
|     TRUSTED_PROXIES=127.0.0.1            ngrok on the same PC (XAMPP / php artisan serve)
|     TRUSTED_PROXIES=172.18.0.1           ngrok on the PC, site in Docker (the Docker network's gateway)
|
| Several addresses or ranges: separate them with commas. "*" is refused on
| purpose (see App\Support\TrustedProxies). Laravel's own TrustProxies
| middleware reads this value; README, "Going online", says how to find the
| right address.
|
*/

return [
    'proxies' => TrustedProxies::parse(env('TRUSTED_PROXIES')),
];
