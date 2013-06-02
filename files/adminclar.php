<?php
if (isset($_SESSION['loggedin']) && $_SESSION['team']['status'] == 'Admin') {
    if (isset($_GET['page']))
        $page = $_GET['page'];
    else
        $page = 1;
    echo "<h1>Clarifications</h1>";
    $query = 'from clar order by time desc';
    $data = DB::findAllWithCount("select * ", $query, $page, 10);
    $result = $data['data'];
    foreach ($result as $row) {
        $query = "select teamname from teams where tid='$row[tid]'";
        $team = DB::findOneFromQuery($query);
        if ($row['pid'] != '0') {
            $query = "select name, code from problems where pid='$row[pid]'";
            $prob = DB::findOneFromQuery($query);
            $prob['code'] = "problems/" . $prob['code'];
        } else {
            $prob['name'] = 'Feedback';
            $prob['code'] = 'contact';
        }
        echo "<a href='" . SITE_URL . "/teams/$team[teamname]'>$team[teamname]</a> (<a href='" . SITE_URL . "/$prob[code]'>$prob[name]</a>):<br/>
                <b>Q. $row[query]</b><br/>";
        if ($row['reply'] != "") {
            echo "A. $row[reply]<br/><br/>";
        }
        echo "<form method='post' action='" . SITE_URL . "/process.php'>";
        echo "Access: <select name='access'><option value='public' " . (($row['access'] == "public") ? ("selected='selected' ") : ("")) . ">Public</option><option value='deleted' " . (($row['access'] == "deleted") ? ("selected='selected' ") : ("")) . ">Deleted</option></select><br/>";
        echo "<input type='hidden' name='tid' value='$row[tid]' /><input type='hidden' name='pid' value='$row[pid]' /><input type='hidden' name='time' value='$row[time]' />
<textarea name='reply' style='width: 450px; height: 100px;'>$row[reply]</textarea><br/>
<input type='submit' class='btn btn-primary' name='clarreply' value='Reply / Change Reply'/>
</form><hr/>";
    }
    if ($data['noofpages'] > 1) {
        if ($page - 5 > 0)
            $start = $page - 5;
        else
            $start = 1;
	if($data['noofpages'] >= $start + 10) 
		$end = $start + 10;
	else
		$end = $data['noofpages'];
        ?>
        <div class ="pagination pagination-centered">
            <ul>        
                <?php if ($page > 1) { ?>
                    <li><a href="<?php echo SITE_URL . "/adminclar&page=" . ($page - 1); ?>">Prev</a></li>
                    <?php
                }
                for ($i = $start; $i <= $end; $i++) {
                    ?>
                    <li <?php echo ($i == $page) ? ("class='disabled'") : (''); ?>><a href="<?php echo ($i != $page) ? (SITE_URL . "/adminclar&page=" . $i) : ("#"); ?>"><?php echo $i; ?></a></li>
                    <?php
                }
                if ($page < $data['noofpages']) {
                    ?>
                    <li><a href="<?php echo SITE_URL . "/adminclar&page=" . ($page + 1); ?>">Next</a></li>
                <?php } ?>
            </ul>
        </div>
        <?php
    }
} else {
    $_SESSION['msg'] = "Access Denied: You need to be administrator to access that page.";
    redirectTo(SITE_URL);
}
?>
