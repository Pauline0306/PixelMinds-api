<?php 

class GlobalUtil{

    function sendResponse($data, $statusCode)
    {
        return array("status" => "success", "data" => $data, "statusCode" => $statusCode);
    }

    function sendErrorResponse($message, $statusCode)
    {
        return array("status" => "error", "message" => $message, "statusCode" => $statusCode);
    }

}

?>