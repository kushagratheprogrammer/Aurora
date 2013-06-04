<?php
if ($judge['value'] != "Lockdown" || (isset($_SESSION['loggedin']) && $_SESSION['team']['status'] == 'Admin')) {
    if (isset($_GET['code'])) {
        $_GET['code'] = addslashes($_GET['code']);
        ?>
        <center><h1>Submissions</h1></center>
        <?php
        if (isset($_GET['page']))
            $page = $_GET['page'];
        else
            $page = 1;
        if (isset($_SESSION['loggedin']) && $_SESSION['team']['status'] == 'Admin') {
            $query = "select tid from teams where teamname = '$_GET[code]'";
            $push = DB::findOneFromQuery($query);
            $tid = $push['tid'];
            echo "<center><form method='post' action='" . SITE_URL . "/process.php'>
            <input type='hidden' name='tid' value='$tid' />
            <input type='submit' name='rejudge' class='btn btn-danger' value='Rejudge All Selected Submisssions'/>
            </form></center>";
        }
        $select = "Select *";
        $query = "from runs where access!='deleted' and tid in (SELECT tid FROM teams WHERE teamname='$_GET[code]') AND pid in (SELECT pid FROM problems WHERE status='Active') order by rid desc";
        $result = DB::findAllWithCount($select, $query, $page, 25);
        $data = $result['data'];
        echo "<table class='table table-hover'><tr><th>Run ID</ht><th>Team</th><th>Problem</th><th>Language</th><th>Time</th><th>Result</th><th>Options</th></tr>";
        foreach ($data as $row) {
            $team = DB::findOneFromQuery("select teamname from teams where tid = $row[tid]");
            $prob = DB::findOneFromQuery("Select name, code from problems where pid = $row[pid]");
            echo "<tr" . (($row['result'] == "AC") ? (" class='success'>") : (">")) . "<td>" . (($row['access'] == 'public' || (isset($_SESSION['loggedin']) && ($_SESSION['team']['status'] == "Admin" || $_SESSION['team']['id'] == $row['tid']))) ? ("<a href='" . SITE_URL . "/viewsolution/$row[rid]'>$row[rid]</a>") : ("$row[rid]")) . "</td><td><a href='" . SITE_URL . "/teams/$team[teamname]'>$team[teamname]</a></td><td><a href='" . SITE_URL . "/problems/$prob[code]'>$prob[name]</a></td><td>$row[language]</td><td>$row[time]</td><td>$row[result]</td><td>" . (($row['access'] == 'public' || (isset($_SESSION['loggedin']) && ($_SESSION['team']['status'] == "Admin" || $_SESSION['team']['id'] == $row['tid']))) ? ("<a class='btn btn-primary' href='" . SITE_URL . "/viewsolution/$row[rid]'>Code</a>") : ("")) . "</td></tr>";
        }
        echo "</table>";
        pagination($result['noofpages'], SITE_URL."/submissions/$_GET[code]", $page, 10);   
    } else {
        ?>
        <center><h1>Submissions</h1></center>
        <?php
        if (isset($_GET['page']))
            $page = $_GET['page'];
        else
            $page = 1;
        $select = "Select *";
        if (isset($_SESSION['loggedin']) && $_SESSION['team']['status'] == 'Admin') {
            $query = "from runs where tid in (SELECT tid FROM teams WHERE status='Normal' OR status='Admin') AND pid in (SELECT pid FROM problems WHERE status='Active') order by rid desc";
        } else {
            $query = "from runs where access!='deleted' and tid in (SELECT tid FROM teams WHERE status='Normal' OR status='Admin') AND pid in (SELECT pid FROM problems WHERE status='Active') order by rid desc";
        }
        $result = DB::findAllWithCount($select, $query, $page, 25);
        $data = $result['data'];
        echo "<table class='table table-hover'><tr><th>Run ID</ht><th>Team</th><th>Problem</th><th>Language</th><th>Time</th><th>Result</th><th>Options</th></tr>";
        foreach ($data as $row) {
            $team = DB::findOneFromQuery("select teamname from teams where tid = $row[tid]");
            $prob = DB::findOneFromQuery("Select name, code from problems where pid = $row[pid]");
            echo "<tr" . (($row['result'] == "AC") ? (" class='success'>") : (">")) . "<td>" . (($row['access'] == 'public' || (isset($_SESSION['loggedin']) && ($_SESSION['team']['status'] == "Admin" || $_SESSION['team']['id'] == $row['tid']))) ? ("<a href='" . SITE_URL . "/viewsolution/$row[rid]'>$row[rid]</a>") : ("$row[rid]")) . "</td><td><a href='" . SITE_URL . "/teams/$team[teamname]'>$team[teamname]</a></td><td><a href='" . SITE_URL . "/problems/$prob[code]'>$prob[name]</a></td><td>$row[language]</td><td>$row[time]</td><td>$row[result]</td><td>" . (($row['access'] == 'public' || (isset($_SESSION['loggedin']) && ($_SESSION['team']['status'] == "Admin" || $_SESSION['team']['id'] == $row['tid']))) ? ("<a class='btn btn-primary' href='" . SITE_URL . "/viewsolution/$row[rid]'>Code</a>") : ("")) . "</td></tr>";
        }
        echo "</table>";
        pagination($result['noofpages'], SITE_URL."/submissions", $page, 10);
    }
} else {
    echo "<br/><br/><br/><div style='padding: 10px;'><h1>Lockdown Mode :(</h1>This feature is now offline as Judge is in Lockdown mode.</div><br/><br/><br/>";
}
?>
