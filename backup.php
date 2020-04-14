<?php
/**
* Function list:
* - Zip()
* - backup()
* - human_filesize()
* - getFileList()
*/
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function Zip($source, $destination, $include_dir = false, $exclusions  = false) {
    // Remove existing archive
    if (file_exists($destination)) {
        unlink($destination);
    }

    $zip         = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        return false;
    }
    $source  = str_replace('\\', '/', realpath($source));
    if (is_dir($source) === true) {
        $files   = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source) , RecursiveIteratorIterator::SELF_FIRST);
        if ($include_dir) {
            $arr     = explode("/", $source);
            $maindir = $arr[count($arr) - 1];
            $source  = "";
            for ($i       = 0;$i < count($arr) - 1;$i++) {
                $source .= '/' . $arr[$i];
            }
            $source = substr($source, 1);
            $zip->addEmptyDir($maindir);
        }
        foreach ($files as $file) {
            // Ignore "." and ".." folders
            $file = str_replace('\\', '/', $file);
            if (in_array(substr($file, strrpos($file, '/') + 1) , array('.','..'))) {
                continue;
            }

            // Add Exclusion
            if (($exclusions) && (is_array($exclusions))) {
                if (in_array(str_replace($source . '/', '', $file) , $exclusions)) {
                    continue;
                }
            }

            $file = realpath($file);
            if (is_dir($file) === true) {
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            }
            elseif (is_file($file) === true) {
                $zip->addFromString(str_replace($source . '/', '', $file) , file_get_contents($file));
            }
        }
    }
    elseif (is_file($source) === true) {
        $zip->addFromString(basename($source) , file_get_contents($source));
    }
    return $zip->close();
}

function human_filesize($bytes, $decimals = 2) {
    $factor   = floor((strlen($bytes) - 1) / 3);
    if ($factor > 0) $sz       = 'KMGT';
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor - 1] . 'B';
}

function getFileList($dir, $recurse = false) {
    // array to hold return value
    $retval  = [];

    // add trailing slash if missing
    if (substr($dir, -1) != "/") {
        $dir .= "/";
    }

    // open pointer to directory and read list of files
    $d      = @dir($dir) or die("getFileList: Failed opening directory {$dir} for reading");
    while (false !== ($entry  = $d->read())) {
        // skip hidden files
        if ($entry{0} == ".") continue;
        if (is_dir("{$dir}{$entry}")) {
            // just eleminate the folder lines
            // $retval[] = [
            //   'name' => str_replace("./","","{$dir}{$entry}"),
            //   'type' => filetype("{$dir}{$entry}"),
            //   'size' => "",
            //   'lastmod' => date("d. F Y H:i", filemtime("{$dir}{$entry}"))
            // ];
            if ($recurse && is_readable("{$dir}{$entry}/")) {
                if (is_array(getFileList("{$dir}{$entry}/", true))) {
                    $retval = array_merge($retval, getFileList("{$dir}{$entry}/", true));
                }
                else { {
                        continue;
                    }
                }

            }
        }
        elseif (is_readable("{$dir}{$entry}")) {

            $retval[] = [
              'name' => "<a href=\" " . str_replace("./", "", "{$dir}{$entry}") . " \">" . str_replace("./", "", "{$dir}{$entry}") . "</a>",
              'type' => mime_content_type("{$dir}{$entry}") ,
              'size' => human_filesize(filesize("{$dir}{$entry}") , 2) ,
              'lastmod' => date("d. F Y H:i", filemtime("{$dir}{$entry}"))
            ];
        }
    }
    $d->close();

    // sort array
    foreach ($retval as $key => $row) {
        $name[$key]     = $row['name'];
    }
    if (isset($name) and is_array($name)) {
        array_multisort($name, SORT_ASC, SORT_STRING, $retval);
    }

    // print_r($retval);
    return $retval;
}

function backup($path) {
    $backup     = $path;
    $exclusions = [];
    // Excluding an entire directory
    $files      = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('backups/') , RecursiveIteratorIterator::SELF_FIRST);
    foreach ($files as $file) {
        array_push($exclusions, $file);
    }
    // Excluding a file
    // array_push($exclusions, 'config/config.php');
    // Excluding the backup file
    array_push($exclusions, $backup);
    Zip('.', $backup, false, $exclusions);
}


$date = date("Y-m-d_H-i");
$path = 'backups/' . $date . '_webseite_de.zip';
backup($path);



// output file list in HTML TABLE format
$dirlist = getFileList("backups/", 1); // 1 = include subdirectories
echo "<style>";
echo "a:link,a:visited {text-decoration: none; color: #000;}";
echo "a:hover ,a:active {text-decoration: none; color: grey;}";
echo "ul {display: table;padding-left: 0;font: 1em Candara;}";
echo "li {display: table-row;}";
echo "li.head {font-weight: bold; display: table-row;}";
echo "li > span {display: table-cell;padding: 0 0.5em;}";
echo ".right {text-align:right;}";
echo "</style>";

echo "<h1>Backups</h1>";
echo "<ul><li class=\"head\">\n";
echo "<span>Name</span>\n";
// echo "<span>Type</span>\n";
echo "<span>Size</span>\n";
echo "<span>Last Modified</span>\n";
echo "</li>\n";
foreach ($dirlist as $file) {
    echo "<li>\n";
    echo "<span>{$file['name']}</span>\n";
    // echo "<span>{$file['type']}</span>\n";
    echo "<span class=\"right\">{$file['size']}</span>\n";
    echo "<span>{$file['lastmod']}</span>\n";
    echo "</li>\n";
}
echo "</ul>\n\n";

?>
