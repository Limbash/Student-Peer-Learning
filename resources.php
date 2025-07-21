<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'includes/db.php';

$user_id = $_SESSION['user_id'];

// Get all resources from groups the user is a member of
$resources = $conn->query("
    SELECT r.*, g.name AS group_name, g.id AS group_id, 
           u.name AS uploaded_by_name, u.profile_pic AS uploaded_by_pic,
           DATE_FORMAT(r.uploaded_at, '%M %e, %Y') AS formatted_date
    FROM resources r
    JOIN groups g ON r.group_id = g.id
    JOIN group_members gm ON g.id = gm.group_id
    LEFT JOIN users u ON r.uploaded_by = u.id
    WHERE gm.user_id = $user_id
    ORDER BY r.uploaded_at DESC
");

// Get resource statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) AS total_resources,
        SUM(CASE WHEN r.uploaded_by = $user_id THEN 1 ELSE 0 END) AS my_resources,
        COUNT(DISTINCT r.group_id) AS groups_with_resources
    FROM resources r
    JOIN group_members gm ON r.group_id = gm.group_id
    WHERE gm.user_id = $user_id
")->fetch_assoc();

// Get recently accessed resources
$recent_resources = $conn->query("
    SELECT r.id, r.title, g.name AS group_name, g.id AS group_id
    FROM resource_access_log ral
    JOIN resources r ON ral.resource_id = r.id
    JOIN groups g ON r.group_id = g.id
    WHERE ral.user_id = $user_id
    ORDER BY ral.access_time DESC
    LIMIT 5
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources - Peer Learning Network</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .resource-card {
            transition: all 0.3s ease;
            border-left: 3px solid #4e73df;
        }
        .resource-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .file-icon {
            font-size: 2rem;
            color: #4e73df;
        }
        .stat-card {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .file-type-badge {
            font-size: 0.7rem;
            padding: 3px 6px;
            border-radius: 3px;
            background-color: #f8f9fa;
            color: #4e73df;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <?php include 'partials/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-file-earmark-text me-2"></i> Learning Resources</h2>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="resourceActions" data-bs-toggle="dropdown">
                    <i class="bi bi-gear"></i> Actions
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#uploadResourceModal">
                        <i class="bi bi-upload me-2"></i> Upload Resource
                    </a></li>
                    <li><a class="dropdown-item" href="search.php?type=resources">
                        <i class="bi bi-search me-2"></i> Search Resources
                    </a></li>
                </ul>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card bg-light">
                    <div class="stat-number"><?= $stats['total_resources'] ?></div>
                    <div class="stat-label">Total Resources</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card bg-light">
                    <div class="stat-number"><?= $stats['my_resources'] ?></div>
                    <div class="stat-label">My Uploads</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card bg-light">
                    <div class="stat-number"><?= $stats['groups_with_resources'] ?></div>
                    <div class="stat-label">Groups with Resources</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Resources List -->
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">All Resources</h5>
                        <small class="text-muted"><?= $resources->num_rows ?> resources found</small>
                    </div>
                    <div class="card-body">
                        <?php if ($resources->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while($resource = $resources->fetch_assoc()): 
                                    $file_ext = pathinfo($resource['filename'], PATHINFO_EXTENSION);
                                ?>
                                    <div class="list-group-item list-group-item-action resource-card">
                                        <div class="d-flex align-items-start">
                                            <div class="me-3 text-center">
                                                <i class="bi 
                                                    <?= in_array($file_ext, ['pdf']) ? 'bi-file-earmark-pdf' : '' ?>
                                                    <?= in_array($file_ext, ['doc', 'docx']) ? 'bi-file-earmark-word' : '' ?>
                                                    <?= in_array($file_ext, ['ppt', 'pptx']) ? 'bi-file-earmark-slides' : '' ?>
                                                    <?= in_array($file_ext, ['xls', 'xlsx']) ? 'bi-file-earmark-spreadsheet' : '' ?>
                                                    <?= in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'bi-file-earmark-image' : '' ?>
                                                    <?= !in_array($file_ext, ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif']) ? 'bi-file-earmark' : '' ?>
                                                    file-icon">
                                                </i>
                                                <div class="file-type-badge mt-1"><?= strtoupper($file_ext) ?></div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between">
                                                    <h5 class="mb-1">
                                                        <a href="uploads/<?= htmlspecialchars($resource['filename']) ?>" download>
                                                            <?= htmlspecialchars($resource['title']) ?>
                                                        </a>
                                                    </h5>
                                                    <small class="text-muted"><?= $resource['formatted_date'] ?></small>
                                                </div>
                                                <p class="mb-1">
                                                    <?php if (!empty($resource['description'])): ?>
                                                        <?= nl2br(htmlspecialchars(substr($resource['description'], 0, 150))) ?>
                                                        <?= strlen($resource['description']) > 150 ? '...' : '' ?>
                                                    <?php endif; ?>
                                                </p>
                                                <small class="text-muted">
                                                    In group: <a href="group_view.php?id=<?= $resource['group_id'] ?>">
                                                        <?= htmlspecialchars($resource['group_name']) ?>
                                                    </a>
                                                    • Uploaded by 
                                                    <img src="<?= htmlspecialchars($resource['uploaded_by_pic'] ?: 'images/default.png') ?>" 
                                                         class="rounded-circle ms-1 me-1" width="20" height="20">
                                                    <?= htmlspecialchars($resource['uploaded_by_name'] ?: 'System') ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <a href="uploads/<?= htmlspecialchars($resource['filename']) ?>" 
                                               class="btn btn-sm btn-outline-primary me-2" download>
                                                <i class="bi bi-download me-1"></i> Download
                                            </a>
                                            <button class="btn btn-sm btn-outline-secondary me-2">
                                                <i class="bi bi-bookmark me-1"></i> Save
                                            </button>
                                            <button class="btn btn-sm btn-outline-info">
                                                <i class="bi bi-share me-1"></i> Share
                                            </button>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-file-earmark-text" style="font-size: 3rem; color: #6c757d;"></i>
                                <h4 class="mt-3">No Resources Found</h4>
                                <p class="text-muted">You don't have access to any resources yet. Join groups to see shared resources.</p>
                                <a href="discussions.php" class="btn btn-primary mt-2">
                                    <i class="bi bi-search me-1"></i> Browse Groups
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar with Recent and Categories -->
            <div class="col-lg-4">
                <!-- Recently Accessed -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> Recently Accessed</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_resources->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while($recent = $recent_resources->fetch_assoc()): ?>
                                    <a href="group_view.php?id=<?= $recent['group_id'] ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span><?= htmlspecialchars($recent['title']) ?></span>
                                            <small class="text-muted"><?= htmlspecialchars($recent['group_name']) ?></small>
                                        </div>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mb-0">
                                No recently accessed resources
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Resource Categories -->
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-tags me-2"></i> Resource Types</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="search.php?type=resources&q=filetype:pdf" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-file-earmark-pdf text-danger me-2"></i> PDF Documents</span>
                                <span class="badge bg-primary rounded-pill">14</span>
                            </a>
                            <a href="search.php?type=resources&q=filetype:doc OR filetype:docx" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-file-earmark-word text-primary me-2"></i> Word Documents</span>
                                <span class="badge bg-primary rounded-pill">8</span>
                            </a>
                            <a href="search.php?type=resources&q=filetype:ppt OR filetype:pptx" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-file-earmark-slides text-warning me-2"></i> Presentations</span>
                                <span class="badge bg-primary rounded-pill">5</span>
                            </a>
                            <a href="search.php?type=resources&q=filetype:xls OR filetype:xlsx" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-file-earmark-spreadsheet text-success me-2"></i> Spreadsheets</span>
                                <span class="badge bg-primary rounded-pill">3</span>
                            </a>
                            <a href="search.php?type=resources&q=filetype:jpg OR filetype:jpeg OR filetype:png" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-file-earmark-image text-info me-2"></i> Images</span>
                                <span class="badge bg-primary rounded-pill">7</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Resource Modal -->
    <div class="modal fade" id="uploadResourceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload New Resource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="upload_resources.php" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Group</label>
                            <select name="group_id" class="form-select" required>
                                <option value="">Choose a group</option>
                                <?php
                                $conn = new mysqli("localhost", "root", "", "peer_learning_db");
                                $groups = $conn->query("
                                    SELECT g.id, g.name 
                                    FROM groups g
                                    JOIN group_members gm ON g.id = gm.group_id
                                    WHERE gm.user_id = $user_id
                                    ORDER BY g.name
                                ");
                                while($group = $groups->fetch_assoc()): ?>
                                    <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">File</label>
                            <input type="file" name="resource_file" class="form-control" required>
                            <small class="text-muted">Max size: 10MB (PDF, DOC, PPT, XLS, JPG, PNG)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload Resource</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // File upload preview
        document.querySelector('input[name="resource_file"]').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'No file selected';
            document.getElementById('file-selected-name').textContent = fileName;
        });
    </script>
</body>
</html>