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

                if ($namespace)
                {
                    $filter =
                    [
                        'crc32_namespace' => crc32(
                            $namespace
                        )
                    ];
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

                    if ($part > 1)
                    {
                        $h1[] = sprintf(
                            '%s %d',
                            $config->geminiapp->string->part,
                            $part
                        );
                    }
                }

                else
                {
                    if ($part > 1)
                    {
                        $h1[] = $config->geminiapp->string->title;

                        $h1[] = sprintf(
                            '%s %d',
                            $config->geminiapp->string->part,
                            $part
                        );
                    }

                    else
                    {
                        $h1[] = $config->geminiapp->string->title;
                    }
                }

                $result[] = null;
                $result[] = sprintf(
                    '# %s',
                    implode(
                        ' · ',
                        $h1
                    )
                );

                // Menu
                $result[] = null;
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
                    $result[] = null;

                    foreach ($config->geminiapp->links as $link)
                    {
                        $result[] = sprintf(
                            '=> %s',
                            $link
                        );
                    }
                }

                // Get records
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
                $result[] = null;
                $result[] = sprintf(
                    '## %s',
                    $search ? $config->geminiapp->string->results : $config->geminiapp->string->latest
                );

                if ($records)
                {
                    $result[] = null;

                    // Append latest records
                    foreach ($records as $record)
                    {
                        // Link
                        $result[] = sprintf(
                            '=> /%s %s %s',
                            $record['transaction'],
                            date(
                                'Y-m-d',
                                $record['time']
                            ),
                            trim(
                                preg_replace( // single-line
                                    '/[\s]+/',
                                    ' ',
                                    $record['key']
                                )
                            )
                        );
                    }

                    // Append navigation
                    $result[] = null;

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
                    $result[] = null;
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
                        // View mode
                        switch ($request->getQuery())
                        {
                            case 'raw':

                                // Transaction ID
                                $result[] = null;
                                $result[] = sprintf(
                                    '# %s',
                                    $record['transaction']
                                );

                                $result[] = null;
                                $result[] = sprintf(
                                    '## %s',
                                    $config->geminiapp->string->data
                                );

                                // Key
                                $result[] = null;
                                $result[] = sprintf(
                                    '### %s',
                                    $config->geminiapp->string->key
                                );

                                $result[] = '```';

                                $lines = [];

                                foreach ((array) explode(PHP_EOL, (string) $record['key']) as $line)
                                {
                                    $lines[] = preg_replace(
                                        '/^```/',
                                        ' ```',
                                        $line
                                    );
                                }

                                $result[] = implode(
                                    PHP_EOL,
                                    $lines
                                );

                                $result[] = '```';

                                // Value
                                $result[] = null;
                                $result[] = sprintf(
                                    '### %s',
                                    $config->geminiapp->string->value
                                );

                                $result[] = '```';

                                $lines = [];

                                foreach ((array) explode(PHP_EOL, (string) $record['value']) as $line)
                                {
                                    $lines[] = preg_replace(
                                        '/^```/',
                                        ' ```',
                                        $line
                                    );
                                }

                                $result[] = implode(
                                    PHP_EOL,
                                    $lines
                                );

                                $result[] = '```';

                                // Meta
                                $result[] = null;
                                $result[] = sprintf(
                                    '## %s',
                                    $config->geminiapp->string->meta
                                );

                                // Time
                                $result[] = null;
                                $result[] = sprintf(
                                    '### %s',
                                    $config->geminiapp->string->time
                                );

                                $result[] = null;
                                $result[] = date(
                                    'Y-m-d',
                                    $record['time']
                                );

                                // Block
                                $result[] = null;
                                $result[] = sprintf(
                                    '### %s',
                                    $config->geminiapp->string->block
                                );

                                $result[] = null;
                                $result[] = $record['block'];

                            break;

                            default:

                                // Key
                                $result[] = null;
                                $result[] = sprintf(
                                    '# %s',
                                    trim(
                                        preg_replace( // single-line
                                            '/[\s]+/',
                                            ' ',
                                            $record['key']
                                        )
                                    )
                                );

                                // Value
                                $result[] = null;
                                $result[] = trim(
                                    preg_replace(
                                        [
                                            '/(^|\s+)(#|##)/', // escape h1, h2, hashtags
                                            '/[\n\r]{3,}/',    // remove extra breaks
                                        ],
                                        [
                                            '$1 $2',
                                            PHP_EOL . PHP_EOL,
                                        ],
                                        trim(
                                            $record['value']
                                        )
                                    )
                                );

                                // Time
                                $result[] = null;
                                $result[] = sprintf(
                                    '%s in %d',
                                    date(
                                        'Y-m-d',
                                        $record['time']
                                    ),
                                    $record['block']
                                );
                        }

                        // Footer
                        $result[] = null;
                        $result[] = sprintf(
                            '## %s',
                            $config->geminiapp->string->navigation
                        );

                        switch ($request->getQuery())
                        {
                            case 'raw':

                                $result[] = null;
                                $result[] = sprintf(
                                    '=> /%s %s',
                                    $record['transaction'],
                                    $config->geminiapp->string->view->reader
                                );

                            break;

                            default:

                                $result[] = null;
                                $result[] = sprintf(
                                    '=> /%s?raw %s',
                                    $record['transaction'],
                                    $config->geminiapp->string->view->raw
                                );
                        }

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