<?php

namespace Paymenter\Extensions\Servers\PterodactylAdvanced;

use App\Attributes\ExtensionMeta;
use App\Models\Service;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Paymenter\Extensions\Servers\Pterodactyl\Pterodactyl;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Pterodactyl server extension with mounts, single sign-on and managed accounts.
 *
 * Extends the built-in Pterodactyl extension shipped with Paymenter and adds three
 * things it does not cover:
 *
 *   - mounts selected per product and attached once the server is created;
 *   - a "Go to server" button that opens the customer's panel session directly;
 *   - optional managed accounts, created with a password that is never disclosed.
 *
 * None of these exist in the Pterodactyl application API, they require the
 * chredeur/pterodactyl-api-addon package installed on the panel.
 *
 * @link https://github.com/chredeur/pterodactyl-api-addon
 */
#[ExtensionMeta(
    name: 'Pterodactyl Advanced',
    description: 'Pterodactyl server extension with automatic mount attachment, single sign-on and managed accounts. Requires chredeur/pterodactyl-api-addon on the panel.',
    version: '2.4.1',
    author: 'chredeur',
    url: 'https://github.com/chredeur/Paymenter-Extensions',
)]
class PterodactylAdvanced extends Pterodactyl
{
    /**
     * Session key carrying a freshly generated SFTP password across the redirect.
     */
    private const SFTP_PASSWORD_KEY = 'pterodactyl_advanced.sftp_password';

    /**
     * Registers the single sign-on redirect route.
     *
     * Called from AppServiceProvider on every request, hence the guard.
     */
    public function boot()
    {
        if (!Route::has('extensions.servers.pterodactyladvanced.sso')) {
            require __DIR__ . '/routes.php';
        }

        View::addNamespace('pterodactyladvanced', __DIR__ . '/resources/views');
        Lang::addNamespace('pterodactyladvanced', __DIR__ . '/resources/lang');
    }

    /**
     * Adds the managed accounts switch to the server configuration.
     */
    public function getConfig($values = []): array
    {
        $config = parent::getConfig($values);

        $config[] = [
            'name' => 'managed_accounts',
            'label' => 'Managed accounts',
            'type' => 'checkbox',
            'description' => 'Create panel accounts with a random password that is never disclosed, so customers reach the panel only through the Go to server button. Leaves them without SFTP password access until they register an SSH key on their account.',
        ];

        return $config;
    }

    /**
     * Injects a password on account creation when managed accounts are enabled.
     *
     * UserCreationService only generates a password reset token, and only sends the
     * "Setup Your Account" link, when no password is supplied. Providing one turns the
     * email into a plain notice and leaves the customer with no password to manage.
     *
     * Done here rather than by overriding createServer, which would mean duplicating the
     * whole provisioning routine to change a single call.
     */
    public function request($url, $method = 'get', $data = []): array
    {
        $creatingUser = strtolower($method) === 'post'
            && rtrim($url, '/') === '/api/application/users'
            && !isset($data['password']);

        if ($creatingUser && filter_var($this->config('managed_accounts'), FILTER_VALIDATE_BOOLEAN)) {
            $data['password'] = Str::password(32);
        }

        return parent::request($url, $method, $data);
    }

    /**
     * Adds the mount field to the product configuration.
     */
    public function getProductConfig($values = []): array
    {
        $config = parent::getProductConfig($values);

        // The mount list depends on the selected egg and node, so both have to refresh
        // the form when they change. The parent only marks nest_id and port_array live.
        foreach ($config as &$field) {
            if (in_array($field['name'] ?? null, ['egg_id', 'node'], true)) {
                $field['live'] = true;
            }
        }
        unset($field);

        $mounts = $this->fetchMounts($values);

        $config[] = [
            'name' => 'mounts',
            'label' => 'Mounts',
            'type' => 'select',
            'options' => $mounts['options'],
            'multiple' => true,
            'database_type' => 'array',
            'required' => false,
            'description' => $mounts['description'],
        ];

        return $config;
    }

    /**
     * Creates the server, then attaches the configured mounts.
     */
    public function createServer(Service $service, $settings, $properties)
    {
        $result = parent::createServer($service, $settings, $properties);

        $mounts = $this->normaliseMountIds($settings['mounts'] ?? []);

        if (empty($mounts) || !isset($result['server'])) {
            return $result;
        }

        // The server already exists at this point, so a failure here must not abort the
        // provisioning: that would leave an orphaned server behind on the panel. Log the
        // failure and let the service go through, the mounts can be attached afterwards.
        try {
            $this->attachMounts($result['server'], $mounts);
        } catch (Throwable $e) {
            Log::error('[PterodactylAdvanced] Failed to attach mounts: ' . $e->getMessage(), [
                'service_id' => $service->id,
                'server_id' => $result['server'],
                'mounts' => $mounts,
            ]);

            $this->notifyStaff(
                'Pterodactyl mounts were not attached',
                'Service #' . $service->id . ' was created but its mounts could not be attached. ' . $e->getMessage()
            );
        }

        return $result;
    }

