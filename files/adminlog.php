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
    pagination($result['noofpages'], SITE_URL."/adminlog", $page, 10);
} else {
    $_SESSION['msg'] = "Access Denied: You need to be administrator to access that page.";
    redirectTo(SITE_URL);
}
?>
