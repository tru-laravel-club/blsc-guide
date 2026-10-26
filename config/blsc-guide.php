<?php

return [

    /*
    | The URL path of the guide page. The guide only loads in the "local" environment.
    */
    'path' => 'guide',

    /*
    | The folder that holds one PHP file per feature.
    */
    'features_path' => dirname(__DIR__).'/features',

    /*
    | The folder that holds the acceptance test templates.
    */
    'tests_path' => dirname(__DIR__).'/stubs/tests',

    /*
    | The GitHub repository of the BLSC website. Used for issue links.
    */
    'repository' => 'https://github.com/tru-laravel-club/blsc-website',

    /*
    | Where guide:verify saves the last result of each acceptance test.
    | Laravel's storage/framework/cache folder is already ignored by git.
    */
    'results_path' => null,

];
