<?php

    include ("crawler.php");

    define('IDS_FILE_PATH' ,'ids.txt');
    define('OUT_FOLDER' ,'data');
    define('ENDPOINT','http://www.bathingwaterprofiles.gr/bathingprofiles/');

    //
    // Start crawling
    //
    try {
        /*$crawler = new Crawler(IDS_FILE_PATH, OUT_FOLDER, ENDPOINT);
        $data = $crawler->crawl();
        echo "<table border='1' width='100%'>";
            echo "<thead>";
                echo "<tr>";
                    echo "<th>#</th>";
                    echo "<th>ID</th>";
                    echo "<th>Όνομα Ακτής</th>";
                    echo "<th>Δήμος</th>";
                    echo "<th>Περιφέρεια</th>";
                    echo "<th>Αποκεντρωμένη Διοίκηση</th>";
                echo "</tr>";
            echo "</thead>";
            echo "<tbody>";
            $i = 1;
            foreach ($data as $d) {
                echo "<tr>";
                    echo "<td>".($i++)."</td>";
                    echo "<td>".$d['id']."</td>";
                    echo "<td>".$d['beachName']."</td>";
                    echo "<td>".$d['municipality']."</td>";
                    echo "<td>".$d['region']."</td>";
                    echo "<td>".$d['decentralizedRegion']."</td>";
                echo "</tr>";
            }
            echo "</tbody>";
        echo "</table>";
        */

        $dir = new DirectoryIterator('data/content');
        $fall = 'all.html';
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot()) {
                $html = file_get_contents('data/content/' . $fileinfo->getFilename());
                $html = "<h1>" . str_replace(".html", "", $fileinfo->getFilename()) . "</h1><br/>" . $html . "<br/><hr/>";
                file_put_contents($fall, $html, FILE_APPEND | LOCK_EX);
            }
        }
    }
    catch (Exception $ex) {
        echo $ex->getMessage();
    }
?>