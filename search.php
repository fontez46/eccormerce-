<?php
// search.php

// Database connection remains the same
$host = 'localhost';
$dbname = 'eccormerce';
$username = 'root';
$password = 'Joseph@254';

try {
  $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  die(json_encode(['error' => 'Database connection failed']));
}

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;

if (!empty($query)) {
  $offset = ($page - 1) * $limit;
  $searchTerm = '%' . $query . '%';

  // Main search query (now includes category)
  $sql = "SELECT COUNT(*) FROM products 
          WHERE name LIKE :query 
          OR description LIKE :query 
          OR category LIKE :query";
  
  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':query', $searchTerm, PDO::PARAM_STR);
  $stmt->execute();
  $totalItems = $stmt->fetchColumn();
  $totalPages = ceil($totalItems / $limit);
  // Modify the SQL query to match your needs
$resultsSql = "SELECT * FROM products 
WHERE category LIKE :query 
OR name LIKE :query 
OR description LIKE :query 
ORDER BY 
  CASE WHEN category LIKE :query THEN 0 ELSE 1 END,
  name
LIMIT :limit OFFSET :offset";
  
  $stmt = $pdo->prepare($resultsSql);
  $stmt->bindParam(':query', $searchTerm, PDO::PARAM_STR);
  $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
  $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Suggestions query (now searches all fields)
  $suggestionsSql = "SELECT DISTINCT name, category 
                    FROM products 
                    WHERE name LIKE :query 
                    OR description LIKE :query 
                    OR category LIKE :query 
                    LIMIT 5";
  
  $suggestionsStmt = $pdo->prepare($suggestionsSql);
  $suggestionsStmt->bindParam(':query', $searchTerm, PDO::PARAM_STR);
  $suggestionsStmt->execute();
  $suggestions = $suggestionsStmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
      'results' => $results,
      'totalPages' => $totalPages,
      'suggestions' => $suggestions
  ]);
} else {
  echo json_encode(['error' => 'Please enter a search term']);
}
?>