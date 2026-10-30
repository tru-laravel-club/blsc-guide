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
    | A GitHub token for guide:start, which checks who is assigned to the issue
    | of a feature. A private repository needs it. Never commit the token.
    */
    'github_token' => env('GITHUB_TOKEN'),

    /*
    | Where guide:verify saves the last result of each acceptance test.
    | Laravel's storage/framework/cache folder is already ignored by git.
    */
    'results_path' => null,

    /*
    | The folder that marks features as done, with one small file for each feature.
    | Commit it to blsc-website, so every member sees the same progress.
    | The default is .blsc-guide/done in the app root.
    */
    'progress_path' => null,

];
