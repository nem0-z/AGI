<?php

class App
{
    function getCallerID() {
        global $agi;

        return $agi->parse_callerid()['username'];
    }

    function connectDB($host, $user, $pw, $db) {
        $conn = new mysqli($host, $user, $pw, $db);
        if (mysqli_connect_error())
            die("Couldn't connect to database");
        return $conn;
    }

    function currentUser() {
        global $callerID, $db;

        $query = 'SELECT password FROM users WHERE extension = "' . $callerID . '"';
        $existingUser = $db->query($query);
        if (!$existingUser)
            die('Query failed');

        if ($existingUser->num_rows > 0)
            return $existingUser->fetch_assoc()['password'];

        return false;
    }

    function authenticate($matchedPw) {
        //In case currentUser function returned false aka this user is logging in for the first time
        if (!$matchedPw)
            return;

        global $agi;

        $pw = $agi->get_data('agent-pass', -1)['result'];
        //Just leave if login failed, alternatively loop until we hit the pw
        if ($pw !== $matchedPw) {
            $agi->stream_file('vm-incorrect');
            die();
        }
    }

    function getChoice() {
        global $agi;

        return $agi->get_data('conf-getchannel', -1, 1)['result'];
    }

    function requestCall() {
        global $agi, $callerID;

        $ext = $agi->get_data('vm-enter-num-to-call', -1)['result'];

        $agi->stream_file($ext === $callerID ? 'pbx-invalid' : 'followme/pls-hold-while-try');
        if ($ext !== $callerID) {
            $status = $agi->exec_dial('PJSIP', $ext)['result'];
            if ($status !== 200)
                return -1;
        }
        return 0;
    }

    function changePassword() {
        global $agi, $db, $callerID, $userPw;

        $pw = $agi->get_data('agent-pass', -1)['result'];
        if ($userPw) {
            //This user already has an account, go on
            $query = 'SELECT 1 FROM users WHERE extension = "' . $callerID . '" AND password = "' . $pw . '"';
            $match = $db->query($query);
            if (!$match->num_rows) {
                $agi->stream_file('vm-invalidpassword');
                return -1;
            }

            //Double confirm new password before updating db
            $newPw = $agi->get_data('vm-newpassword', -1)['result'];
            $newPwConfirm = $agi->get_data('vm-reenterpassword', -1)['result'];
            if ($newPw !== $newPwConfirm) {
                $agi->stream_file('vm-mismatch');
                return -1;
            }
        }
        //If first time around, create entry in db, else update existing row in table
        if ($userPw) {
            $query = 'UPDATE users SET password = "' . $newPw . '" WHERE extension = "' . $callerID . '"';
            $userPw = $newPw;
        } else {
            $query =  'INSERT INTO users values ("' . $callerID . '", "' . $pw . '")';
            $userPw = $pw;
        }

        if (!$db->query($query))
            die('Query failed');

        $agi->stream_file('vm-passchanged');

        return 0;
    }
}
