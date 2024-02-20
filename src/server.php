<?php

// Load dependencies
require_once __DIR__ . '/../vendor/autoload.php';

// Check arguments
if (empty($argv[1]))
{
    exit(_('Configured hostname required as argument!') . PHP_EOL);
}

// Check cert exists
if (!file_exists(__DIR__ . '/../host/' . $argv[1] . '/cert.pem'))
{
    exit(
        sprintf(
            _('Certificate for host "%s" not found!') . PHP_EOL,
            $argv[1]
        )
    );
}

// Check key exists
if (!file_exists(__DIR__ . '/../host/' . $argv[1] . '/key.rsa'))
{
    exit(
        sprintf(
            _('Key for host "%s" not found!') . PHP_EOL,
            $argv[1]
        )
    );
}

// Check host configured
if (!file_exists(__DIR__ . '/../host/' . $argv[1] . '/config.json'))
{
    exit(
        sprintf(
            _('Host "%s" not configured!') . PHP_EOL,
            $argv[1]
        )
    );
}

// Init config
$config = json_decode(
    file_get_contents(
        __DIR__ . '/../host/' . $argv[1] . '/config.json'
    )
);

// Init index
$index = new \Kvazar\Index\Manticore(
    $config->manticore->index->document->name,
    (array) $config->manticore->index->document->meta,
    $config->manticore->server->host,
    $config->manticore->server->port
);

// Init server
$server = new \Yggverse\TitanII\Server();

$server->setCert(
    __DIR__ . '/../host/' . $argv[1] . '/cert.pem'
);

$server->setKey(
    __DIR__ . '/../host/' . $argv[1] . '/key.rsa'
);

