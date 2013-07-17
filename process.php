<?php
require_once 'config.php';
function customhash($str){
    return $str; // To help change the hashing for password saving if needed.
}
$query = "select value from admin where variable='mode'";
$judge = DB::findOneFromQuery($query);
$query = "insert into logs value ('".time()."', '$_SERVER[REMOTE_ADDR]', '".  addslashes(print_r($_SESSION, TRUE))."', '".addslashes(print_r($_REQUEST, TRUE))."' )";
DB::query($query);
if ($judge['value'] != "Lockdown" || (isset($_SESSION['loggedin']) && $_SESSION['team']['status'] == 'Admin') || isset($_POST['login'])) {
// ------------------ LOGIN ------------------- //
    if (isset($_POST['login'])) {
        if (!isset($_POST['teamname']) || $_POST['teamname'] == '') {
            $_SESSION['msg'] = "Teamname missing";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        } else if (!isset($_POST['password']) || $_POST['password'] == '') {
            $_SESSION['msg'] = "Teamname missing";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        } else {
            $_POST['teamname'] = addslashes($_POST['teamname']);
            $_POST['password'] = customhash(addslashes($_POST['password']));
            $query = "select * from teams where teamname  = '$_POST[teamname]' and pass = '$_POST[password]'";
            $res = DB::findOneFromQuery($query);
            if ($res && ($res['status'] == 'Normal' || $res['status'] == 'Admin')) {
                $save = $_SESSION;
                session_destroy();
                session_regenerate_id(true);
                session_start();
                $_SESSION = $save;
                $_SESSION['team']['id'] = $res['tid'];
                $_SESSION['team']['name'] = $res['teamname'];
                $_SESSION['loggedin'] = "true";
                $_SESSION['team']['status'] = $res['status'];
                redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
            } else if ($res) {
                $_SESSION['msg'] = "You can not log in as your current status is : $res[status]";
                redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
            } else {
                $_SESSION['msg'] = "Incorrect Username/Password";
                redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
            }
        }
// ---------------------- LOG OUT -------------------------- //
    } else if (isset($_GET['logout'])) {
        session_destroy();
        redirectTo(SITE_URL);
// ------------------ CODE SUBMISSION --------------------- //
    } else if (isset($_POST['submitcode'])) {
        $_SESSION['subcode'] = addslashes($_POST['sub']);
        /*
         * 
         * Different levels of check ... (quite a job!)
         * Lvl 0 - User is logged in
         * Lvl 1 - Judge Active or Passive
         * Lvl 2 - All the POST value are set
         * Lvl 3 - Max Input file size
         * Lvl 4 - Problem and language combination is allowed
         * Lvl 5 - Only CQM's submissions are accepted in Active mode
         * Lvl 6 - Only Practice submissions are accepted in Passive mode
         * Lvl 7 - Only Active problem are accepted
         * Also Admin can by-pass lvl 1 and lvl 7 checks.
         * 
         */
        if (isset($_SESSION['loggedin'])) {     // Lvl 0
            $query = "select * from admin where variable ='mode' or variable ='endtime' or variable='ip' or variable ='port'";
            $check = DB::findAllFromQuery($query);
            $admin = Array();
            foreach ($check as $row) {
                $admin[$row['variable']] = $row['value'];
            }
            if ($admin['mode'] == 'Passive' || ($admin['mode'] == 'Active' && $admin['endtime'] >= time()) || $_SESSION['team']['status'] == 'Admin') { // Lvl 1
                $allowed = Array('application/octet-stream', 'text/x-csrc', 'text/x-c++src', 'text/x-csharp', 'text/x-java', 'text/javascript', 'text/x-pascal', 'text/x-perl', 'text/x-php', 'text/x-python', 'text/x-ruby', 'text/plain');
                if ((isset($_POST['lang']) && $_POST['lang'] != "") && ($_FILES['code_file']['size'] > 0 || (isset($_POST['sub']) && $_POST['sub'] != "")) && (isset($_POST['probcode']) && $_POST['probcode'] != "")) {  // Lvl 2
                    if ($_FILES['code_file']['size'] > 0 && $_FILES['code']['error'] == 0 && in_array($_FILES['code_file']['type'], $allowed)) {
                        $sourcecode = addslashes(file_get_contents($_FILES['code_file']['tmp_name']));
                    } else {
                        $sourcecode = addslashes($_POST['sub']);
                    }
                    $query = "select pid, status, contest, maxfilesize from problems where languages like '%$_POST[lang]%' and code = '$_POST[probcode]'";
                    $res = DB::findOneFromQuery($query);
                    if (strlen(stripcslashes($sourcecode)) <= $res['maxfilesize']) { // Lvl 3
                        if ($res) { // Lvl 4
                            if ($admin['mode'] == 'Active' && $admin['endtime'] >= time() && $res['contest'] == 'practice') { // Lvl 5
                                $_SESSION['msg'] = "Submissions are only accepted for CQM question right now. Come back later.";
                                redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
                            } else if ($admin['mode'] == 'Passive' && $res['contest'] == 'contest') { // Lvl 6
                                $_SESSION['msg'] = "Submissions are only accepted for Practice question right now.";
                                redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
                            } else if ($res['status'] == 'Active' || $_SESSION['team']['status'] == 'Admin') { // Lvl 7
                                $submittime = time();
                                $query = "INSERT INTO runs (pid,tid,language,name,code,access,submittime) VALUES ('$res[pid]', '" . $_SESSION['team']['id'] . "', '$_POST[lang]', 'Main', '$sourcecode', 'private', '" . $submittime . "')";
                                $res2 = DB::query($query);
                                $query = "select rid from runs where tid = " . $_SESSION['team']['id'] . " and pid = $res[pid] and submittime = $submittime";
                                $result = DB::findOneFromQuery($query);
                                if ($result) {
                                    $rid = $result['rid'];
                                    unset($_SESSION['subcode']);
                                    $_SESSION['msg'] = "Problem submitted successfully. If your problem is not judged then contact admin.";
                                    $client = stream_socket_client($admin['ip'] . ":" . $admin['port'], $errno, $errorMessage);
                                    if ($client === false) {
                                        $_SESSION["msg"] .= "<br/>Cannot connect to Judge: Contact Admin";
                                    }
                                    fwrite($client, $rid);
                                    fclose($client);
                                    redirectTo(SITE_URL . "/viewsolution/" . $rid);
                                } else {
                                    $_SESSION['msg'] = "Some error occured during submission. If the problem continues contact Admin";
                                    redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
                                }
                            } else {
                                $_SESSION['msg'] = "Problem is not active for submissions.";
                                redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
                            }
                        } else {
                            $_SESSION['msg'] = "Either the problem does not exsits or the language is not allowed.";
                            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
                        }
                    } else {
                        $_SESSION['msg'] = "Submitted code exceeds size limits.";
                        redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
                    }
                } else {
                    $_SESSION['msg'] = "You missed some necessary values.";
                    redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
                }
            } else {
                $_SESSION['msg'] = "You cannot submit at this time.";
                redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
            }
        } else {
            $_SESSION['msg'] = "You should be logged in to make a submission.";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        }
// -------------------------------- REGISTER ---------------------------- //
    } else if (isset($_POST['register'])) {
        if ((isset($_POST['teamname']) && $_POST['teamname'] != "") && (isset($_POST['password']) && $_POST['password'] != "") && (isset($_POST['repassword']) && $_POST['repassword'] != "") && (isset($_POST['name1']) && $_POST['name1'] != "") && (isset($_POST['roll1']) && $_POST['roll1'] != "") && (isset($_POST['branch1']) && $_POST['branch1'] != "") && (isset($_POST['email1']) && $_POST['email1'] != "") && (isset($_POST['phno1']) && $_POST['phno1'] != "")) {
            if (preg_match("/^[a-zA-Z0-9_@]+$/", $_POST['teamname'], $match) && $match[0] == $_POST['teamname']) {
                if (addslashes($_POST['password']) == addslashes($_POST['repassword'])) {
                    $query = "select * from teams where teamname='" . addslashes($_POST['teamname']) . "'";
                    $res = DB::findOneFromQuery($query);
                    if ($res == NULL) {
                        $query = "Insert into teams (teamname, pass, status, name1, roll1, branch1, email1, phone1, name2, roll2, branch2, email2, phone2, name3, roll3, branch3, email3, phone3, score, penalty, gid) 
                        values ('" . addslashes($_POST['teamname']) . "', '" . customhash(addslashes($_POST['password'])) . "', 'Normal', '" . addslashes($_POST['name1']) . "', '" . addslashes($_POST['roll1']) . "','" . addslashes($_POST['branch1']) . "','" . addslashes($_POST['email1']) . "','" . addslashes($_POST['phno1']) . "','" . addslashes($_POST['name2']) . "', '" . addslashes($_POST['roll2']) . "', '" . addslashes($_POST['branch2']) . "', '" . addslashes($_POST['email2']) . "', '" . addslashes($_POST['phno2']) . "', '" . addslashes($_POST['name3']) . "', '" . addslashes($_POST['roll3']) . "', '" . addslashes($_POST['branch3']) . "','" . addslashes($_POST['email3']) . "','" . addslashes($_POST['phno3']) . "','0','0','" . addslashes($_POST['group']) . "')";
                        $res = DB::query($query);
                        $query = "select * from teams where teamname='" . addslashes($_POST['teamname']) . "'";
                        $res = DB::findOneFromQuery($query);
                        if ($res) {
                            $_SESSION['msg'] = "Team successfully registered.";
                            redirectTo(SITE_URL);
                        } else {
                            $_SESSION['reg'] = $_POST;
                            //$_SESSION['msg'] = "Insert into teams (teamname, pass, status, name1, roll1, branch1, email1, phone1, name2, roll2, branch2, email2, phone2, name3, roll3, branch3, email3, phone3, score, penalty) 
                            //values ('" . addslashes($_POST['teamname']) . "', '" . addslashes($_POST['password']) . "', 'Normal', '" . addslashes($_POST['name1']) . "', '" . addslashes($_POST['roll1']) . "','" . addslashes($_POST['branch1']) . "','" . addslashes($_POST['email1']) . "','" . addslashes($_POST['phno1']) . "','" . addslashes($_POST['name2']) . "', '" . addslashes($_POST['roll2']) . "', '" . addslashes($_POST['branch2']) . "', '" . addslashes($_POST['email2']) . "', '" . addslashes($_POST['phno2']) . "', '" . addslashes($_POST['name3']) . "', '" . addslashes($_POST['roll3']) . "', '" . addslashes($_POST['branch3']) . "','" . addslashes($_POST['email3']) . "','" . addslashes($_POST['phno3']) . "','0','0')";
                            $_SESSION['msg'] = "Some error occured. Try again. If the problem continues contact admin.";
                            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
                        }
                    } else {
                        $_SESSION['reg'] = $_POST;
                        $_SESSION['msg'] = "Teamname already registered.";
                        redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
                    }
                } else {
                    $_SESSION['reg'] = $_POST;
                    $_SESSION['msg'] = "Password mismatch.";
                    redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
                }
            } else {
                $_SESSION['reg'] = $_POST;
                $_SESSION['msg'] = "Team name should contain only alphabets numbers @ and _.";
                redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
            }
        }
// ------------------------------- ACCOUNT UPDATE ---------------------------- //
    } else if (isset($_POST['update'])) {
        if (isset($_SESSION['loggedin'])) {
            if (isset($_POST['oldpass']) && isset($_POST['pass1']) && isset($_POST['repass']) && $_POST['pass1'] != "") {
                if ($_POST['pass1'] == $_POST['repass']) {
                    $query = "select * from teams where tid ='" . $_SESSION['team']['id'] . "' and pass ='" . customhash(addslashes($_POST['oldpass'])) . "'";
                    $res = DB::findOneFromQuery($query);
                    if ($res) {
                        $query = "update teams set pass = '" . customhash(addslashes($_POST['pass1'])) . "' " . ((isset($_POST['group']) && $_POST['group'] != "") ? (", gid='" . addslashes($_POST['group']) . "' ") : ("")) . "where tid='" . $_SESSION['team']['id'] . "'";
                        $res = DB::query($query);
                        $_SESSION['msg'] = "Password Updated";
                        redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
                    } else {
                        $_SESSION['msg'] = "Old password incorrect.";
                        redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
                    }
                } else {
                    $_SESSION['msg'] = "Password do not match or password is empty.";
                    redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
                }
            } else if (isset($_POST['group'])) {
                if ($_POST['group'] != '') {
                    $query = "update teams set gid='" . addslashes($_POST['group']) . "' where tid='" . $_SESSION['team']['id'] . "'";
                    $res = DB::query($query);
                    $_SESSION['msg'] = "Group Updated";
                    redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
                } else {
                    $_SESSION['msg'] = "Incorrect group.";
                    redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
                }
            } else {
                $_SESSION['msg'] = "Not enough values.";
                redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
            }
        } else {
            $_SESSION['msg'] = "You should be logged in.";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        }
    } else if (isset($_POST['clar'])) {
        if (isset($_SESSION['loggedin'])) {
            if (isset($_POST['query']) && $_POST['query'] != "") {
                $query = "Insert into clar (time, pid, tid, query, access) 
                values ('" . time() . "', '" . addslashes($_POST['pid']) . "', '" . $_SESSION['team']['id'] . "', '" . addslashes($_POST['query']) . "', 'public')";
                $res = DB::query($query);
                $_SESSION['msg'] = "Clarification posted... we will reply soon.";
                redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
            } else {
                $_SESSION['msg'] = "Empty Query :(";
                redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
            }
        } else {
            $_SESSION['msg'] = "You should be logged in.";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        }
    } else if (isset($_SESSION['loggedin']) && $_SESSION['team']['status'] == 'Admin') {  // request only admin can make
        if (isset($_POST['judgeupdate'])) {
            $admin = Array();
            $admin['mode'] = $_POST['mode'];
            if ($admin['mode'] == "Active" && $admin['endtime'] == "") {
                $admin['endtime'] = (time() + 180 * 60);
            } else {
                $admin['endtime'] = (time() + $_POST['endtime'] * 60);
            }
            $admin['penalty'] = $_POST['penalty'];
            foreach ($admin as $key => $val) {
                $query = "update admin set value = '$val' where variable = '$key'";
                DB::query($query);
            }
            $_SESSION['msg'] = "Judge Updated.";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        } else if (isset($_POST['addproblem'])) {
            $prob = Array();
            $prob['name'] = addslashes($_POST['name']);
            $prob['code'] = addslashes($_POST['code']);
            $prob['score'] = addslashes($_POST['score']);
            $prob['type'] = addslashes($_POST['type']);
            $prob['pgroup'] = addslashes($_POST['pgroup']);
            $prob['contest'] = addslashes($_POST['contest']);
            $prob['timelimit'] = addslashes($_POST['timelimit']);
            $prob['status'] = addslashes($_POST['status']);
            $prob['languages'] = addslashes($_POST['languages']);
            $prob['displayio'] = addslashes($_POST['displayio']);
            $prob['maxfilesize'] = addslashes($_POST['maxfilesize']);
            $prob['statement'] = addslashes(file_get_contents($_FILES['statement']['tmp_name']));
            $prob['input'] = addslashes(file_get_contents($_FILES['input']['tmp_name']));
            $prob['output'] = addslashes(addslashes(file_get_contents($_FILES['output']['tmp_name'])));
            if ($_FILES['image']['size'] > 0) {
                $prob['image'] = base64_encode(file_get_contents($_FILES['image']['tmp_name']));
            }
            $query = "insert into problems (" . implode(array_keys($prob), ",") . ") values ('" . implode(array_values($prob), "','") . "')";
            DB::query($query);
            $_SESSION['msg'] = "Problem Added.";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        } else if (isset($_POST['updateproblem'])) {
            $prob = Array();
            $pid = addslashes($_POST['pid']);
            $prob['name'] = addslashes($_POST['name']);
            $prob['code'] = addslashes($_POST['code']);
            $prob['score'] = addslashes($_POST['score']);
            $prob['type'] = addslashes($_POST['type']);
            $prob['pgroup'] = addslashes($_POST['pgroup']);
            $prob['contest'] = addslashes($_POST['contest']);
            $prob['timelimit'] = addslashes($_POST['timelimit']);
            $prob['status'] = addslashes($_POST['status']);
            $prob['languages'] = addslashes($_POST['languages']);
            $prob['displayio'] = addslashes($_POST['displayio']);
            $prob['maxfilesize'] = addslashes($_POST['maxfilesize']);
            $prob['statement'] = addslashes($_POST['statement']);
            if ($_FILES['input']['size'] > 0) {
                $prob['input'] = addslashes(file_get_contents($_FILES['input']['tmp_name']));
            }
            if ($_FILES['output']['size'] > 0) {
                $prob['output'] = addslashes(addslashes(file_get_contents($_FILES['output']['tmp_name'])));
            }
            if ($_FILES['image']['size'] > 0) {
                $prob['image'] = base64_encode(file_get_contents($_FILES['image']['tmp_name']));
            }
            foreach ($prob as $key => $val) {
                $query = "update problems set $key = '$val' where pid=$pid";
                DB::query($query);
            }
            $_SESSION['msg'] = "Problem Updated.";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        } else if (isset($_POST['addcontest'])) {
            $newcontest['code'] = addslashes($_POST['code']);
            $newcontest['name'] = addslashes($_POST['name']);
            $date = new DateTime($_POST['starttime']);
            $newcontest['starttime'] = $date->getTimestamp();
            $date = new DateTime($_POST['endtime']);
            $newcontest['endtime'] = $date->getTimestamp();
            $newcontest['announcement'] = addslashes($_POST['announcement']);
            $query = "insert into contest (" . implode(array_keys($newcontest), ",") . ") values ('" . implode(array_values($newcontest), "','") . "')";
            DB::query($query);
            $_SESSION['msg'] = "Contest Added.";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        } else if (isset($_POST['updatecontest'])) {
            $id = addslashes($_POST['id']);
            $newcontest['code'] = addslashes($_POST['code']);
            $newcontest['name'] = addslashes($_POST['name']);
            $date = new DateTime($_POST['starttime']);
            $newcontest['starttime'] = $date->getTimestamp();
            $date = new DateTime($_POST['endtime']);
            $newcontest['endtime'] = $date->getTimestamp();
            $newcontest['announcement'] = addslashes($_POST['announcement']);
            foreach ($newcontest as $key => $val) {
                $query = "update contest set $key = '$val' where id=$id";
                DB::query($query);
            }
            $_SESSION['msg'] = "Contest Updated.";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        } else if (isset($_POST['updateteam'])) {
            $tid = addslashes($_POST['tid']);
            $team['teamname'] = $_POST['teamname'];
            if (preg_match("/^[a-zA-Z0-9_@]+$/", $team['teamname'], $match) && $match[0] == $team['teamname']) {
                $team['status'] = addslashes($_POST['status']);
                $team['name1'] = addslashes($_POST['name1']);
                $team['roll1'] = addslashes($_POST['roll1']);
                $team['branch1'] = addslashes($_POST['branch1']);
                $team['email1'] = addslashes($_POST['email1']);
                $team['phone1'] = addslashes($_POST['phone1']);
                $team['name2'] = addslashes($_POST['name2']);
                $team['roll2'] = addslashes($_POST['roll2']);
                $team['branch2'] = addslashes($_POST['branch2']);
                $team['email2'] = addslashes($_POST['email2']);
                $team['phone2'] = addslashes($_POST['phone2']);
                $team['name3'] = addslashes($_POST['name3']);
                $team['roll3'] = addslashes($_POST['roll3']);
                $team['branch3'] = addslashes($_POST['branch3']);
                $team['email3'] = addslashes($_POST['email3']);
                $team['phone3'] = addslashes($_POST['phone3']);
                foreach ($team as $key => $val) {
                    $query = "update teams set $key = '$val' where tid=$tid";
                    DB::query($query);
                }
                $_SESSION['msg'] = "Team Updated.";
                redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
            } else {
                $_SESSION['msg'] = "Teamname didn't satisfy the criteria.";
                redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
            }
        } else if (isset($_POST['rejudge'])) {
            $query = "select * from admin where variable ='mode' or variable ='endtime' or variable='ip' or variable ='port'";
            $check = DB::findAllFromQuery($query);
            $admin = Array();
            foreach ($check as $row) {
                $admin[$row['variable']] = $row['value'];
            }
            $query = "update runs set result = NULL, time = NULL where ";
            if (isset($_POST['rid'])) {
                $query .= "rid = " . addslashes($_POST['rid']);
            }
            if (isset($_POST['tid']) && isset($_POST['rid'])) {
                $query .= " and tid=" . addslashes($_POST['tid']);
            } else if (isset($_POST['tid'])) {
                $query .= "tid=" . addslashes($_POST['tid']);
            }
            if (isset($_POST['pid']) && (isset($_POST['rid']) || isset($_POST['tid']))) {
                $query .= " and pid=" . addslashes($_POST['pid']);
            } else if (isset($_POST['pid'])) {
                $query .= "pid=" . addslashes($_POST['pid']);
            }
            DB::query($query);
            $client = stream_socket_client($admin['ip'] . ":" . $admin['port'], $errno, $errorMessage);
            if ($client === false) {
                $_SESSION["msg"] .= "<br/>Cannot connect to Judge: Contact Admin";
            }
            fwrite($client, 'rejudge');
            fclose($client);
            $_SESSION['msg'] .= "Problem(s) set to rejudge.";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        } else if (isset ($_POST['dq'])){
            $query = "update runs set result = 'DQ' where rid = ".addslashes($_POST['rid']);
            DB::query($query);
            $_SESSION['msg'] = "Solution Disqualified.";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        } else if(isset ($_POST['runaccess'])){
            $query = "update runs set access = '".  addslashes($_POST['access'])."' where rid = ".addslashes($_POST['rid']);
            DB::query($query);
            $_SESSION['msg'] = "Access Updated.";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        } else if(isset ($_POST['judgesocket'])){
            $_POST['ip'] = addslashes($_POST['ip']);
            $query = "update admin set value='$_POST[ip]' where variable='ip'";
            DB::query($query);
            $_POST['port'] = addslashes($_POST['port']);
            $query = "update admin set value='$_POST[port]' where variable='port'";
            DB::query($query);
            $_SESSION['msg'] = "Socket Updated.";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        } else if(isset ($_POST['judgenotice'])){
            $_POST['notice'] = addslashes($_POST['notice']);
            $query = "update admin set value='$_POST[notice]' where variable='notice'";
            DB::query($query);
            $_SESSION['msg'] = "Notice Updated.";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        } else if (isset ($_POST['clarreply'])){
            $tid = addslashes($_POST['tid']);
            $pid = addslashes($_POST['pid']);
            $time = addslashes($_POST['time']);
            $reply = addslashes($_POST['reply']);
            $access = addslashes($_POST['access']);
            $query = "update clar set reply='$reply', access='$access' where tid=$tid and pid=$pid and time=$time";
            DB::query($query);
            $_SESSION['msg'] = "Reply Updated.";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        } else if (isset ($_POST['addgroup'])){
            $groupname = addslashes($_POST['groupname']);
            $query = "insert into groups (groupname) values ('$groupname')";
            DB::query($query);
            $_SESSION['msg'] = "Group Created.";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        } else if (isset ($_POST['updategroup'])){
            $gid = addslashes($_POST['gid']);
            $groupname = addslashes($_POST['groupname']);
            $query = "update groups set groupname ='$groupname' where gid=$gid";
            DB::query($query);
            $_SESSION['msg'] = "Group Updated.";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        } else if (isset ($_POST['deletegroup'])){
            $gid = addslashes($_POST['gid']);
            $query = "delete from groups where gid=$gid";
            DB::query($query);
            $_SESSION['msg'] = "Group Deleted.";
            redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
        }
    }
} else {
    $_SESSION['msg'] = "Judge is in Lockdown mode and so no requests are being processed.";
    redirectTo("http://" . $_SERVER['HTTP_HOST'] . $_SESSION['url']);
}
?>
