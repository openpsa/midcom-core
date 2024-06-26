<?php
return [
    'default' => [
        'description' => 'shell schema',
        'l10n_db'     => 'midgard.admin.asgard',
        'operations' => [
            'save' => 'run'
        ],
        'templates' => [
            'form' => \midcom\datamanager\template\form::class
        ],
        'fields'      => [
            'code' => [
                'title'       => 'code',
                'storage'     => null,
                'type'        => 'php',
                'type_config' => [
                    'output_mode' => 'code',
                ],
                'widget'      => 'codemirror',
                'widget_config' => [
                    'height' => 30,
                    'width' => '100%'
                ],
                'required' => true
        	]
    	]
    ]
];