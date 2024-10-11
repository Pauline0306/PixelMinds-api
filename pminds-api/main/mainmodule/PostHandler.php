<?php

require_once('../utils/Response.php');
require_once(__DIR__ . '/../config/Connection.php');
require_once(__DIR__ . '/../config/secretKey.php');
require_once('../vendor/autoload.php');


class PostHandler extends GlobalUtil
{
    private $pdo;
    private $conn;
    private $gm;

    public function __construct($pdo)
    {
        $databaseService = new DatabaseAccess();
        $this->conn = $databaseService->connect();
        $this->pdo = $pdo;
    }

    
    public function createPost($data)
    {

        $title = $_POST['title'] ?? null;
        $content = $_POST['content'] ?? null;
        $author_id = $_POST['author_id'] ?? null;

        if (empty($title) || empty($content) || empty($author_id)) {
            http_response_code(400);
            return json_encode(array("message" => "Title, content, and author_id are required."));
        }

        $imageUrl = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/'; // Directory where images will be stored
        $imageFileName = basename($_FILES['image']['name']);
        $targetFilePath = $uploadDir . uniqid() . '_' . $imageFileName; // Use unique name to prevent overwriting

        // Check if the file is a valid image
        $imageFileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        $validExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($imageFileType, $validExtensions)) {
            // Move the uploaded file to the specified directory
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
                $imageUrl = $targetFilePath; // Store the path for database
            } else {
                http_response_code(400);
                return json_encode(array("message" => "Failed to upload image."));
            }
        } else {
            http_response_code(400);
            return json_encode(array("message" => "Invalid image format."));
        }
    }

    // Set author_role to 'user'
    $authorRole = 'user';

    // Prepare the SQL statement
    $table_name = 'posts';
    $query = "INSERT INTO " . $table_name . "
                SET title = :title,
                    content = :content,
                    author_id = :author_id,
                    author_role = :author_role,
                    image = :image,
                    created_at = :created_at";

    $stmt = $this->conn->prepare($query);

    // Bind parameters
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':content', $content);
    $stmt->bindParam(':author_id', $author_id);
    $stmt->bindParam(':author_role', $authorRole);
    $stmt->bindParam(':image', $imageUrl);

    // Set created_at to current timestamp
    $created_at = date('Y-m-d H:i:s');
    $stmt->bindParam(':created_at', $created_at);

    // Execute the query and handle the response
    if ($stmt->execute()) {
        http_response_code(200);
        return json_encode(array("message" => "Post was successfully created."));
    } else {
        http_response_code(400);
        return json_encode(array("message" => "Unable to create the post."));
    }
}


    public function createAdminPost()
    {
        $data = json_decode(file_get_contents("php://input"));

        // Extract the data from the incoming JSON
        $title = $data->title;
        $content = $data->content;
        $author_id = $data->author_id;

        // Check if author is admin
        $authorRole = $this->getAuthorRole($author_id);
        if ($authorRole !== 'admin') {
            http_response_code(403);
            echo json_encode(array("message" => "Only admins can create admin posts."));
            return;
        }

        // Set author_role to 'admin'
        $authorRole = 'admin';

        $table_name = 'posts';

        $query = "INSERT INTO " . $table_name . "
                    SET title = :title,
                        content = :content,
                        author_id = :author_id,
                        author_role = :author_role,
                        created_at = :created_at";

        $stmt = $this->conn->prepare($query);

        // Bind the parameters
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':content', $content);
        $stmt->bindParam(':author_id', $author_id);
        $stmt->bindParam(':author_role', $authorRole); // Bind author_role to 'admin'

        // Set the created_at parameter to the current timestamp
        $created_at = date('Y-m-d H:i:s');
        $stmt->bindParam(':created_at', $created_at);

        // Execute the query and handle the response
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array("message" => "Admin post was successfully created."));
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Unable to create the admin post."));
        }
    }

    // Helper function to get author's role by author_id
    private function getAuthorRole($author_id)
    {
        $sql = "SELECT user_role FROM users WHERE user_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$author_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['user_role'];
    }

    public function deletePost($postIds)
    {
        $tableName = 'posts';
        $placeholders = rtrim(str_repeat('?, ', count($postIds)), ', '); // Create placeholders like (?, ?, ?)
        $sql = "DELETE FROM $tableName WHERE id IN ($placeholders)";

        try {
            $stmt = $this->pdo->prepare($sql);

            // Execute the statement with the array of post IDs
            $stmt->execute($postIds);

            return $this->sendResponse("Posts Deleted", 200);
        } catch (\PDOException $e) {
            $errmsg = $e->getMessage();
            return $this->sendErrorResponse("Failed to delete posts: " . $errmsg, 400);
        }
    }

    public function updatePost($postId, $data)
    {
        $sql = "UPDATE posts SET title = :title, content = :content WHERE id = :id";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':title', $data->title);
            $stmt->bindParam(':content', $data->content);
            $stmt->bindParam(':id', $postId);
            $stmt->execute();

            return $this->sendResponse("Post updated successfully", 200);
        } catch (\PDOException $e) {
            $errmsg = $e->getMessage();
            return $this->sendErrorResponse("Failed to update post: " . $errmsg, 400);
        }
    }



    public function upload_images() {
        if (!isset($_FILES['image'])) {
            return $this->gm->
                returnPayload(  null, 
                 'failed', 
                 'No file uploaded', 
              400  );
        }
    
        $file = $_FILES['image'];
       $targetDir = "uploads/";
    
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0777, true)) {
                return $this->gm->
                    returnPayload(  null, 
                     'failed', 
                     'Failed to create directory', 
                      500 );
            }
        }
    
        $targetFile = $targetDir . basename($file["name"]);
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $allowedTypes = array("jpg", "jpeg", "png", "gif");
    
    
        if (!in_array($imageFileType, $allowedTypes)) {
            return $this->gm->
                returnPayload(null, 
                    'failed', 
                    'Unsupported file type.',
                     400);
        }
    
        
        if (move_uploaded_file($file["tmp_name"], $targetFile)) {
            $sql = "INSERT INTO images (title, filename, url, description, uploaded_at, user_id) VALUES (?, ?, ?, ?,  NOW(), ?)";
            $stmt = $this->pdo->prepare($sql);
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $_POST['title'],        
                    $file["name"],          
                    $targetFile,            
                    $_POST['description'],  
                    $_POST['user_id']       
                ]);

                return $this->gm->
                    returnPayload(
                                null, 
                                'success', 
                                'Successfully inserted image', 
                                200
                            );
            } catch (\PDOException $e) {
               
                return $this->gm->
                    returnPayload(
                        null, 
                        'failed', 
                        'Database error: ' . $e->getMessage(), 
                        400
                    );
            }
        } else {
            return $this->gm->
                returnPayload(
                    null, 
                    'failed', 
                    'Failed to move uploaded file.',
                    500
                );
        }
    }




    public function getPostData()
    {
        try {
            $tableName = 'posts';


            // SQL query to select posts with author details
            $sql = "SELECT 
                        p.id, 
                        p.title, 
                        p.content, 
                        p.author_id, 
                        p.image,
                        u.user_fullname, 
                        p.author_role,
                        p.created_at 
                    FROM 
                        $tableName p 
                    JOIN 
                        users u ON p.author_id = u.user_id";

            $stmt = $this->pdo->query($sql);

            // Fetch all rows as associative array
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Check if there are posts found
            if ($result) {
                // Return successful response with the posts data
                return $this->sendResponse($result, 200);
            } else {
                // Return error response if no posts found
                return $this->sendErrorResponse("No posts found.", 404);
            }
        } catch (\PDOException $e) {
            $errmsg = $e->getMessage();
            // Return error response for any database error
            return $this->sendErrorResponse("Failed to retrieve posts: " . $errmsg, 500);
        }
    }

    public function getComments($postId)
    {
        try {
            $sql = "SELECT c.comment_id, c.comment_content, c.created_at, u.user_fullname
                FROM comments c
                JOIN users u ON c.user_id = u.user_id
                WHERE c.post_id = ?
                ORDER BY c.created_at DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$postId]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Check if there are comments found
            if ($comments) {
                // Return successful response with the comments data
                return $this->sendResponse($comments, 200);
            } else {
                // Return error response if no comments found
                return $this->sendErrorResponse("No comments found for post $postId", 404);
            }
        } catch (\PDOException $e) {
            $errmsg = $e->getMessage();
            // Return error response for any database error
            return $this->sendErrorResponse("Failed to fetch comments: " . $errmsg, 500);
        }
    }


    public function addComment()
    {
        try {
            $data = json_decode(file_get_contents("php://input"));

            // Extract data from JSON
            $postId = $data->postId;
            $userId = $data->userId;
            $commentContent = $data->commentText; // Make sure this matches your JSON structure

            // Validate if necessary
            if (empty($commentContent)) {
                return $this->sendErrorResponse("Comment content cannot be empty", 400);
            }

            // Prepare SQL statement
            $sql = "INSERT INTO comments (post_id, user_id, comment_content, created_at)
                    VALUES (:post_id, :user_id, :comment_content, :created_at)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':post_id', $postId);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':comment_content', $commentContent);
            $created_at = date('Y-m-d H:i:s');
            $stmt->bindParam(':created_at', $created_at);

            // Execute SQL statement
            if ($stmt->execute()) {
                return $this->sendResponse("Comment added successfully", 200);
            } else {
                return $this->sendErrorResponse("Failed to add comment", 500);
            }
        } catch (\PDOException $e) {
            $errmsg = $e->getMessage();
            return $this->sendErrorResponse("Failed to add comment: " . $errmsg, 500);
        }
    }




    public function deleteComment($commentId)
    {
        try {
            $sql = "DELETE FROM comments WHERE comment_id = ?";
            $stmt = $this->pdo->prepare($sql);
            if ($stmt->execute([$commentId])) {
                return $this->sendResponse("Comment deleted successfully", 200);
            } else {
                return $this->sendErrorResponse("Failed to delete comment", 400);
            }
        } catch (\PDOException $e) {
            $errmsg = $e->getMessage();
            return $this->sendErrorResponse("Failed to delete comment: " . $errmsg, 500);
        }
    }

    public function get_images_by_user($user_id) {
        // Log the received user_id
        error_log("Received user_id: " . print_r($user_id, true));
    
        // Validate the provided user_id
        if (empty($user_id)) {
            error_log("User ID is empty or not provided.");
            return $this->gm->returnPayload(null, 'failed', 'User ID not provided', 400);
        }
    
        // SQL query to get images by user_id
        $sql = "SELECT id, title, filename, url, description, uploaded_at, user_id FROM images WHERE user_id = ?";
    
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$user_id]); // Bind user_id to the query
            $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            // Log the retrieved images
            error_log("Retrieved images: " . print_r($images, true));
    
            if ($images) {
                return $this->gm->returnPayload($images, 'success', 'Successfully retrieved images', 200);
            } else {
                error_log("No images found for user_id: " . $user_id);
                return $this->gm->returnPayload(null, 'failed', 'No images found for the user', 404);
            }
        } catch (\PDOException $e) {
            // Log the database error
            error_log("Database error: " . $e->getMessage());
            return $this->gm->returnPayload(null, 'failed', 'Database error: ' . $e->getMessage(), 500);
        }
    }
}