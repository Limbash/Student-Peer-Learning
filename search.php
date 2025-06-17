<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'includes/db.php';

$search_query = trim($_GET['q'] ?? '');
$search_type = $_GET['type'] ?? 'all'; // all, groups, discussions, resources
$results = [];

if (!empty($search_query)) {
    // Prepare search query for LIKE operations
    $like_query = '%' . $search_query . '%';
    
    // Search groups
    if ($search_type === 'all' || $search_type === 'groups') {
        $stmt = $conn->prepare("
            SELECT g.id, g.name, g.description, g.category, 
                   MATCH(g.name, g.description) AGAINST(? IN NATURAL LANGUAGE MODE) AS relevance
            FROM groups g
            WHERE MATCH(g.name, g.description) AGAINST(? IN NATURAL LANGUAGE MODE)
            OR g.name LIKE ? OR g.description LIKE ?
            ORDER BY relevance DESC
            LIMIT 20
        ");
        $stmt->bind_param("ssss", $search_query, $search_query, $like_query, $like_query);
        $stmt->execute();
        $groups_result = $stmt->get_result();
        while ($row = $groups_result->fetch_assoc()) {
            $results['groups'][] = $row;
        }
        $stmt->close();
    }
    
    // Search discussions
    if ($search_type === 'all' || $search_type === 'discussions') {
        $stmt = $conn->prepare("
            SELECT d.id, d.title, d.content, d.created_at, 
                   g.id AS group_id, g.name AS group_name,
                   u.name AS author_name,
                   MATCH(d.title, d.content) AGAINST(? IN NATURAL LANGUAGE MODE) AS relevance
            FROM discussions d
            JOIN groups g ON d.group_id = g.id
            JOIN users u ON d.user_id = u.id
            WHERE MATCH(d.title, d.content) AGAINST(? IN NATURAL LANGUAGE MODE)
            OR d.title LIKE ? OR d.content LIKE ?
            ORDER BY relevance DESC
            LIMIT 20
        ");
        $stmt->bind_param("ssss", $search_query, $search_query, $like_query, $like_query);
        $stmt->execute();
        $discussions_result = $stmt->get_result();
        while ($row = $discussions_result->fetch_assoc()) {
            $results['discussions'][] = $row;
        }
        $stmt->close();
    }
    
    // Search resources
    if ($search_type === 'all' || $search_type === 'resources') {
        $stmt = $conn->prepare("
            SELECT r.id, r.title, r.description, r.uploaded_at,
                   g.id AS group_id, g.name AS group_name,
                   u.name AS uploaded_by_name,
                   MATCH(r.title, r.description) AGAINST(? IN NATURAL LANGUAGE MODE) AS relevance
            FROM resources r
            JOIN groups g ON r.group_id = g.id
            LEFT JOIN users u ON r.uploaded_by = u.id
            WHERE MATCH(r.title, r.description) AGAINST(? IN NATURAL LANGUAGE MODE)
            OR r.title LIKE ? OR r.description LIKE ?
            ORDER BY relevance DESC
            LIMIT 20
        ");
        $stmt->bind_param("ssss", $search_query, $search_query, $like_query, $like_query);
        $stmt->execute();
        $resources_result = $stmt->get_result();
        while ($row = $resources_result->fetch_assoc()) {
            $results['resources'][] = $row;
        }
        $stmt->close();
    }
}

function highlight_search_term($text, $query) {
    if (empty($query)) return $text;
    return preg_replace("/(" . preg_quote($query, '/') . ")/i", "<mark>$1</mark>", $text);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - Peer Learning Network</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .search-result-card {
            transition: all 0.3s ease;
            border-left: 3px solid #4e73df;
        }
        .search-result-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .result-type-badge {
            font-size: 0.7rem;
            padding: 2px 5px;
            border-radius: 3px;
            background-color: #4e73df;
            color: white;
        }
        mark {
            background-color: #f6c23e;
            padding: 0 2px;
        }
        .search-nav-pills .nav-link {
            color: #4e73df;
        }
        .search-nav-pills .nav-link.active {
            background-color: #4e73df;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'partials/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" action="search.php">
                            <div class="input-group">
                                <input type="text" name="q" class="form-control form-control-lg" 
                                       placeholder="Search groups, discussions, and resources..." 
                                       value="<?= htmlspecialchars($search_query) ?>" required>
                                <button class="btn btn-primary" type="submit">
                                    <i class="bi bi-search"></i> Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if (!empty($search_query)): ?>
                    <ul class="nav nav-pills search-nav-pills mb-4">
                        <li class="nav-item">
                            <a class="nav-link <?= $search_type === 'all' ? 'active' : '' ?>" 
                               href="search.php?q=<?= urlencode($search_query) ?>&type=all">All</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $search_type === 'groups' ? 'active' : '' ?>" 
                               href="search.php?q=<?= urlencode($search_query) ?>&type=groups">Groups</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $search_type === 'discussions' ? 'active' : '' ?>" 
                               href="search.php?q=<?= urlencode($search_query) ?>&type=discussions">Discussions</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $search_type === 'resources' ? 'active' : '' ?>" 
                               href="search.php?q=<?= urlencode($search_query) ?>&type=resources">Resources</a>
                        </li>
                    </ul>
                    
                    <div class="mb-4">
                        <h4>Search Results for "<?= htmlspecialchars($search_query) ?>"</h4>
                        <p class="text-muted">Found <?= 
                            (count($results['groups'] ?? []) + 
                             count($results['discussions'] ?? []) + 
                             count($results['resources'] ?? [])) ?> results
                        </p>
                    </div>
                    
                    <!-- Groups Results -->
                    <?php if (($search_type === 'all' || $search_type === 'groups') && !empty($results['groups'])): ?>
                        <div class="mb-5">
                            <h5 class="d-flex align-items-center mb-3">
                                <i class="bi bi-people-fill me-2"></i> Groups
                                <span class="badge bg-primary ms-2"><?= count($results['groups']) ?></span>
                            </h5>
                            
                            <?php foreach ($results['groups'] as $group): ?>
                                <div class="card search-result-card mb-3">
                                    <div class="card-body">
                                        <h5>
                                            <a href="group_view.php?id=<?= $group['id'] ?>">
                                                <?= highlight_search_term(htmlspecialchars($group['name']), $search_query) ?>
                                            </a>
                                        </h5>
                                        <p class="text-muted mb-2">
                                            <span class="badge bg-secondary"><?= ucfirst($group['category']) ?></span>
                                        </p>
                                        <p class="mb-0">
                                            <?= highlight_search_term(
                                                nl2br(htmlspecialchars(substr($group['description'], 0, 200))), 
                                                $search_query
                                            ) ?>
                                            <?= strlen($group['description']) > 200 ? '...' : '' ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Discussions Results -->
                    <?php if (($search_type === 'all' || $search_type === 'discussions') && !empty($results['discussions'])): ?>
                        <div class="mb-5">
                            <h5 class="d-flex align-items-center mb-3">
                                <i class="bi bi-chat-left-text-fill me-2"></i> Discussions
                                <span class="badge bg-primary ms-2"><?= count($results['discussions']) ?></span>
                            </h5>
                            
                            <?php foreach ($results['discussions'] as $discussion): ?>
                                <div class="card search-result-card mb-3">
                                    <div class="card-body">
                                        <h5>
                                            <a href="discussion_threads.php?group_id=<?= $discussion['group_id'] ?>&discussion_id=<?= $discussion['id'] ?>">
                                                <?= highlight_search_term(htmlspecialchars($discussion['title']), $search_query) ?>
                                            </a>
                                        </h5>
                                        <p class="text-muted mb-2">
                                            In group: <a href="group_view.php?id=<?= $discussion['group_id'] ?>">
                                                <?= htmlspecialchars($discussion['group_name']) ?>
                                            </a>
                                            • Started by <?= htmlspecialchars($discussion['author_name']) ?>
                                            • <?= date('M j, Y', strtotime($discussion['created_at'])) ?>
                                        </p>
                                        <p class="mb-0">
                                            <?= highlight_search_term(
                                                nl2br(htmlspecialchars(substr($discussion['content'], 0, 200))), 
                                                $search_query
                                            ) ?>
                                            <?= strlen($discussion['content']) > 200 ? '...' : '' ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Resources Results -->
                    <?php if (($search_type === 'all' || $search_type === 'resources') && !empty($results['resources'])): ?>
                        <div class="mb-5">
                            <h5 class="d-flex align-items-center mb-3">
                                <i class="bi bi-file-earmark-fill me-2"></i> Resources
                                <span class="badge bg-primary ms-2"><?= count($results['resources']) ?></span>
                            </h5>
                            
                            <?php foreach ($results['resources'] as $resource): ?>
                                <div class="card search-result-card mb-3">
                                    <div class="card-body">
                                        <h5>
                                            <a href="uploads/<?= htmlspecialchars($resource['filename']) ?>" download>
                                                <?= highlight_search_term(htmlspecialchars($resource['title']), $search_query) ?>
                                            </a>
                                        </h5>
                                        <p class="text-muted mb-2">
                                            In group: <a href="group_view.php?id=<?= $resource['group_id'] ?>">
                                                <?= htmlspecialchars($resource['group_name']) ?>
                                            </a>
                                            • Uploaded by <?= htmlspecialchars($resource['uploaded_by_name'] ?: 'System') ?>
                                            • <?= date('M j, Y', strtotime($resource['uploaded_at'])) ?>
                                        </p>
                                        <?php if (!empty($resource['description'])): ?>
                                            <p class="mb-0">
                                                <?= highlight_search_term(
                                                    nl2br(htmlspecialchars(substr($resource['description'], 0, 200))), 
                                                    $search_query
                                                ) ?>
                                                <?= strlen($resource['description']) > 200 ? '...' : '' ?>
                                            </p>
                                        <?php endif; ?>
                                        <div class="mt-2">
                                            <a href="uploads/<?= htmlspecialchars($resource['filename']) ?>" 
                                               class="btn btn-sm btn-outline-primary" download>
                                                <i class="bi bi-download me-1"></i> Download
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($results['groups']) && empty($results['discussions']) && empty($results['resources'])): ?>
                        <div class="alert alert-info text-center py-5">
                            <i class="bi bi-search" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">No results found</h4>
                            <p>Try different keywords or search for something else</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info text-center py-5">
                        <i class="bi bi-search" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Search the Peer Learning Network</h4>
                        <p>Find groups, discussions, and resources by entering keywords above</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>