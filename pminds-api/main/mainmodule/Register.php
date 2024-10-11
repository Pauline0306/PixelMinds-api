<?php

require_once(__DIR__ . '/../config/Connection.php');

class RegisterUser
{
    private $conn;

    public function __construct()
    {
        $databaseService = new DatabaseAccess();
        $this->conn = $databaseService->connect();

        // CORS Headers
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        header("Access-Control-Max-Age: 3600");
        header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

        // Handle preflight (OPTIONS) request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    public function registerUser($data)
    {
    
        // Log incoming data for debugging
        error_log("Incoming registration data: " . json_encode($data));
        error_log("Raw input: " . file_get_contents("php://input"));
    
        // Retrieve and decode the JSON input
        
    
        // Validate required fields
        if (!isset($data->email) || !isset($data->password) || !isset($data->fullname)) {
            http_response_code(400);
            echo json_encode(array("message" => "Incomplete registration data."));
            return;
        }
    
        $email = $data->email;
        $password = $data->password;
        $fullName = $data->fullname;
        $userRole = 'user'; // Default role; adjust as needed
        $table_name = 'users';
    
        // Check if user already exists
        $checkQuery = "SELECT * FROM " . $table_name . " WHERE user_email = :email";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->bindParam(':email', $email);
        $checkStmt->execute();
    
        if ($checkStmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(array("message" => "User already exists."));
            return;
        }
    
        // Prepare the INSERT query
        $query = "INSERT INTO " . $table_name . "
                    SET user_email = :email,
                        password = :password,
                        user_fullname = :fullname,
                        user_role = :user_role";
    
        $stmt = $this->conn->prepare($query);
    
        $stmt->bindParam(':email', $email);
    
        // Hash the password securely
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
        $stmt->bindParam(':password', $password_hash);
    
        $stmt->bindParam(':fullname', $fullName);
        $stmt->bindParam(':user_role', $userRole);
    
        // Execute the query
        if ($stmt->execute()) {
            http_response_code(201); // 201 Created
            echo json_encode(array("message" => "User was successfully registered."));
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Unable to register the user."));
        }
        error_log("User registration process completed.");
    }
    }
    ?>
    
