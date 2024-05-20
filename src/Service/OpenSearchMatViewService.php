<?php

namespace OsOliver\OpenSearchMatViewBundle\Service;

use App\Entity\Listing;
use OpenSearch;
use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use Symfony\Component\Uid\UuidV4;

class OpenSearchMatViewService
{
    public const LIST_INDEX = 'list';
    public const PAINLESS_ADD_USER = '
        if (!ctx._source.users.contains(params.user)) {
            ctx._source.users.add(params.user)
        }
        ';
    public const PAINLESS_REMOVE_USER = '
        if (ctx._source.users.contains(params.user)) {
            ctx._source.users.remove(ctx._source.users.indexOf(params.user))
        }
        ';

    private readonly Client $client;

    public function __construct(
        // private string $indexName,
    ) {
        /*
        $this->client = OpenSearch\ClientBuilder::fromConfig([
            'hosts' => [
                $_ENV['OS_URL']
            ],
            'retries' => 2,
            'handler' => OpenSearch\ClientBuilder::multiHandler()
        ]);
        */

        // OR via Builder

        if (empty($_ENV['USE_LOCAL_CREDENTIALS'])) {
            $this->client = (new ClientBuilder())
                ->setHosts([$_ENV['OS_URL']])
                // ->setBasicAuthentication('admin', 'admin') // For testing only. Don't store credentials in code.
                // or, if using AWS SigV4 authentication:
                // ->setSigV4Region('us-east-2')
                // ->setSigV4CredentialProvider(true)
                ->setSSLVerification(false) // For testing only. Use certificate for validation
                ->build();
        } else {
            $this->client = (new ClientBuilder())
                ->setHosts([$_ENV['LOCAL_OS_URL']])
                ->setBasicAuthentication($_ENV['LOCAL_OS_USER'], $_ENV['LOCAL_OS_PASSWORD']) // For testing only. Don't store credentials in code.
                // or, if using AWS SigV4 authentication:
                // ->setSigV4Region('us-east-2')
                // ->setSigV4CredentialProvider(true)
                ->setSSLVerification(false) // For testing only. Use certificate for validation
                ->build();
        }
    }

    /**
     * Search first 10 user documents for specific client.
     */
    public function searchByClientId($clientId)
    {
        $docs = $this->client->search([
            // index to search in or '_all' for all indices
            'index' => 'user',
            'size' => 10,
            'body' => [
                'query' => [
                    'match' => [
                        'client.id' => $clientId,
                    ],
                ],
            ],
        ]);
        // var_dump($docs['hits']['total']['value'] > 0);
        dump($docs);
        dump($docs['hits']['total']['value']);

        return;
    }

    /**
     * Search first 25 user documents for specific client.
     */
    public function searchByQuery($query, array $options = [])
    {
        $default = [
            'size' => 25,
            'page' => 1,
        ];
        $options = array_merge($default, $options);
        $docs = $this->client->search([
            'index' => 'user',
            'size' => $options['size'],
            'body' => [
                'query' => $query,
                'sort' => $options['sort'],
                'from' => ($options['page'] - 1) * $options['size'],
            ],
        ]);
        // var_dump($docs['hits']['total']['value'] > 0);

        // if (!empty($docs['hits'])) {
        //     dump($docs['hits']['total']);
        //     foreach ($docs['hits']['hits'] as $item) {
        //         if (empty($item['_source']['custom_fields'])) {
        //             continue;
        //         }
        //         dump($item['_source']['first_name'] . ' ' . $item['_source']['last_name'], $item['_source']['custom_fields']);
        //     }
        //     dd('custom fields only');
        // }
        // dump($docs);
        // dump($docs['hits']['total']['value']);

        return $docs['hits'];
    }

    public function createDummyRecords($clientId)
    {
        $names = ['George', 'Luke', 'Tom', 'Cyrus', 'Archibald', 'Oliver', 'Zoran', 'Milos', 'John'];
        $surnames = ['Juniper', 'Westfield', 'Gorgeous', 'Goldfest', 'Principal', 'Carpenter'];

        $result = $this->client->create([
            'index' => 'user',
            'body' => [
                'client.id' => $clientId,
                'first_name' => $names[random_int(0, count($names) - 1)],
                'last_name' => $surnames[random_int(0, count($surnames) - 1)],
                'uuid' => new UuidV4(),
            ],
        ]);
    }

