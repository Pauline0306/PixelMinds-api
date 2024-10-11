<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    
    ob_start();
    
    // Allow requests from any origin
    header('Access-Control-Allow-Origin: *');
    
    // Allow specific HTTP methods
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
    
    // Allow specific headers
    header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token, Origin, Authorization');
    
    // Set Content-Type header to application/json for all responses
    header('Content-Type: application/json');
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    
        exit(0);
    }
    
    require_once('./config/Connection.php');
    require_once( './mainmodule/Login.php');
    require_once('./mainmodule/Register.php');
    require_once('./mainmodule/PostHandler.php');
    require_once('./mainmodule/UserManagement.php');
    

    
    $con = new DatabaseAccess();
    $pdo = $con->connect();
    
    
    $register = new RegisterUser($pdo);
    $login = new Login($pdo);
    $post = new PostHandler($pdo);
    $usermanage = new UserManagement($pdo);
    
   
    
    // Check if 'request' parameter is set in the request
    if (isset($_REQUEST['request'])) {
        // Split the request into an array based on '/'
        $request = explode('/', $_REQUEST['request']);
    } else {
        // If 'request' parameter is not set, return a 404 response
        echo json_encode(["error" => "Not Found"]);
        http_response_code(404);
        exit();
    }
    
    // Handle requests based on HTTP method
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            $data = json_decode(file_get_contents("php://input"));
            switch ($request[0]) {
                case 'login':
                    if (isset($data->email) && isset($data->password)) {
                        echo json_encode($login->loginUser($data->email, $data->password));
                    } else {
                        echo json_encode([
                            'status' => 400,
                            'message' => 'Invalid input data'
                        ]);
                    }
                    break;
                    case 'createPost':
                        echo json_encode($post->createPost($data));
                        break;
                    case 'logout':
                        echo json_encode($login->logoutUser($data));
                        break;
                        case 'register':
                            $register->registerUser($data);
                            break;
                    case 'editprofile':
                        if (isset($request[1])) {
                            echo json_encode($usermanage->updateUserData($request[1], $data));
                        } else {
                            echo json_encode($usermanage->sendErrorResponse("Invalid Response", 400));
                        }
                        break;
                    case 'updatePost':
                            if (isset($request[1])) {
                                echo json_encode($post->updatePost($request[1], $data));
                            } else {
                                echo json_encode([
                                    'status' => 400,
                                    'message' => 'Invalid post ID'
                                ]);
                            }
                        break;
                        case 'addComment':
                            echo json_encode($post->addComment($data));
                            break;
                        case 'hello':
                            echo "Hello";
                            break;


                    default:
                    echo json_encode([
                            'status' => 403,
                            'message' => 'Forbidden'
                        ]);
                        http_response_code(403);
                    break;
                    }
                        break;
                        break;
                        case 'GET':
    
                            switch ($request[0]) {

                                case 'posts':
                                    if (count($request) > 1) {
                                        echo json_encode($post->getPostData($request[1]));
                                    } else {
                                        echo json_encode($post->getPostData());
                                    }
                                    break;
                                case 'getComment': // Change case label to 'getComment' for consistency
                                    if (count($request) > 1) {
                                        echo json_encode($post->getComments($request[1]));
                                    } else {
                                        echo "Comment ID not specified"; // Handle case where comment ID is missing
                                        http_response_code(400); // Bad request status code
                                    }
                                    break;
                                    case 'GET':
                                 case 'getImages':
                                      $user_id = isset($req[1]) ? $req[1] : null;
                                       echo json_encode($get->get_images_by_user($user_id));
                                       break;

                                default:
                                    echo "Method not available";
                                    http_response_code(404);
                                    break;
                            }
                            break;                        
            case 'DELETE':
                switch ($request[0]) {
                    case 'deletePost':
                        if (isset($request[1])) {
                            echo json_encode($post->deletePost([$request[1]]));
                        } else {
                            echo json_encode([
                                'status' => 400,
                                'message' => 'Invalid post ID'
                            ]);
                        }
                        break;
                   
                case 'deleteComment':
                    if (isset($request[1])) {
                        echo json_encode($post->deleteComment([$request[1]]));
                    } else {
                        echo json_encode([
                            'status' => 400,
                            'message' => 'Invalid post ID'
                        ]);
                    }
                    break;
            default:
                echo "Method not available";
                http_response_code(404);
                break;
        }
        switch($_SERVER['REQUEST_METHOD']){
            case 'POST':
                $data = json_decode(file_get_contents("php://input"));
                switch ($req[0]) {
        case 'uploadImages':
                        echo json_encode($post->upload_images());
                        break;
    }
      }
    }
     
?>