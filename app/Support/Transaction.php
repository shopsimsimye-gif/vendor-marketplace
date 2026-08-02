<?php
namespace VMP\Support;

defined('ABSPATH') || exit;

/**
 * Database transaction helper for WordPress/MySQL.
 * Ensures atomic multi-step operations.
 */
class Transaction
{
    private bool $active = false;

    /**
     * Start a new transaction.
     */
    public function begin(): void
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        $this->active = true;
    }

    /**
     * Commit the current transaction.
     */
    public function commit(): void
    {
        global $wpdb;
        $wpdb->query('COMMIT');
        $this->active = false;
    }

    /**
     * Rollback the current transaction.
     */
    public function rollback(): void
    {
        global $wpdb;
        $wpdb->query('ROLLBACK');
        $this->active = false;
    }

    /**
     * Execute a callback within a transaction.
     * Automatically commits on success, rolls back on failure.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws \Throwable
     */
    public function run(callable $callback)
    {
        $this->begin();
        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Check if a transaction is currently active.
     */
    public function isActive(): bool
    {
        return $this->active;
    }
}
