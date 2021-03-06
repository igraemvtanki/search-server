<?php

/*
 * This file is part of the Apisearch Server
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Feel free to edit as you please, and have fun.
 *
 * @author Marc Morera <yuhu@mmoreram.com>
 */

declare(strict_types=1);

namespace Apisearch\Server\Tests\Functional;

use Apisearch\Config\Config;
use Apisearch\Config\ImmutableConfig;
use Apisearch\Exception\ResourceNotAvailableException;
use Apisearch\Model\Changes;
use Apisearch\Model\Item;
use Apisearch\Model\ItemUUID;
use Apisearch\Plugin\Elastica\ElasticaPluginBundle;
use Apisearch\Plugin\Neo4j\Neo4jPluginBundle;
use Apisearch\Plugin\Redis\RedisPluginBundle;
use Apisearch\Query\Query as QueryModel;
use Apisearch\Result\Events;
use Apisearch\Result\Logs;
use Apisearch\Result\Result;
use Apisearch\Server\ApisearchServerBundle;
use Apisearch\Server\Exception\ErrorException;
use Apisearch\Token\Token;
use Apisearch\Token\TokenUUID;
use Mmoreram\BaseBundle\BaseBundle;
use Mmoreram\BaseBundle\Kernel\BaseKernel;
use Mmoreram\BaseBundle\Tests\BaseFunctionalTest;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

set_error_handler(function ($code, $message, $file, $line, $context) {
    if (0 == error_reporting()) {
        return;
    }

    throw new ErrorException($message, $code);
});

/**
 * Class ApisearchServerBundleFunctionalTest.
 */
abstract class ApisearchServerBundleFunctionalTest extends BaseFunctionalTest
{
    /**
     * @var string
     *
     * External server port
     */
    const HTTP_TEST_SERVICE_PORT = '8200';

    /**
     * Get container service.
     *
     * @param string $serviceName
     *
     * @return mixed
     */
    public static function getStatic(string $serviceName)
    {
        return self::$container->get($serviceName);
    }

    /**
     * Container has service.
     *
     * @param string $serviceName
     *
     * @return bool
     */
    public static function hasStatic(string $serviceName): bool
    {
        return self::$container->has($serviceName);
    }

    /**
     * Get container parameter.
     *
     * @param string $parameterName
     *
     * @return mixed
     */
    public static function getParameterStatic(string $parameterName)
    {
        return self::$container->getParameter($parameterName);
    }

