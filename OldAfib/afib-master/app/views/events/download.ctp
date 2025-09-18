<?php
if (!defined('CAKEPHP_UNIT_TEST_EXECUTION')) {
    header("Content-type: $contentType");
    header('Content-disposition: attachment; filename="' . $destfile . '"');

    //header('Content-Length: ' . fileSize($sourcefile));
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
// IE handles no-cache in a rather stupid way when downloading, so just skip it
//    header('Pragma: no-cache');

//    ob_end_clean();

    $handle = fopen($sourcefile, "rb");
    
    while (!feof($handle) && connection_status() == 0 && !connection_aborted()) 
    {
        set_time_limit(0);
        $buffer = fread($handle, 8192);
        echo $buffer;
    //  flush();
        ob_flush();
    }

    flush();
    ob_flush();
    fclose($handle);
}

?>
