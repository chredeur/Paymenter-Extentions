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
 * level "Mounts" field. After a server is created, the selected mounts are attached to it
 * through the application API.
 *
 * Attaching and listing mounts is not part of the Pterodactyl application API, it
 * requires the chredeur/pterodactyl-api-addon package installed on the panel.
 *
 * @link https://github.com/chredeur/pterodactyl-api-addon
 */
#[ExtensionMeta(
    name: 'Pterodactyl (Mounts)',
    description: 'Pterodactyl server extension that also attaches mounts to a server on creation. Requires chredeur/pterodactyl-api-addon on the panel.',
    version: '1.1.0',
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
     * Builds the options of the mount field.
     *
     * The panel is asked for the mounts allowed on the selected egg and node, so the list
     * only offers mounts that can actually be attached. Both filters are optional: until
     * an egg is picked, or when the product deploys by location instead of a fixed node,
     * the list is wider and an ineligible mount is rejected at creation time.
     *
     * @return array{options: array<int, string>, description: string}
     */
    protected function fetchMounts(array $values): array
    {
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