    /**
     * Replaces the panel link with one that signs the customer in on the way.
     *
     * The token is minted when the button is clicked rather than when the page renders,
     * so it cannot go stale while the page sits open, and it never appears in the HTML.
     */
    public function getActions(Service $service)
    {
        return [
            [
                'type' => 'button',
                'label' => __('pterodactyladvanced::messages.actions.go_to_server'),
                'url' => route('extensions.servers.pterodactyladvanced.sso', ['service' => $service->id]),
                // New tab, so the customer keeps the billing page open behind them. The
                // theme adds rel="noopener noreferrer" on its own for _blank.
                'target' => '_blank',
            ],
            [
                'type' => 'view',
                'name' => 'sftp',
                'label' => __('pterodactyladvanced::messages.sftp.title'),
            ],
        ];
    }

    /**
     * Renders the SFTP panel of the service page.
     *
     * The plain label and value pairs offered by the theme are too cramped for
     * credentials, so this ships its own markup with copy buttons.
     *
     * The password comes from the flash set by resetSftpPassword just before redirecting
     * back here. It is shown once and stored nowhere: the panel only keeps its hash.
     */
    public function getView(Service $service, $settings, $properties, $name)
    {
        if ($name !== 'sftp') {
            return '';
        }

        return view('pterodactyladvanced::sftp', [
            'service' => $service,
            'sftp' => $this->sftpDetails($service),
            'password' => session(self::SFTP_PASSWORD_KEY),
        ])->render();
    }

    /**
     * Sets a new SFTP password on the customer's panel account and shows it once.
     *
     * A password cannot be read back, the panel only stores its hash, so the only thing
     * this can offer is a replacement. Any SFTP client still holding the previous one
     * stops working, which is why the view asks for confirmation first.
     *
     * Reached over POST from the extension route, never from a link.
     */
    public function resetSftpPassword(Service $service)
    {
        abort_if($service->user_id !== Auth::id(), 403);

        try {
            $server = $this->request('/api/application/servers/external/' . $service->id);
            $user = $this->request('/api/application/users/' . $server['attributes']['user']);

            // The panel account behind a service is normally a plain customer account, but
            // nothing guarantees it: a server created by hand, or handed over afterwards,
            // can belong to an administrator. A reset would hand that account's password
            // to the customer, which is an interactive administrator session rather than a
            // file transfer. Refused for the same reason the addon refuses these accounts
            // a single sign-on link. The view hides the button when it already knows, so
            // reaching this point means a request built by hand.
            if ($user['attributes']['root_admin'] ?? false) {
                Log::warning('[PterodactylAdvanced] Refused an SFTP password reset on an administrator account.', [
                    'service_id' => $service->id,
                    'panel_user_id' => $user['attributes']['id'],
                ]);

                $this->notifyStaff(
                    'SFTP password reset refused',
                    'Service #' . $service->id . ' is attached to the administrator panel account '
                        . $user['attributes']['username'] . '. The reset was refused, as it would have '
                        . 'disclosed an administrator password to the customer.'
                );

                abort(403);
            }

            $password = Str::random(24);

            // email, username, first_name and last_name stay "required" on update, so the
            // current values are sent back untouched alongside the new password.
            $this->request('/api/application/users/' . $user['attributes']['id'], 'patch', [
                'email' => $user['attributes']['email'],
                'username' => $user['attributes']['username'],
                'first_name' => $user['attributes']['first_name'],
                'last_name' => $user['attributes']['last_name'],
                'password' => $password,
            ]);

            session()->flash(self::SFTP_PASSWORD_KEY, $password);
        } catch (HttpException $e) {
            // The refusal above, which must reach the customer as a 403 rather than be
            // reported as a panel failure by the handler below.
            throw $e;
        } catch (Throwable $e) {
            Log::error('[PterodactylAdvanced] Failed to reset the SFTP password: ' . $e->getMessage(), [
                'service_id' => $service->id,
            ]);

            $this->notifyStaff(
                'SFTP password reset failed',
                'Service #' . $service->id . '. ' . $e->getMessage()
            );
        }

        return redirect()->route('services.show', $service);
    }

