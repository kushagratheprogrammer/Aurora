<?php
if (isset($_SESSION['loggedin']) && $_SESSION['team']['status'] == 'Admin') {
    ?>
    <center><h1>Request Logs</h1></center>
    <?php
    if (isset($_GET['page']))
        $page = $_GET['page'];
    else
        $page = 1;
    $body = "from logs order by time desc";
    $result = DB::findAllWithCount("select *", $body, $page, 10);
    $data = $result['data'];
    echo "<table class='table table-condensed table-hover'><tr><th>Time</th><th>IP</th><th>Session</th><th>Request</th></tr>";
    foreach ($data as $row) {
        echo "<tr><td>" . date("d/m/Y h:i:sa", $row['time']) . "</td><td>$row[ip]</td><td><pre>$row[tid]</pre></td><td><pre>$row[request]</pre></td></tr>";
    }
    echo "</table>";
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
                    <li><a href="<?php echo SITE_URL . "/adminlog&page=" . ($page - 1); ?>">Prev</a></li>
                    <?php
                }
                for ($i = $start; $i <= $end; $i++) {
                    ?>
                    <li <?php echo ($i == $page) ? ("class='disabled'") : (''); ?>><a href="<?php echo ($i != $page) ? (SITE_URL . "/adminlog&page=" . $i) : ("#"); ?>"><?php echo $i; ?></a></li>
                    <?php
                }
                if ($page < $result['noofpages']) {
                    ?>
                    <li><a href="<?php echo SITE_URL . "/adminlog&page=" . ($page + 1); ?>">Next</a></li>
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