    public function getSingleUserByUuid($uuid)
    {
        $result = $this->client->get([
            'index' => 'user',
            'id' => $uuid,
        ]);

        return $result;
    }

    public function removeSingleUser($clientId, $id = false)
    {
        // Lucene query string syntax
        $query = 'client.id: '.$clientId;

        $result = $this->client->deleteByQuery([
            'index' => 'user',
            'max_docs' => 1,
            'q' => $query,
        ]);

        dump($result);
    }

    public function getInfo($clientId)
    {
        dump($this->client->info());
    }

    public function getUserIndexInfo()
    {
        dump($this->client->info());
    }

    public function putListToIndex(Listing $list)
    {
        $data = [
            'id' => $list->getId(),
            'clientId' => $list->getClient(),
            'clientUuid' => $list->getClient(),
            'title' => $list->getTitle(),
            'authorUuid' => $list->getAuthorUuid(),
            'users' => [],
        ];

        $this->createList($data);
    }

    public function createList(array $data)
    {
        $result = $this->client->create([
            'id' => $data['id'],
            'index' => self::LIST_INDEX,
            'body' => [
                'client' => [
                    'id' => $data['clientId'],
                    'uuid' => $data['clientUuid'],
                ],
                'title' => $data['title'],
                'author' => [
                    'uuid' => $data['authorUuid'],
                ],
                'users' => $data['users'],
            ],
        ]);

        return $result;
    }

    public function deleteList(array $data)
    {
        $result = $this->client->delete([
            'id' => $data['id'],
            'index' => self::LIST_INDEX,
        ]);

        return $result;
    }

    public function updateList(array $data)
    {
        $result = $this->client->update([
            'id' => $data['id'],
            'index' => self::LIST_INDEX,
            'body' => $data['updates'],
        ]);

        return $result;
    }

    public function addUserToList(array $data)
    {
        $result = $this->client->get([
            'index' => self::LIST_INDEX,
            'id' => $data['list'],
        ]);

        if (empty($result['found']) || !$result['found']) {
            return;
        }
        $list = $result['_source'];

        /*
        $newusers = $list['users'];
        $newusers[] = $data['user'];

        $this->updateList([
            'id' => $data['list'],
            'updates' => [
                'doc' => [
                    'users' => $newusers
                ]
            ]
        ]);
        */

        $this->updateList([
            'id' => $data['list'],
            'updates' => [
                'script' => [
                    'lang' => 'painless',
                    'params' => [
                        'user' => $data['user']
                    ],
                    'source' => self::PAINLESS_ADD_USER,
                ]
            ]
        ]);
    }

    public function deleteUserFromList(array $data)
    {
        $result = $this->client->get([
            'index' => self::LIST_INDEX,
            'id' => $data['list'],
        ]);

        if (empty($result['found']) || !$result['found']) {
            return;
        }
        $list = $result['_source'];
        if (empty($list['users'])) {
            return;
        }

        /*
        $pos = array_search($data['user'], $list['users']);
        if ($pos === false) {
            return;
        }

        unset($list['users'][$pos]);
        $this->updateList([
            'id' => $data['list'],
            'updates' => [
                'doc' => [
                    'users' => array_values($list['users'])
                ]
            ]
        ]);
        */
        // update using ES script

        $this->updateList([
            'id' => $data['list'],
            'updates' => [
                'script' => [
                    'lang' => 'painless',
                    'params' => [
                        'user' => $data['user']
                    ],
                    'source' => self::PAINLESS_REMOVE_USER,
                ]
            ]
        ]);
    }


    public function createDocument(array $data)
    {
        $result = $this->client->create([
            'id' => $data['id'],
            'index' => $data['index'],
            'body' => $data['body'],
        ]);

        return $result;
    }

    public function deleteDocument(array $data)
    {
        $result = $this->client->delete([
            'id' => $data['id'],
            'index' => $data['index'],
        ]);

        return $result;
    }

    public function updateDocument(array $data)
    {
        $result = $this->client->update([
            'id' => $data['id'],
            'index' => $data['index'],
            'body' => $data['updates'],
        ]);

        return $result;
    }

    /**
     * $id can be string only (uuid, or built-in ES identifier)
     */
    public function getDocument(string $index, $id)
    {
        $result = $this->client->get([
            'index' => $index,
            'id' => $id,
        ]);

        return $result;
    }
}
