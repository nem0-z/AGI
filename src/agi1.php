#!/usr/bin/php -q
<?php
include(dirname(__DIR__).'/include/phpagi.php');
include(dirname(__DIR__).'/include/app.php');
$agi = new AGI();
$app = new App();

//Connect to db
$db = $app->connectDB("localhost", "asterisk", "test1234", "demo1");

//Fetch extension, needed later
$callerID = $app->getCallerID();

//Pick up the call
$agi->answer();
$agi->stream_file('hello-world');

$userPw = $app->currentUser();
$app->authenticate($userPw);

for (;;) {
    $agi->stream_file('dir-welcome');
    $choice = $app->getChoice();

    if ($choice == 1) {
        if ($app->requestCall())
            die('Call failed');
    } else if ($choice == 2) {
        $app->changePassword();
    } else if ($choice == 3)
        //Repeat
        continue;
    else if ($choice == 9)
        //End
        break;
    else
        $agi->stream_file('conf-errormenu');
}

$agi->stream_file('demo-thanks');
$agi->hangup();
?>