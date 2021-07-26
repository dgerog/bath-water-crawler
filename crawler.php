<?php
    function _traverseDOM (DOMNode $domNode, $startPrinting) {
        $html = "";
        foreach ($domNode->childNodes as $node) {
            if ($node->nodeValue == "Στοιχεία επικοινωνίας και πηγές περαιτέρω ενημέρωσης") {
                //once this text is meet stop any further process... we have extracted all the required info
                break;
            }
            if ($node->nodeValue == "Εισαγωγή") {
                //start printing only after this text is detected... all the previous info is garbage
                $startPrinting = true;
            }
            if ($startPrinting) {
                if (in_array($node->nodeName, array("h1","h2","h3","h4","h5","h6"))) {
                    $html .= "<h3>" . $node->nodeValue . "</h3>";
                }
                else if ($node->nodeName == "li") {
                    $html .= "•" . $node->nodeValue . "<br/>";
                }
                else if ($node->nodeName == "sup") {
                    $html .= "<sup>" . $node->nodeValue . "</sup>";
                }
                else if ($node->nodeName == "sub") {
                    $html .= "<sub>" . $node->nodeValue . "</sub>";
                }
                else if ($node->nodeName == "br") {
                    $html .= "<br/>";
                }
                else if ($node->nodeName == "p") {
                    if ($node->nodeValue != "")
                    $html .= "<p>" . $node->nodeValue . "</p>";
                }
                if($node->hasChildNodes())
                    $html .= _traverseDOM($node, true);
            }
        }
        return ($html);
    }

    class Crawler {
        protected $ids;
        protected $endpoint;
        protected $outfolder;

        function __construct($infile, $outfolder, $endpoint) {
            //read the IDs for the records to crawl
            if (!file_exists($infile)) {
                throw new Exception($infile . ": File does not exist.");
            }
            $this->ids = array();
            $file = fopen($infile, "r");
            while ($id = fgets($file)) {
                $id = trim($id, " \n\r\t\v\0");
                if ($id != '')
                    array_push($this->ids, $id);
            }
            fclose($file);

            //define the root crawling endpoint
            $this->endpoint = $endpoint;
            if (substr($this->endpoint, -1, 1) != '/')
                $this->endpoint .= '/'; //make sure the last character is always the trailing slash
            $this->endpoint = $this->endpoint;

            //create the output folder
            if (substr($outfolder, -1, 1) != '/')
                $outfolder .= '/'; //make sure the last character is always the trailing slash
            $this->outfolder = $outfolder;                        
            if (!file_exists($outfolder)) {
                mkdir($outfolder, 0777, true);
            }
            else {
                if (!is_dir($outfolder)) {
                    throw new Exception($outfolder . ": Not a folder.");
                }
            }

            $outfolder = $this->outfolder. "/content";
            if (!file_exists($outfolder)) {
                mkdir($outfolder, 0777, true);
            }
        }

        function crawl() {
            set_time_limit(3600);

            $data = array();
            $doc = new DOMDocument();
            foreach ($this->ids as $id) {
                //1. read html
                $ch = curl_init();
                    curl_setopt($ch, CURLOPT_HEADER, false);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_URL, $this->endpoint . $id);
                $html = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code != 200) {
                    //an error occured -> continue... no need to parse the output
                    continue;
                }
                //2. create storage file
                $outfolder = $this->outfolder.$id . "/";
                if (!file_exists($outfolder)) {
                    mkdir($outfolder, 0777, true);
                }

                //
                //3. parse html
                //
                $doc->loadHTML($html);

                //3.1 download images
                $elements = $doc->getElementsByTagName('img');
                foreach ($elements as $elem) {
                    if (strpos($elem->getAttribute('src'), "bp_site_photos")) {
                        $url_path = parse_url($elem->getAttribute('src'), PHP_URL_PATH);
                        $path_parts = pathinfo($url_path);
                        $fname = $outfolder . str_replace("_","",$path_parts["filename"]) . "." . $path_parts['extension'];
                        file_put_contents($fname, file_get_contents($elem->getAttribute('src')));
                    }
                }

                //3.2 download pdf
                $elements = $doc->getElementsByTagName('a');
                foreach ($elements as $elem) {
                    if (strpos($elem->getAttribute('href'), $id.".pdf")) {
                        $fname = $outfolder . $id.".pdf";
                        file_put_contents($fname, file_get_contents($elem->getAttribute('href')));
                    }
                }

                //3.3 basic data
                $beachName = "";
                $municipality = "";
                $region = "";
                $decentralizedRegion = "";
                $elements = $doc->getElementsByTagName('b');
                foreach ($elements as $elem) {
                    if (is_numeric(strpos($elem->nodeValue, "Όνομα Ακτής:")) && $beachName == "") {
                        $beachName = trim(str_replace("Όνομα Ακτής:","",$elem->nodeValue), " \n\r\t\v\0");
                    }
                    else if (is_numeric(strpos($elem->nodeValue, "Δήμος")) && $municipality == "") {
                        $municipality = trim(str_replace("Δήμος","",$elem->nodeValue), " \n\r\t\v\0");
                    }
                    else if (is_numeric(strpos($elem->nodeValue, "Περιφέρεια")) && $region == "") {
                        $region = trim(str_replace("Περιφέρεια","",$elem->nodeValue), " \n\r\t\v\0");
                    }
                    else if (is_numeric(strpos($elem->nodeValue, "Αποκεντρωμένη Διοίκηση")) && $decentralizedRegion == "") {
                        $decentralizedRegion = trim(str_replace("Αποκεντρωμένη Διοίκηση","",$elem->nodeValue), " \n\r\t\v\0");
                    }
                }
                file_put_contents($outfolder . $id.".txt", "Όνομα Ακτής: {$beachName}\nΔήμος: {$municipality}\nΠεριφέρεια: {$region}\nΑποκεντρωμένη Διοίκηση: {$decentralizedRegion}");

                //3.4 texts
                $elements = $doc->getElementsByTagName('div');
                foreach ($elements as $elem) {
                    if ($elem->getAttribute("property") == "content:encoded") {
                        $html = _traverseDOM($elem, false);
                        break;
                    }
                }
                file_put_contents($outfolder . $id.".html", $html);
                file_put_contents($this->outfolder. "/content/" . $id.".html", $html);

                array_push($data, array (
                    "id" => $id,
                    "beachName" => $beachName,
                    "municipality" => $municipality,
                    "region" => $region,
                    "decentralizedRegion" => $decentralizedRegion,
                ));
            }

            return($data);
        }
    };
?>