$server->setHandler(
    function (\Yggverse\TitanII\Request $request): \Yggverse\TitanII\Response
    {
        global $config;
        global $index;

        $response = new \Yggverse\TitanII\Response();

        $response->setCode(
            20
        );

        $response->setMeta(
            'text/gemini; charset=utf-8'
        );

        // Route begin
        switch (true)
        {
            case null  == $request->getPath():
            case false == $request->getPath():
            case ''    == $request->getPath():

                $response->setCode(
                    30
                );

                $response->setMeta(
                    '/'
                );

            break;

            // Main
            case '/' == $request->getPath():
            case preg_match('/\/(N[A-z0-9]{33})/', $request->getPath(), $ns):

                $part = 1;
                $search = '';
                $namespace = null;
                $h1 = [];
                $filter = [];
                $result = [];

                // Parse request
                parse_str(
                    $request->getQuery(),
                    $attribute
                );

                if (isset($attribute['search']))
                {
                    $search = urldecode(
                        (string) $attribute['search']
                    );
                }

                if (isset($attribute['part']))
                {
                    $part = (int) $attribute['part'];
                }

                if (isset($ns[1]))
                {
                    $namespace = $ns[1];
                }

                // Build header
                if ($search)
                {
                    $h1[] = $search;
                    $h1[] = $config->geminiapp->string->search;

                    if ($part > 1)
                    {
                        $h1[] = sprintf(
                            '%s %d',
                            $config->geminiapp->string->part,
                            $part
                        );
                    }

                    $h1[] = $config->geminiapp->string->title;
                }

                else if ($namespace)
                {
                    // Find namespace alias
                    if ($aliases = $index->get('_KEVA_NS_', $filter))
                    {
                        foreach ($aliases as $alias)
                        {
                            if ($alias['key'] == '_KEVA_NS_')
                            {
                                $h1[] = $alias['value'];

                                break;
                            }
                        }
                    }

                    // Not found, use hash
                    else
                    {
                        $h1[] = $namespace;
                    }

                    $h1[] = $config->geminiapp->string->title;
                }

                else
                {
                    if ($part > 1)
                    {
                        $h1[] = sprintf(
                            '%s %d',
                            $config->geminiapp->string->part,
                            $part
                        );

                        $h1[] = $config->geminiapp->string->title;
                    }

                    else
                    {
                        $h1[] = $config->geminiapp->string->title;
                        $h1[] = $config->geminiapp->string->description;
                    }
                }

                $result[] = sprintf(
                    '# %s',
                    implode(
                        ' · ',
                        $h1
                    )
                );

                // Menu
                $result[] = sprintf(
                    '=> /search %s',
                    $config->geminiapp->string->search
                );

                // Append links
                if ($namespace || $search || $part > 1)
                {
                    $result[] = sprintf(
                        '=> / %s',
                        $config->geminiapp->string->main
                    );
                }

                else
                {
                    $result[] = '';

                    foreach ($config->geminiapp->links as $link)
                    {
                        $result[] = sprintf(
                            '=> %s',
                            $link
                        );
                    }
                }

                // Get records
                if ($namespace)
                {
                    $filter =
                    [
                        'crc32_namespace' => crc32(
                            $namespace
                        )
                    ];
                }

                $records = $index->get(
                    $search,
                    $filter,
                    [
                        'time' => 'desc'
                    ],
                    $part > 1 ? ($part - 1) * $config->geminiapp->pagination->limit : 0,
                    $config->geminiapp->pagination->limit
                );

                // Append h2
                $result[] = sprintf(
                    '## %s',
                    $search ? $config->geminiapp->string->results : $config->geminiapp->string->latest
                );

                if ($records)
                {
                    // Append latest records
                    foreach ($records as $record)
                    {
                        // Key
                        $result[] = sprintf(
                            '### %s',
                            $record['key']
                        );

                        // Value
                        $result[] = sprintf(
                            '``` %s',
                            $config->geminiapp->string->value
                        );

                        $result[] = $record['value'];
                        $result[] = '```';

                        // Link
                        $result[] = sprintf(
                            '=> /%s %s in %d',
                            $record['transaction'],
                            date(
                                'Y-m-d',
                                $record['time']
                            ),
                            $record['block']
                        );
                    }

                    // Append navigation
                    $result[] = sprintf(
                        '## %s',
                        $config->geminiapp->string->navigation
                    );

                    // Pagination
                    $older = [];
                    $newer = [];

                    if ($search)
                    {
                        $older[] = sprintf(
                            'search=%s',
                            urlencode(
                                $search
                            )
                        );

                        $newer[] = sprintf(
                            'search=%s',
                            urlencode(
                                $search
                            )
                        );
                    }

                    $older[] = sprintf(
                        'part=%d',
                        $part + 1
                    );

                    if ($part > 1)
                    {
                        $newer[] = sprintf(
                            'part=%d',
                            $part - 1
                        );
                    }

                    // Links
                    if ($older)
                    {
                        $result[] = sprintf(
                            '=> /%s?%s %s',
                            $namespace,
                            implode(
                                '&',
                                $older,
                            ),
                            $config->geminiapp->string->older
                        );
                    }

                    if ($newer)
                    {
                        $result[] = sprintf(
                            '=> /%s?%s %s',
                            $namespace,
                            implode(
                                '&',
                                $newer,
                            ),
                            $config->geminiapp->string->newer
                        );
                    }
                }

                else
                {
                    $result[] = $config->geminiapp->string->nothing;
                }

                $response->setContent(
                    implode(
                        PHP_EOL,
                        $result
                    )
                );

            break;

            // Transaction page
            case preg_match('/\/([A-f0-9]{64})/', $request->getPath(), $attribute):

                $result = [];

                foreach (
                    $index->get(
                        $attribute[1],
                        [
                            'crc32_transaction' => crc32(
                                $attribute[1]
                            )
                        ],
                        [
                            'time' => 'desc'
                        ],
                        0, 1
                    ) as $record
                ) {
                    if ($record['transaction'] == $attribute[1])
                    {
                        // Header
                        $result[] = sprintf(
                            '# %s',
                            $record['key']
                        );

                        // Body
                        $result[] = sprintf(
                            '``` %s',
                            $config->geminiapp->string->value
                        );

                        $result[] = $record['value'];
                        $result[] = '```';

                        $result[] = sprintf(
                            '%s in %d',
                            date(
                                'Y-m-d',
                                $record['time']
                            ),
                            $record['block']
                        );

                        // Footer
                        $result[] = sprintf(
                            '## %s',
                            $config->geminiapp->string->navigation
                        );

                        $result[] = sprintf(
                            '=> /%s %s',
                            $record['namespace'],
                            $config->geminiapp->string->namespace
                        );

                        $result[] = sprintf(
                            '=> /search %s',
                            $config->geminiapp->string->search
                        );

                        $result[] = sprintf(
                            '=> / %s',
                            $config->geminiapp->string->main
                        );

                        break;
                    }
                }

                $response->setContent(
                    implode(
                        PHP_EOL,
                        $result
                    )
                );

            break;

            // Search page
            case '/search' == $request->getPath():

                if (empty($request->getQuery()))
                {
                    $response->setCode(
                        10
                    );

                    $response->setMeta(
                        $config->geminiapp->string->search
                    );
                }

                else
                {
                    $response->setCode(
                        30
                    );

                    $response->setMeta(
                        sprintf(
                            '/?search=%s',
                            $request->getQuery()
                        )
                    );
                }

            break;

            // Not found
            default:

                $response->setCode(
                    51
                );

                $response->setMeta(
                    $config->geminiapp->string->nothing
                );
        }

        return $response;
    }
);

// Start server
printf(
    _('Server "%s" started on %s:%d') . PHP_EOL,
    $argv[1],
    $config->geminiapp->server->host,
    $config->geminiapp->server->port
);

$server->start(
    $config->geminiapp->server->host,
    $config->geminiapp->server->port
);