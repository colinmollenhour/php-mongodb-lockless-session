<?php

namespace Cm\MongoSession;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;

class Backend implements \SessionHandlerInterface
{
    protected $_mongoClient;

    protected $_collection;

    protected $_reads;

    protected const BOT_PATTERN = '/bot|crawl|slurp|spider|mediapartners/i';

    protected const MAX_LIFETIME = 2592000; // 30 days in seconds

    protected const BOT_LIFETIME = 30; // 30 seconds for bots

    public function __construct()
    {
        $this->_mongoClient = new Client('mongodb://localhost:27017');
        $this->_collection = $this->_mongoClient->selectDatabase('sessions')->selectCollection('sessions');
    }

    /**
     * Read session data and update read stats
     *
     * @param  string  $sessionId
     * @return array
     */
    public function read($sessionId)
    {
        try {
            $result = $this->_collection->findOneAndUpdate(
                ['_id' => $sessionId],
                [
                    '$set' => ['last_read_at' => new UTCDateTime],
                    '$inc' => ['reads' => 1],
                ],
                [
                    'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
                    'upsert' => true,
                ]
            );

            // If session was destroyed, return empty array
            if (isset($result['_destroyed']) && $result['_destroyed'] === true) {
                $this->_reads = 1;

                return [];
            }

            // Store number of reads for later when we write to calculate lifetime
            $this->_reads = $result['reads'] ?? 1;

            // Return the session data
            return $result['data'] ?? [];
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            Mage::logException($e);

            return [];
        }
    }

    /**
     * Calculate session lifetime based on number of reads and user agent
     */
    protected function _calculateLifetime(int $reads, string $userAgent): int
    {
        if (preg_match(self::BOT_PATTERN, $userAgent)) {
            return self::BOT_LIFETIME;
        }

        $lifetime = $reads > 100 ? self::MAX_LIFETIME : pow($reads, 3) * 30;

        return (int) min($lifetime, self::MAX_LIFETIME);
    }

    /**
     * Process session data into MongoDB updates
     *
     * @param  string  $sessionId
     * @param  array  $sessionData
     * @return bool
     */
    public function write($sessionId, $sessionData): mixed
    {
        if (empty($sessionData)) {
            return true;
        }

        try {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $lifetime = $this->_calculateLifetime($this->_reads ?? 0, $userAgent);
            $updates = $this->_processSessionData($sessionData);

            // Add metadata updates
            $updates['$set']['updated_at'] = new \MongoDB\BSON\UTCDateTime(time() * 1000);
            $updates['$set']['lifetime'] = $lifetime;
            $updates['$set']['user_agent'] = $userAgent;

            // Not an upsert so we do not resurrect a destroyed session
            $result = $this->_collection->updateOne(
                ['_id' => $sessionId],
                $updates
            );

            return $result->isAcknowledged();
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            Mage::logException($e);

            return false;
        }
    }

    /**
     * Process session data into MongoDB update operations
     *
     * @param  array  $sessionData
     * @return array
     */
    protected function _processSessionData($sessionData)
    {
        $updates = [];
        $sets = [];
        $unsets = [];

        foreach ($sessionData as $namespace => $operations) {
            // Non-namespaced data is written directly
            if (str_starts_with($namespace, '_')) {
                $sets['data.'.$namespace] = $operations;
            }

            // Ignore namespaces that do not conform to the expected format
            if (! isset($operations['__operations'])) {
                continue;
            }

            foreach ($operations['__operations'] as $operation) {
                $key = 'data.'.$namespace.'.'.$operation['key'];

                if ($operation['type'] === 'set') {
                    $sets[$key] = $operation['value'];
                } elseif ($operation['type'] === 'unset') {
                    unset($sets[$key]);
                    $unsets[$key] = 1;
                }
            }
        }

        if (! empty($sets)) {
            $updates['$set'] = $sets;
        }
        if (! empty($unsets)) {
            $updates['$unset'] = $unsets;
        }

        return $updates;
    }

    /**
     * Mark session as destroyed
     *
     * @param  string  $sessionId
     * @return bool
     */
    public function destroy($sessionId)
    {
        try {
            $result = $this->_collection->updateOne(
                ['_id' => $sessionId],
                [
                    '$set' => [
                        '_destroyed' => true,
                        'destroyed_at' => new \MongoDB\BSON\UTCDateTime,
                    ],
                ]
            );

            return $result->isAcknowledged();
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            Mage::logException($e);

            return false;
        }
    }

    /**
     * Garbage collection
     *
     * @param  int  $maxlifetime
     * @return bool
     */
    public function gc($maxlifetime)
    {
        try {
            $now = time();

            // Delete sessions that are either:
            // 1. Destroyed and older than their calculated lifetime
            // 2. Not accessed for longer than their calculated lifetime
            $result = $this->_collection->deleteMany([
                '$or' => [
                    [
                        '_destroyed' => true,
                        'destroyed_at' => [
                            '$lt' => new \MongoDB\BSON\UTCDateTime(($now - $maxlifetime) * 1000),
                        ],
                    ],
                    [
                        '$expr' => [
                            '$lt' => [
                                '$last_read_at',
                                new \MongoDB\BSON\UTCDateTime(($now - '$lifetime') * 1000),
                            ],
                        ],
                    ],
                ],
            ]);

            return $result->isAcknowledged();
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            Mage::logException($e);

            return false;
        }
    }

    public function open(string $path, string $name): void {}

    public function close(): void {}
}
