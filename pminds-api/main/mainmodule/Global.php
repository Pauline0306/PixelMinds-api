<?php

require_once( '/../utils/Response.php');
require_once(__DIR__ . '/../config/Conneciton.php');
require_once(__DIR__ . '/../config/secretKey.php');
require_once( '/../vendor/autoload.php');

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

    public function createPost()
    {
        $data = json_decode(file_get_contents("php://input"));

        // Extract the data from the incoming JSON
        $title = $data->title;
        $content = $data->content;
        $author_id = $data->author_id;

        // Set author_role to 'user' regardless of the actual role of the author
        $authorRole = 'user';

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
        $stmt->bindParam(':author_role', $authorRole); // Bind author_role to 'user'

        // Set the created_at parameter to the current timestamp
        $created_at = date('Y-m-d H:i:s');
        $stmt->bindParam(':created_at', $created_at);

        // Execute the query and handle the response
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array("message" => "Post was successfully created."));
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Unable to create the post."));
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
}
