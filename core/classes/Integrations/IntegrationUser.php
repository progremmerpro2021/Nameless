<?php
/**
 * Represents a integration user
 *
 * @package NamelessMC\Integrations
 * @author Partydragen
 * @version 2.0.0-pr13
 * @license MIT
 */

class IntegrationUser {

    private DB $_db;
    private $_data;
    private IntegrationBase $_integration;

    public function __construct(IntegrationBase $integration, string $value = null, string $field = 'id') {
        $this->_db = DB::getInstance();
        $this->_integration = $integration;

        if (!$user) {
            $data = $this->_db->selectQuery('SELECT * FROM nl2_users_integrations WHERE ' . $field . ' = ? AND integration_id = ?;', [$value, $integration->data()->id]);
            if ($data->count()) {
                $this->_data = $data->first();
            }
        }
    }

    /**
     * Get the NamelessMC User that belong to this integration user
     *
     * @return User NamelessMC User that belong to this integration user
     */
    public function getUser(): User {
        return new User($this->data()->user_id);
    }

    /**
     * Get the integration user data.
     *
     * @return object This integration user data.
     */
    public function data(): ?object {
        return $this->_data;
    }

    /**
     * Does this integration user exist?
     *
     * @return bool Whether the user exists (has data) or not.
     */
    public function exists(): bool {
        return (!empty($this->_data));
    }

    /**
     * Get if this integration user is verified or not.
     *
     * @return bool Whether this integration user has been verified.
     */
    public function isVerified(): bool {
        return $this->data()->verified;
    }

    /**
     * Update integration user data in the database.
     *
     * @param array $fields Column names and values to update.
     * @throws Exception
     */
    public function update(array $fields = []): void {
        if (!$this->_db->update('users_integrations', $id, $fields)) {
            throw new RuntimeException('There was a problem updating integration user.');
        }
    }

    /**
     * Save a new user linked to a specific integration.
     *
     * @param User $user The user to link
     * @param string $identifier The id of the integration account
     * @param string $username The username of the integration account
     * @param bool $verified Verified the ownership of the integration account
     * @param string|null $code (optional) The verification code to verify the ownership
     *
     * @return bool
     */
    public function linkIntegration(User $user, string $identifier, string $username, bool $verified = false, string $code = null): void {
        $this->_db->createQuery(
            'INSERT INTO nl2_users_integrations (user_id, integration_id, identifier, username, verified, date, code) VALUES (?, ?, ?, ?, ?, ?, ?)', [
                $user->data()->id,
                $this->_integration->data()->id,
                Output::getClean($identifier),
                Output::getClean($username), 
                $verified ? 1 : 0,
                date('U'),
                $code
            ]
        );
    }

    /**
     * Delete integration user data.
     *
     * @return bool
     */
    public function unlinkIntegration(): void {
        $this->_db->createQuery(
            'DELETE FROM nl2_users_integrations WHERE user_id = ? AND integration_id = ?', [
                $this->data()->user_id,
                $this->_integration->data()->id
            ]
        );
    }
}