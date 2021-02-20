#!/usr/bin/php -q
<?php
include('phpagi.php');
$agi = new AGI();

//Fetch extension, needed later
$caller_id = $agi->parse_callerid()['username'];

//Pick up the call
$agi->answer();

//Connect to db
$db = new mysqli('localhost', 'asterisk', 'test123', 'demo1');
if (mysqli_connect_error()) {
    $agi->verbose("Couldn't connect to database");
    exit();
}

//Check if there is entry for this user in db
$query = 'SELECT password FROM users WHERE extension = "' . $caller_id . '"';
$existing_user = $db->query($query);
if (!$existing_user) {
    $agi->verbose('Query failed');
    goto end;
}
if ($existing_user->num_rows > 0) {
    $pw = $agi->get_data('agent-pass', -1)['result'];
    $user_pw = $existing_user->fetch_assoc()['password'];

    //Just leave if login failed, alternatively loop until we hit the pw
    if ($pw !== $user_pw) {
        $agi->stream_file('vm-incorrect');
        exit();
    }
}

$agi->stream_file('hello-world');

for (;;) {
    $agi->stream_file('dir-welcome');
    $choice = $agi->get_data('conf-getchannel', -1, 1)['result'];

    switch ($choice) {
        case 1:
            $ext = $agi->get_data('vm-enter-num-to-call', -1)['result'];
            if ($ext === $caller_id) {
                //Not allowed to call myself, just go on
                $agi->stream_file('pbx-invalid');
            } else {
                $agi->stream_file('followme/pls-hold-while-try');
                // $status = $agi->exec_dial('PJSIP', $ext)['result']; //What if ext doesn't exist?
                $status = $agi->exec_dial('PJSIP', 'SOFTPHONE_B')['result']; //Call twinkle, test
                if ($status !== 200) {
                    $agi->verbose('Call failed!');
                    goto end;
                }
            }
            break;
        case 2:
            $pw = $agi->get_data('agent-pass', -1)['result'];
            if ($user_pw) {
                //This user already has an account, go on
                $query = 'SELECT 1 FROM users WHERE extension = "' . $caller_id . '" AND password = "' . $pw . '"';
                $match = $db->query($query);
                if ($match->num_rows === 0) {
                    $agi->stream_file('vm-invalidpassword');
                    continue;
                }

                //Double confirm new password before updating db
                $newPw = $agi->get_data('vm-newpassword', -1)['result'];
                $pw_confirm = $agi->get_data('vm-reenterpassword', -1)['result'];
                if ($newPw !== $pw_confirm) {
                    $agi->stream_file('vm-mismatch');
                    continue;
                }
            }
            //If first time around, create entry in db
            $query = $user_pw ? 'UPDATE users SET password = "' . $newPw . '" WHERE extension = "' . $caller_id . '"'
                : 'INSERT INTO users values ("' . $caller_id . '", "' . $pw . '")';
            $ret = $db->query($query);
            if (!$ret) {
                $agi->verbose('Query failed');
                goto end;
            }

            $agi->stream_file('vm-passchanged');
            break;
        case 3:
            //Repeat
            continue;
        case 9:
            //End
            goto end;
        default:
            //Bad choice
            $agi->stream_file('conf-errormenu');
            break;
    }
}

end:
$agi->stream_file('demo-thanks');
$agi->hangup();
?>