    /**
     * Get kernel.
     *
     * @return KernelInterface
     */
    protected static function getKernel(): KernelInterface
    {
        self::loadEnv();
        self::$godToken = $_ENV['APISEARCH_GOD_TOKEN'];
        self::$pingToken = $_ENV['APISEARCH_PING_TOKEN'];
        self::$readonlyToken = $_ENV['APISEARCH_READONLY_TOKEN'];
        $imports = [
            ['resource' => '@ApisearchServerBundle/Resources/config/tactician.yml'],
            [
                'resource' => '@ApisearchServerBundle/app_deploy.yml',
                'ignore_errors' => true,
            ],
            ['resource' => '@ApisearchServerBundle/Resources/test/subscribers.yml'],
        ];

        if (!static::logDomainEvents()) {
            $imports[] = ['resource' => '@ApisearchServerBundle/Resources/test/middlewares.yml'];
        }

        $bundles = [
            BaseBundle::class,
            ApisearchServerBundle::class,
            ElasticaPluginBundle::class,
            RedisPluginBundle::class,
            Neo4jPluginBundle::class,
        ];

        $configuration = [
            'imports' => $imports,
            'parameters' => [
                'kernel.secret' => 'sdhjshjkds',
            ],
            'framework' => [
                'test' => true,
            ],
            'services' => [
                '_defaults' => [
                    'autowire' => false,
                    'autoconfigure' => false,
                    'public' => true,
                ],
            ],
            'apisearch_server' => [
                'middleware_domain_events_service' => static::saveEvents()
                    ? 'apisearch_server.middleware.inline_events'
                    : 'apisearch_server.middleware.ignore_events',
                'middleware_logs_service' => static::saveLogs()
                    ? 'apisearch_server.middleware.inline_logs'
                    : 'apisearch_server.middleware.ignore_logs',
                'command_bus_service' => static::asynchronousCommands()
                    ? 'apisearch_server.command_bus.asynchronous'
                    : 'apisearch_server.command_bus.inline',
                'god_token' => self::$godToken,
                'ping_token' => self::$pingToken,
                'readonly_token' => self::$readonlyToken,
            ],
            'rs_queue' => [
                'server' => [
                    'redis' => [
                        'host' => $_ENV['REDIS_HOST'],
                        'port' => $_ENV['REDIS_PORT'],
                    ],
                ],
            ],
            'elastica_plugin' => [
                'cluster' => [
                    'localhost' => [
                        'host' => $_ENV['ELASTICSEARCH_HOST'],
                        'port' => $_ENV['ELASTICSEARCH_PORT'],
                    ],
                ],
                'config' => [
                    'repository' => [
                        'config_path' => '/tmp/config_{app_id}_{index_id}',
                        'shards' => 1,
                        'replicas' => 0,
                    ],
                    'event_repository' => [
                        'shards' => 1,
                        'replicas' => 0,
                    ],
                    'log_repository' => [
                        'shards' => 1,
                        'replicas' => 0,
                    ],
                ],
            ],
            'apisearch' => [
                'repositories' => [
                    'main' => [
                        'adapter' => 'service',
                        'endpoint' => '~',
                        'app_id' => self::$appId,
                        'token' => '~',
                        'test' => true,
                        'search' => [
                            'repository_service' => 'apisearch_server.items_repository',
                        ],
                        'app' => [
                            'repository_service' => 'apisearch_server.app_repository',
                        ],
                        'user' => [
                            'repository_service' => 'apisearch_server.user_repository',
                        ],
                        'event' => [
                            'repository_service' => 'apisearch_server.events_repository',
                        ],
                        'log' => [
                            'repository_service' => 'apisearch_server.logs_repository',
                        ],
                        'indexes' => [
                            self::$index => self::$index,
                            self::$anotherIndex => self::$anotherIndex,
                        ],
                    ],
                    'search_http' => [
                        'adapter' => 'http_test',
                        'endpoint' => '~',
                        'app_id' => self::$appId,
                        'token' => '~',
                        'test' => true,
                        'indexes' => [
                            self::$index => self::$index,
                            self::$anotherIndex => self::$anotherIndex,
                        ],
                    ],
                    'search_socket' => [
                        'adapter' => 'http',
                        'endpoint' => 'http://127.0.0.1:'.self::HTTP_TEST_SERVICE_PORT,
                        'app_id' => self::$appId,
                        'token' => self::$godToken,
                        'test' => true,
                        'indexes' => [
                            self::$index => self::$index,
                            self::$anotherIndex => self::$anotherIndex,
                        ],
                    ],
                ],
            ],
        ];

        return new BaseKernel(
            static::decorateBundles($bundles),
            static::decorateConfiguration($configuration),
            static::decorateRoutes([
                '@ApisearchServerBundle/Resources/config/routing.yml',
            ]),
            'prod', false
        );
    }

    /**
     * Load env vars.
     */
    protected static function loadEnv()
    {
        $envPath = __DIR__.'/../../.env';
        if (file_exists($envPath)) {
            $dotenv = new Dotenv();
            $dotenv->load($envPath);
        }
    }

    /**
     * Log domain events.
     *
     * @return bool
     */
    protected static function logDomainEvents(): bool
    {
        return true;
    }

    /**
     * Use asynchronous commands.
     *
     * @return bool
     */
    protected static function asynchronousCommands(): bool
    {
        return false;
    }

    /**
     * Save events.
     *
     * @return bool
     */
    protected static function saveEvents(): bool
    {
        return true;
    }

    /**
     * Save logs.
     *
     * @return bool
     */
    protected static function saveLogs(): bool
    {
        return true;
    }

    /**
     * Save events.
     *
     * @return bool
     */
    protected static function tokensInRedis(): bool
    {
        return true;
    }

    /**
     * Decorate bundles.
     *
     * @param array $bundles
     *
     * @return array
     */
    protected static function decorateBundles(array $bundles): array
    {
        return $bundles;
    }

    /**
     * Decorate configuration.
     *
     * @param array $configuration
     *
     * @return array
     */
    protected static function decorateConfiguration(array $configuration): array
    {
        return $configuration;
    }