    /**
     * Returns the SFTP address and username of the service, or null if the panel cannot
     * be reached. The username format is the one parsed by the panel: account username,
     * a dot, then the short server identifier.
     *
     * root_admin comes along so the view can drop the reset button on the accounts
     * resetSftpPassword refuses, rather than offering a button that answers 403.
     *
     * @return array{host: string, port: int, username: string, root_admin: bool}|null
     */
    protected function sftpDetails(Service $service): ?array
    {
        try {
            $server = $this->request('/api/application/servers/external/' . $service->id);
            $node = $this->request('/api/application/nodes/' . $server['attributes']['node']);
            $user = $this->request('/api/application/users/' . $server['attributes']['user']);

            return [
                'host' => $node['attributes']['fqdn'],
                'port' => $node['attributes']['daemon_sftp'],
                'username' => $user['attributes']['username'] . '.' . $server['attributes']['identifier'],
                'root_admin' => (bool) ($user['attributes']['root_admin'] ?? false),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Asks the panel for a single sign-on link and sends the customer to it.
     *
     * Falls back to the plain panel URL whenever a link cannot be obtained: the addon is
     * missing, the panel is unreachable, or the account is not eligible because it has
     * two-factor authentication enabled. The customer then signs in normally, which is
     * the intended behaviour rather than an error.
     */
    public function ssoRedirect(Service $service)
    {
        abort_if($service->user_id !== Auth::id(), 403);

        $host = rtrim($this->config('host'), '/');
        $identifier = null;

        try {
            // getServer() is private on the parent, so the lookup is repeated here.
            $server = $this->request('/api/application/servers/external/' . $service->id);
            $identifier = $server['attributes']['identifier'] ?? null;

            $sso = $this->request('/api/application/users/' . $server['attributes']['user'] . '/sso', 'post', [
                'redirect' => '/server/' . $identifier,
            ]);

            return redirect()->away($sso['attributes']['url']);
        } catch (Throwable $e) {
            // Logged as an error rather than info: the customer still reaches the panel,
            // but the feature is not doing its job and that must not go unnoticed.
            Log::error('[PterodactylAdvanced] Single sign-on unavailable, falling back to the panel: ' . $e->getMessage(), [
                'service_id' => $service->id,
            ]);

            $this->notifyStaff(
                'Pterodactyl single sign-on failed',
                'Service #' . $service->id . ' fell back to a plain panel link. ' . $e->getMessage()
            );

            return redirect()->away($identifier ? $host . '/server/' . $identifier : $host);
        }
    }

    /**
     * Raises a notification for staff only.
     *
     * Filament renders notifications in the admin panel, and the client theme does not
     * render them at all, so a customer is never shown one. The role check mirrors how
     * Paymenter itself decides admin panel access, in User::canAccessPanel().
     *
     * Nothing is shown when the failure happens outside a request, during queued
     * provisioning for instance. The log entry remains the reliable trace.
     */
    protected function notifyStaff(string $title, string $body): void
    {
        if (is_null(Auth::user()?->role)) {
            return;
        }

        Notification::make()
            ->danger()
            ->persistent()
            ->title($title)
            ->body($body)
            ->send();
    }

    /**
     * Attaches every mount in one call. The endpoint is all or nothing: if a single
     * mount is not allowed for the node and the egg of the server, none are attached.
     */
    protected function attachMounts($serverId, array $mountIds): void
    {
        $this->request('/api/application/servers/' . $serverId . '/mounts', 'post', [
            'mounts' => $mountIds,
        ]);
    }

    /**
     * Builds the options of the mount field.
     *
     * The panel is asked for the mounts allowed on the selected egg and node, so the list
     * only offers mounts that can actually be attached. Both filters are optional: until
     * an egg is picked, or when the product deploys by location instead of a fixed node,
     * the list is wider and an ineligible mount is rejected at creation time.
     *
     * Paymenter passes null while no product value has been entered yet, so the argument
     * is not typed and is normalised here.
     *
     * @return array{options: array<int, string>, description: string}
     */
    protected function fetchMounts($values): array
    {
        $values = is_array($values) ? $values : [];
        $query = [];

        if (!empty($values['egg_id'])) {
            $query['egg_id'] = $values['egg_id'];
        }

        if (!empty($values['node'])) {
            $query['node_id'] = $values['node'];
        }

        try {
            $response = $this->request('/api/application/mounts', 'get', $query);
        } catch (Throwable $e) {
            return [
                'options' => [],
                'description' => 'Could not load the mounts from the panel: ' . $e->getMessage()
                    . ' Check that chredeur/pterodactyl-api-addon is installed and that the API key can read servers.',
            ];
        }

        $options = [];
        foreach ($response['data'] ?? [] as $mount) {
            $mount = $mount['attributes'];
            $options[$mount['id']] = $mount['name'] . ' (' . $mount['target'] . ')';
        }

        if (empty($options)) {
            return [
                'options' => [],
                'description' => 'No mount is available for this egg and node. Create the mount in the panel under Admin > Mounts, then assign it to the node and the egg used by this product.',
            ];
        }

        return [
            'options' => $options,
            'description' => 'Mounts attached once the server is created. Only mounts allowed on the selected egg and node are listed.',
        ];
    }

    /**
     * Accepts the array stored by Paymenter, or a comma separated string, and returns a
     * clean list of unique positive integers.
     *
     * @return int[]
     */
    protected function normaliseMountIds($mounts): array
    {
        if (is_string($mounts)) {
            $mounts = explode(',', $mounts);
        }

        if (!is_array($mounts)) {
            return [];
        }

        $ids = array_map(fn ($id) => (int) trim((string) $id), $mounts);

        return array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
    }
}
