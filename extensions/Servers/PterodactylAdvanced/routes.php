<?php

use App\Helpers\ExtensionHelper;
use App\Models\Service;
use Illuminate\Support\Facades\Route;

/*
| Resolved through ExtensionHelper rather than pointing straight at the class so the
| instance is built with the settings of the server entry it belongs to, exactly like
| every other call Paymenter makes into the extension.
*/
$extension = function (Service $service) {
    $server = $service->product->server;

    abort_if(!$server, 404);

    return ExtensionHelper::getExtension('server', $server->extension, $server->settings);
};

Route::middleware(['web', 'auth'])->group(function () use ($extension) {
    Route::get('/extensions/pterodactyladvanced/sso/{service}', fn (Service $service) => $extension($service)->ssoRedirect($service))
        ->name('extensions.servers.pterodactyladvanced.sso');

    // POST so a password can never be replaced by a link, a prefetch or a page reload.
    Route::post('/extensions/pterodactyladvanced/sftp-password/{service}', fn (Service $service) => $extension($service)->resetSftpPassword($service))
        ->name('extensions.servers.pterodactyladvanced.sftp-password');
});
