<?php


return [
    'javascript'   => ['aliases' => ['js', 'ecmascript', 'es6', 'es6+']],
    'typescript'   => ['aliases' => ['ts']],
    'python'       => ['aliases' => []],
    'java'         => ['aliases' => []],
    'c++'          => ['aliases' => ['cpp']],
    'c#'           => ['aliases' => ['csharp', 'c sharp']],
    'node.js'      => ['aliases' => ['node', 'nodejs', 'node js']],
    'react'        => ['aliases' => ['reactjs', 'react.js']],
    'react native' => ['aliases' => []],
    'vue.js'       => ['aliases' => ['vue', 'vuejs']],
    'postgresql'   => ['aliases' => ['postgres', 'psql']],
    'mongodb'      => ['aliases' => ['mongo']],
    'mysql'        => ['aliases' => []],
    'sql'          => ['aliases' => []],
    'docker'       => ['aliases' => [], 'implied_by' => ['docker compose', 'dockerfile']],
    'kubernetes'   => ['aliases' => ['k8s']],
    'rest apis'    => ['aliases' => ['rest api', 'restful api', 'restful apis', 'restful']],
    'ci/cd'        => ['aliases' => ['ci cd', 'continuous integration', 'continuous delivery']],
    'git'          => ['aliases' => [], 'implied_by' => ['github', 'gitlab', 'bitbucket']],
    'aws'          => ['aliases' => ['amazon web services'], 'implied_by' => ['ec2', 's3', 'lambda', 'cloudwatch']],
    'laravel'      => ['aliases' => []],
    'php'          => ['aliases' => []],
];
