<?php
if ($judge['value'] != "Lockdown" || (isset($_SESSION['loggedin']) && $_SESSION['team']['status'] == 'Admin')) {
    ?>
    <center><h1>Contact Us</h1></center>
    <?php
    if (isset($_GET['page']))
        $page = $_GET['page'];
    else
        $page = 1;
    $select = "Select *";
    $query = "from clar where access = 'Public' and pid='0' order by time desc";
    $result = DB::findAllWithCount($select, $query, $page, 10);
    $data = $result['data'];
    foreach ($data as $row) {
        $query = "Select name, code from problems where pid = $row[pid]";
        $prob = DB::findOneFromQuery($query);
        echo "<a href='" . SITE_URL . "/problems/$prob[code]'>$prob[name]</a><br/><b>Q. $row[query]</b><br/>" . (($row['reply'] != "") ? ("A. $row[reply]<br/>") : (''));
    }
    if (isset($_SESSION['loggedin'])) {
        ?>
        <hr/>
        <h4>Please feel free to post your queries/doubts/praise/criticism/feedback.<br/>We will reply as soon as we can!</h4>
        <form action="<?php echo SITE_URL; ?>/process.php" method="post">
            <input type="hidden" value="0" name="pid" />
            <textarea style="width: 500px; height: 200px;" name="query"></textarea><br/>
            <input name="clar" type="submit" class="btn btn-primary" />
        </form>
        <?php
    }
    if ($result['noofpages'] > 1) {
        if ($page - 5 > 0)
            $start = $page - 5;
        else
            $start = 1;
	if($result['noofpages'] >= $start + 10) 
		$end = $start + 10;
	else
		$end = $result['noofpages'];
        ?>
        <div class ="pagination pagination-centered">
            <ul>        
                <?php if ($page > 1) { ?>
                    <li><a href="<?php echo SITE_URL . "/clarifications&page=" . ($page - 1); ?>">Prev</a></li>
                    <?php
                }
                for ($i = $start; $i <= $end; $i++) {
                    ?>
                    <li <?php echo ($i == $page) ? ("class='disabled'") : (''); ?>><a href="<?php echo ($i != $page) ? (SITE_URL . "/clarifications&page=" . $i) : ("#"); ?>"><?php echo $i; ?></a></li>
                    <?php
                }
                if ($page < $result['noofpages']) {
                    ?>
                    <li><a href="<?php echo SITE_URL . "/clarifications&page=" . ($page + 1); ?>">Next</a></li>
                <?php } ?>
            </ul>
        </div>
        <?php
    }
} else {
    echo "<br/><br/><br/><div style='padding: 10px;'><h1>Lockdown Mode :(</h1>This feature is now offline as Judge is in Lockdown mode.</div><br/><br/><br/>";
}
?>
