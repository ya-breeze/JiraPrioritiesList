<!doctype html>

<html lang="en">
<head>
    <meta charset="utf-8"/>
    <title>jQuery UI Sortable - Default functionality</title>
    <link rel="stylesheet" href="http://code.jquery.com/ui/1.10.2/themes/smoothness/jquery-ui.css"/>
    <script src="http://code.jquery.com/jquery-1.9.1.js"></script>
    <script src="http://code.jquery.com/ui/1.10.2/jquery-ui.js"></script>
    <link rel="stylesheet" href="/resources/demos/style.css"/>
    <style>
        #sortable {
            list-style-type: none;
            margin: 0;
            padding: 0;
            width: 80%;
        }

        #sortable li {
            margin: 0 3px 3px 3px;
            padding: 0.4em;
            padding-left: 1.5em;
            font-size: 1.0em;
        }

        #sortable li span {
            position: absolute;
            margin-left: -1.3em;
        }
    </style>
    <script>
        $(function () {
            $("#sortable").sortable();
            $("#sortable").disableSelection();
        });
    </script>

    <script type="text/javascript">
        function saveIssues() {
            window.location.search = 'issues=' + serializeList($('#sortable'))
        }
        function update() {
            window.location.search = ""
        }

        function showIssues() {
            alert(serializeList($('#sortable')))
        }

        function serializeList(container) {
            var str = '';
            var n = 0;
            var els = container.find('li');
            for (var i = 0; i < els.length; ++i) {
                var el = els[i];
                var text = el.getElementsByTagName("a")[0].innerHTML;
                if (text == "No priority")
                    break;
                str = str + text + ",";
            }
            return str
        }

    </script>

</head>
<body>
<a href="javascript: saveIssues()">Save</a>
<a href="javascript: update()">Update</a>

<ul id="sortable">

    <?php
    class Issue
    {
        public $id = "";
        public $descr = "";
        public $priority = 1000000;
        public $priorityIcon = "";
        public $duedate = "";
        public $assignee = "";
        public $assigneeIcon = "";
        public $labels = "";
    };

    function request($project, &$issues, &$issuesList)
    {
        $output = array();
        // I was unable to use HttpRequest with basic auth at our server
        $json = exec("curl -D- -u username:password -X GET -H 'Content-Type: application/json' 'http://jira.teligent.ru/rest/api/latest/search?jql=project=" . $project . "%20AND%20status!=Resolved%20AND%20status!=Closed&maxResults=1000'", $output);
        //var_dump($json);
        //$json = file_get_contents("issues.json");
        $issuesJson = json_decode($json, TRUE);
        foreach ($issuesJson["issues"] as $issue) {
            if (strlen($issuesList) != 0)
                $issuesList .= ",";
            $issuesList .= "'" . $issue["key"] . "'";

            $i = new Issue();
            $i->id = trim($issue["key"]);
            $i->descr = $issue["fields"]["summary"];
            $i->priorityIcon = $issue["fields"]["priority"]["iconUrl"];
            $i->duedate = $issue["fields"]["duedate"];
            $i->assignee = $issue["fields"]["assignee"]["displayName"];
            $i->assigneeIcon = $issue["fields"]["assignee"]["avatarUrls"]["16x16"];
            $i->labels = $issue["fields"]["labels"];

            if ($issue["fields"]["status"]["name"] != "Resolved" and $issue["fields"]["status"]["name"] != "Closed" and strlen($i->id) > 0) {
                $issues[$issue["key"]] = $i;
            }
        }
    }


    if (isset($_GET["issues"])) {
        $priorities = explode(",", htmlspecialchars($_GET["issues"]));
        $mysqli = new mysqli("localhost", "username", "password", "Issues");

        if (mysqli_connect_errno()) {
            printf("Unable to connect: %s\n", mysqli_connect_error());
            exit();
        }

        $queryList = "";
        foreach ($priorities as $key => $issue) {
            if (strlen($issue) == 0)
                continue;
            if (strlen($queryList) != 0)
                $queryList .= ",";
            $queryList .= "('" . $issue . "', " . $key . ")";
        }

        $query = "REPLACE INTO Issues(id, priority) VALUES " . $queryList;
        $result = $mysqli->query($query);
        if ($result == FALSE) {
            printf("Error with query: %s\n", $query);
            exit();
        }


        $mysqli->close();
    }

    $issues = array();
    $i = new Issue();
    $i->id = "No priority";
    $i->descr = "@@@@@@@@@@@ You should handle underlying tasks - they don't have any priorities @@@@@@@@@@@@";
    $i->priority = 100000;
    $issues[] = $i;
    $issuesList = "";

    request("191AE060", $issues, $issuesList);
    request("191AE060SM", $issues, $issuesList);
    request("TLGLOG", $issues, $issuesList);

    if( empty($issuesList) )
        die("Empty issue list - check your Jira connection");



    $mysqli = new mysqli("localhost", "username", "password", "Issues");

    if (mysqli_connect_errno()) {
        printf("Unable to connect: %s\n", mysqli_connect_error());
        exit();
    }

    $query = "SELECT id, priority FROM Issues WHERE id IN (" . $issuesList . ")";
    $result = $mysqli->query($query);
    if ($result == FALSE) {
        printf("Error with query: %s\n", $query);
        exit();
    }

    while ($row = $result->fetch_row()) {
        $issues[$row[0]]->priority = (int)$row[1];
    }

    $result->free();
    $mysqli->close();

    function cmpIssues($a, $b)
    {
        if ($a->priority == $b->priority) {
            return strcmp($a->id, $b->id);
        }
        return ($a->priority < $b->priority) ? -1 : 1;
    }

    uasort($issues, 'cmpIssues');
    //var_dump($issues);

    foreach ($issues as $issue) {
        if (strlen($issue->id) == 0)
            continue;

        print "<li class=\"ui-state-default\"><span class=\"ui-icon ui-icon-arrowthick-2-n-s\"></span>";
        print "<img src='" . $issue->priorityIcon . "'/>";
        print "<a href=http://jira.teligent.ru/browse/" . $issue->id . " class=issueId>" . $issue->id . "</a> ";
        print "<img src='" . $issue->assigneeIcon . "' title='" . $issue->assignee . "'/>";
        print " (" . $issue->duedate . ")";
        print " - " . $issue->descr;
        $highlight = "";
        if (in_array("3rd_line_Support", $issue->labels)) {
            $highlight = "color=red";
        }
        print " (<font " . $highlight . ">" . implode(", ", $issue->labels) . "</font>)</li>";
    }
    ?>

</ul>
</body>
</html>
