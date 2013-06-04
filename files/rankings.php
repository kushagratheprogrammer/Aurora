<?php
if ($judge['value'] != "Lockdown" || (isset($_SESSION['loggedin']) && $_SESSION['team']['status'] == 'Admin')) {
    ?>
    <center><h1>Rankings</h1></center>
    <?php
    $query = "select * from groups";
    $result = DB::findAllFromQuery($query);
    $group = Array();
    foreach ($result as $row) {
        $group[$row['gid']] = $row['groupname'];
    }
    if (isset($_GET['page']))
        $page = $_GET['page'];
    else
        $page = 1;
    $select = "SELECT * ";
    $body = " FROM teams WHERE status='Normal' ORDER BY score DESC, penalty ASC";
    $result = DB::findAllWithCount($select, $body, $page, 20);
    $data = $result['data'];
    $i = 20 * ($page - 1) + 1;
    echo "<table class='table table-hover'><tr><th>Rank</th><th>Team Name</th><th>Team Group</th><th>Problems Solved / Attempted</th><th>Score</th></tr>";
    foreach ($data as $row) {
        $query = "SELECT (SELECT count(distinct runs.pid) FROM runs,problems WHERE runs.tid='$row[tid]' and runs.result='AC' and runs.pid=problems.pid and problems.status='Active' and problems.contest='contest') as ac, (SELECT count(distinct runs.pid) FROM runs,problems WHERE runs.tid='$row[tid]' and runs.pid=problems.pid and problems.status='Active' and problems.contest='contest') as tot";
        $subs = DB::findOneFromQuery($query);
        echo "<tr><td>" . $i++ . "</td><td><a href='" . SITE_URL . "/teams/$row[teamname]'>$row[teamname]</a></td><td>" . $group[$row['gid']] . "</td><td>$subs[ac]/$subs[tot]</td><td>$row[score]</td></tr>";
    }
    echo "</table>";
    pagination($result['noofpages'], SITE_URL."/rankings", $page, 10);
} else {
    echo "<br/><br/><br/><div style='padding: 10px;'><h1>Lockdown Mode :(</h1>This feature is now offline as Judge is in Lockdown mode.</div><br/><br/><br/>";
}
?>
