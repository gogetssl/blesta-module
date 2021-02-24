<?php

return [
    /**
     * Base configuration of module
     */
    'module' => [
        'namespace'       => 'MgGoGetSsl',
        'system_name'     => 'mg_go_get_ssl',
        'version'         => '2.0.0',
        'authors'         => [
            [
                'name' => 'ModulesGarden',
                'url'  => 'www.modulesgarden.com',
            ],
        ],
        'row_name'        => 'MG GoGetSSL Configuration',
        'row_name_plural' => '',
        'group_name'      => 'Module group name',
        'row_meta_key'    => 'api_config_name',
    ],

    /**
     * API configuration
     */
    'api' => [
        'url' => 'https://my.gogetssl.com/api',
    ],

    /**
     * Only EMAIL validation method available for them
     */
    'brands_with_only_email_validation' => [
        'geotrust', 'thawte', 'rapidssl', 'symantec'
    ]
];