    /**
     * Decorate routes.
     *
     * @param array $routes
     *
     * @return array
     */
    protected static function decorateRoutes(array $routes): array
    {
        return $routes;
    }

    /**
     * @var string
     *
     * God token
     */
    public static $godToken;

    /**
     * @var string
     *
     * Readonly token
     */
    public static $readonlyToken;

    /**
     * @var string
     *
     * Ping token
     */
    public static $pingToken;

    /**
     * @var string
     *
     * App id
     */
    public static $appId = 'test';

    /**
     * @var string
     *
     * App id
     */
    public static $index = 'default';

    /**
     * @var string
     *
     * App id
     */
    public static $anotherAppId = 'another_test';

    /**
     * @var string
     *
     * App id
     */
    public static $anotherInexistentAppId = 'another_test_not_exists';

    /**
     * @var string
     *
     * App id
     */
    public static $anotherIndex = 'another_index';

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        static::configureEnvironment();
        static::resetScenario();
    }

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    public static function tearDownAfterClass()
    {
        static::deleteEverything();
        static::cleanEnvironment();
    }

    /**
     * Reset scenario.
     */
    public static function resetScenario()
    {
        static::deleteEverything();

        static::createIndex(self::$appId);
        static::deleteTokens(self::$appId);

        static::createIndex(self::$anotherAppId);
        static::deleteTokens(self::$anotherAppId);

        static::indexTestingItems();
    }

    /**
     * Index test data.
     *
     * @param string $appId
     * @param string $index
     */
    protected static function indexTestingItems(
        string $appId = null,
        string $index = null
    ) {
        $items = Yaml::parse(file_get_contents(static::getItemsFilePath()));
        $itemsInstances = [];
        foreach ($items['items'] as $item) {
            if (isset($item['indexed_metadata']['created_at'])) {
                $date = new \DateTime($item['indexed_metadata']['created_at']);
                $item['indexed_metadata']['created_at'] = $date->format(DATE_ATOM);
            }
            $itemsInstances[] = Item::createFromArray($item);
        }
        static::indexItems($itemsInstances, $appId, $index);
    }

    /**
     * Get items file path.
     *
     * @return string
     */
    public static function getItemsFilePath(): string
    {
        return __DIR__.'/../items.yml';
    }

    /**
     * Clean all tests data.
     */
    public static function deleteEverything()
    {
        static::deleteAppIdIndexes(self::$appId);
        static::deleteAppIdIndexes(self::$anotherAppId);
    }

    /**
     * Delete index and catch.
     *
     * @param string $appId
     */
    private static function deleteAppIdIndexes(string $appId)
    {
        try {
            static::deleteIndex($appId);
        } catch (ResourceNotAvailableException $e) {
        }
    }

    /**
     * Change index config.
     *
     * @param array $config
     */
    public function changeConfig(array $config)
    {
        static::deleteIndex();
        static::createIndex(
            null,
            null,
            null,
            ImmutableConfig::createFromArray($config)
        );
        static::indexTestingItems();
    }

    /**
     * Query using the bus.
     *
     * @param QueryModel $query
     * @param string     $appId
     * @param string     $index
     * @param Token      $token
     *
     * @return Result
     */
    abstract public function query(
        QueryModel $query,
        string $appId = null,
        string $index = null,
        Token $token = null
    ): Result;

    /**
     * Delete using the bus.
     *
     * @param ItemUUID[] $itemsUUID
     * @param string     $appId
     * @param string     $index
     * @param Token      $token
     */
    abstract public function deleteItems(
        array $itemsUUID,
        string $appId = null,
        string $index = null,
        Token $token = null
    );

    /**
     * Add items using the bus.
     *
     * @param Item[] $items
     * @param string $appId
     * @param string $index
     * @param Token  $token
     */
    abstract public static function indexItems(
        array $items,
        ?string $appId = null,
        ?string $index = null,
        ?Token $token = null
    );

    /**
     * Update using the bus.
     *
     * @param QueryModel $query
     * @param Changes    $changes
     * @param string     $appId
     * @param string     $index
     * @param Token      $token
     */
    abstract public function updateItems(
        QueryModel $query,
        Changes $changes,
        string $appId = null,
        string $index = null,
        Token $token = null
    );

    /**
     * Reset index using the bus.
     *
     * @param string $appId
     * @param string $index
     * @param Token  $token
     */
    abstract public function resetIndex(
        string $appId = null,
        string $index = null,
        Token $token = null
    );

    /**
     * Create index using the bus.
     *
     * @param string          $appId
     * @param string          $index
     * @param Token           $token
     * @param ImmutableConfig $config
     */
    abstract public static function createIndex(
        string $appId = null,
        string $index = null,
        Token $token = null,
        ImmutableConfig $config = null
    );

    /**
     * Configure index using the bus.
     *
     * @param Config $config
     * @param string $appId
     * @param string $index
     * @param Token  $token
     */
    abstract public function configureIndex(
        Config $config,
        string $appId = null,
        string $index = null,
        Token $token = null
    );

    /**
     * Check index.
     *
     * @param string $appId
     * @param string $index
     * @param Token  $token
     *
     * @return bool
     */
    abstract public function checkIndex(
        string $appId = null,
        string $index = null,
        Token $token = null
    ): bool;

    /**
     * Delete index using the bus.
     *
     * @param string $appId
     * @param string $index
     * @param Token  $token
     */
    abstract public static function deleteIndex(
        string $appId = null,
        string $index = null,
        Token $token = null
    );

    /**
     * Add token.
     *
     * @param Token  $newToken
     * @param string $appId
     * @param Token  $token
     */
    abstract public static function addToken(
        Token $newToken,
        string $appId = null,
        Token $token = null
    );

    /**
     * Delete token.
     *
     * @param TokenUUID $tokenUUID
     * @param string    $appId
     * @param Token     $token
     */
    abstract public static function deleteToken(
        TokenUUID $tokenUUID,
        string $appId = null,
        Token $token = null
    );

    /**
     * Get tokens.
     *
     * @param string $appId
     * @param Token  $token
     *
     * @return Token[]
     */
    abstract public static function getTokens(
        string $appId = null,
        Token $token = null
    );

    /**
     * Delete token.
     *
     * @param string $appId
     * @param Token  $token
     */
    abstract public static function deleteTokens(
        string $appId,
        Token  $token = null
    );

    /**
     * Query events.
     *
     * @param QueryModel $query
     * @param int|null   $from
     * @param int|null   $to
     * @param string     $appId
     * @param string     $index
     * @param Token      $token
     *
     * @return Events
     */
    abstract public function queryEvents(
        QueryModel $query,
        ?int $from = null,
        ?int $to = null,
        string $appId = null,
        string $index = null,
        Token $token = null
    ): Events;

    /**
     * Query logs.
     *
     * @param QueryModel $query
     * @param int|null   $from
     * @param int|null   $to
     * @param string     $appId
     * @param string     $index
     * @param Token      $token
     *
     * @return Logs
     */
    abstract public function queryLogs(
        QueryModel $query,
        ?int $from = null,
        ?int $to = null,
        string $appId = null,
        string $index = null,
        Token $token = null
    ): Logs;

    /**
     * Add interaction.
     *
     * @param string $userId
     * @param string $itemUUIDComposed
     * @param int    $weight
     * @param string $appId
     * @param Token  $token
     */
    abstract public function addInteraction(
        string $userId,
        string $itemUUIDComposed,
        int $weight,
        string $appId,
        Token $token
    );

    /**
     * Delete all interactions.
     *
     * @param string $appId
     * @param Token  $token
     */
    abstract public static function deleteAllInteractions(
        string $appId,
        Token $token = null
    );

    /**
     * Ping.
     *
     * @param Token $token
     *
     * @return bool
     */
    abstract public function ping(Token $token = null): bool;

    /**
     * Check health.
     *
     * @param Token $token
     *
     * @return array
     */
    abstract public function checkHealth(Token $token = null): array;

    /**
     * Configure environment.
     */
    abstract public static function configureEnvironment();

    /**
     * Clean environment.
     */
    abstract public static function cleanEnvironment();

    /**
     * Get token id.
     *
     * @param Token $token
     *
     * @return string
     */
    protected static function getTokenId(Token $token = null): string
    {
        return ($token instanceof Token)
                ? $token->getTokenUUID()->composeUUID()
                : self::getParameterStatic('apisearch_server.god_token');
    }
}
