<?php
$zip = new ZipArchive;
if ($zip->open('file.zip') === TRUE) {
    $zip->extractTo('./');
    $zip->close();
    echo 'Extracted!';
} else {
    echo 'Failed!';
}
?>
