<?php

use App\Helpers\ExtensionHelper;
use App\Models\Service;
use Illuminate\Support\Facades\Route;

/*
| Resolved through ExtensionHelper rather than pointing straight at the class so the
| instance is built with the settings of the server entry it belongs to, exactly like
| every other call Paymenter makes into the extension.
*/
Route::get('/extensions/pterodactyladvanced/sso/{service}', function (Service $service) {
    $server = $service->product->server;

    abort_if(!$server, 404);

    return ExtensionHelper::getExtension('server', $server->extension, $server->settings)
        ->ssoRedirect($service);
})
    ->middleware(['web', 'auth'])
    ->name('extensions.servers.pterodactyladvanced.sso');
