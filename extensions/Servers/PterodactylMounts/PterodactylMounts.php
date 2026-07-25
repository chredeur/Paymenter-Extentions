<?php

namespace Paymenter\Extensions\Servers\PterodactylMounts;

use App\Attributes\ExtensionMeta;
use App\Models\Service;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Servers\Pterodactyl\Pterodactyl;
use Throwable;

/**
 * Pterodactyl server extension with mount support.
 *
 * Extends the built-in Pterodactyl extension shipped with Paymenter and adds a product
 * level "Mounts" field. After a server is created, the listed mounts are attached to it
 * through the application API.
 *
 * Attaching mounts is not part of the Pterodactyl application API, it requires the
 * chredeur/pterodactyl-api-addon package installed on the panel.
 *
 * @link https://github.com/chredeur/pterodactyl-api-addon
 */
#[ExtensionMeta(
    name: 'Pterodactyl (Mounts)',
    description: 'Pterodactyl server extension that also attaches mounts to a server on creation. Requires chredeur/pterodactyl-api-addon on the panel.',
    version: '1.0.0',
    author: 'chredeur',
    url: 'https://github.com/chredeur/Paymenter-Extentions',
)]
class PterodactylMounts extends Pterodactyl
{
    /**
     * Adds the mount field to the product configuration.
     */
    public function getProductConfig($values = []): array
    {
        $config = parent::getProductConfig($values);

        $config[] = [
            'name' => 'mounts',
            'label' => 'Mounts',
            'type' => 'tags',
            'description' => 'Numeric mount IDs to attach once the server is created. Each mount must be assigned to the node and the egg of this product in the panel. Leave empty to disable.',
            'database_type' => 'array',
            'required' => false,
            'nested_validation' => 'numeric',
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
            Log::error('[PterodactylMounts] Failed to attach mounts: ' . $e->getMessage(), [
                'service_id' => $service->id,
                'server_id' => $result['server'],
                'mounts' => $mounts,
            ]);
        }

        return $result;
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
     * Accepts the tags array as stored by Paymenter, or a comma separated string, and
     * returns a clean list of unique positive integers.
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
