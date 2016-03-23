<?php
require_once 'DexiIo.php';

//See https://app.dexi.io/#/api
define('DI_API_KEY','Your Secret API Key'); //See https://app.dexi.io/#/api
define('DI_ACCOUNT_ID','95917AB5-645C-47ED-B982-61C83328D90A');
$someRunId = 'A0EC5CB1-CA49-4315-BFEA-752C80F80AAA'; //Edit your runs inside the app to get their ID

DexiIo::init(CS_API_KEY, CS_ACCOUNT_ID);

$newExecution = DexiIo::runs()->execute($someRunId);

var_dump($newExecution);
