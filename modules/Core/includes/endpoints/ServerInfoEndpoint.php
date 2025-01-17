<?php

class ServerInfoEndpoint extends KeyAuthEndpoint {

    public function __construct() {
        $this->_route = 'minecraft/server-info';
        $this->_module = 'Core';
        $this->_description = 'Update the Minecraft server information NamelessMC tracks';
        $this->_method = 'POST';
    }

    public function execute(Nameless2API $api): void {
        $api->validateParams($_POST, ['server-id', 'max-memory', 'free-memory', 'allocated-memory', 'tps']);
        if (!isset($_POST['players'])) {
            $api->throwError(6, $api->getLanguage()->get('api', 'invalid_post_contents'), 'players');
        }

        $serverId = $_POST['server-id'];
        // Ensure server exists
        $server_query = $api->getDb()->get('mc_servers', ['id', '=', $serverId]);

        if (!$server_query->count() || $server_query->first()->bedrock) {
            $api->throwError(27, $api->getLanguage()->get('api', 'invalid_server_id') . ' - ' . $serverId);
        }

        try {
            $api->getDb()->insert('query_results', [
                'server_id' => $_POST['server-id'],
                'queried_at' => date('U'),
                'players_online' => count($_POST['players']),
                'groups' => isset($_POST['groups']) ? json_encode($_POST['groups']) : '[]'
            ]);

            if (file_exists(ROOT_PATH . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . sha1('server_query_cache') . '.cache')) {
                $query_cache = file_get_contents(ROOT_PATH . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . sha1('server_query_cache') . '.cache');
                $query_cache = json_decode($query_cache);
                if (isset($query_cache->query_interval)) {
                    $query_interval = unserialize($query_cache->query_interval->data);
                } else {
                    $query_interval = 10;
                }

                $to_cache = [
                    'query_interval' => [
                        'time' => date('U'),
                        'expire' => 0,
                        'data' => serialize($query_interval)
                    ],
                    'last_query' => [
                        'time' => date('U'),
                        'expire' => 0,
                        'data' => serialize(date('U'))
                    ]
                ];

                // Store in cache file
                file_put_contents(ROOT_PATH . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . sha1('server_query_cache') . '.cache', json_encode($to_cache));
            }
        } catch (Exception $e) {
            $api->throwError(25, $api->getLanguage()->get('api', 'unable_to_update_server_info'), $e->getMessage(), 500);
        }

        $group_sync_log = [];

        try {
            foreach ($_POST['players'] as $uuid => $player) {
                $user = new User($uuid, 'uuid');
                $this->updateUsername($user, $player, $api);
                $log = $this->updateGroups($user, $player);
                if (count($log)) {
                    $group_sync_log[] = $log;
                }
                $this->updatePlaceholders($user, $player);
            }
        } catch (Exception $e) {
            $api->throwError(25, $api->getLanguage()->get('api', 'unable_to_update_server_info'), $e->getMessage(), 500);
        }

        $api->returnArray(array_merge(['message' => $api->getLanguage()->get('api', 'server_info_updated')], ['log' => $group_sync_log]));
    }

    private function updateUsername(User $user, array $player, Nameless2API $api): void
    {
        if (Util::getSetting($api->getDb(), 'username_sync')) {
            if (!$user->data() ||
                $player['name'] == $user->data()->username) {
                return;
            }

            // Update username
            if (!Util::getSetting($api->getDb(), 'displaynames', false)) {
                $user->update([
                        'username' => Output::getClean($player['name']),
                        'nickname' => Output::getClean($player['name'])
                    ],
                    $user->data()->id
                );
            } else {
                $user->update([
                        'username' => Output::getClean($player['name'])
                    ],
                    $user->data()->id
                );
            }
        }
    }

    private function updateGroups(User $user, array $player): array {
        if (!$user->exists()) {
            return [];
        }

        if (!$user->isValidated()) {
            return [];
        }

        $log = GroupSyncManager::getInstance()->broadcastChange(
            $user,
            MinecraftGroupSyncInjector::class,
            isset($player['groups']) ? array_map('strtolower', $player['groups']) : []
        );

        if (count($log)) {
            Log::getInstance()->log(Log::Action('mc_group_sync/role_set'), json_encode($log), $user->data()->id);
        }

        return $log;
    }

    private function updatePlaceholders(User $user, $player): void
    {
        if ($user->data()) {
            $user->savePlaceholders($_POST['server-id'], $player['placeholders']);
        }
    }

}
