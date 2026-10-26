<?php
$mysqli = new mysqli("forum_db", "forum_user", "StrongP@ss2026!", "forum_db");
if ($mysqli->connect_error) { die("DB Error: " . $mysqli->connect_error); }
$mysqli->set_charset("utf8mb4");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["action"]) && $_POST["action"] === "create_post") {
        $title = $mysqli->real_escape_string($_POST["title"]);
        $content = $mysqli->real_escape_string($_POST["content"]);
        $author = $mysqli->real_escape_string($_POST["author"] ?: "Vô danh");
        $cid = intval($_POST["community_id"] ?? 1);
        
        $filePath = NULL;
        if (isset($_FILES["attachment"]) && $_FILES["attachment"]["error"] === UPLOAD_ERR_OK) {
            $uploadDir = "uploads/";
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
            $rawName = basename($_FILES["attachment"]["name"]);
            $cleanName = time() . "_" . preg_replace("/[^a-zA-Z0-9\._-]/", "_", $rawName);
            $targetPath = $uploadDir . $cleanName;
            if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $targetPath)) { $filePath = $targetPath; }
        }
        $stmt = $mysqli->prepare("INSERT INTO posts (community_id, title, content, author, file_path) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $cid, $title, $content, $author, $filePath);
        $stmt->execute();
        $redirectTab = isset($_GET["tab"]) ? "&tab=" . $_GET["tab"] : "";
        header("Location: index.php" . ($cid > 0 ? "?cid=$cid$redirectTab" : ($redirectTab ? "?".substr($redirectTab,1) : ""))); exit;
    }
    if (isset($_POST["action"]) && $_POST["action"] === "create_community") {
        $cname = $mysqli->real_escape_string(trim($_POST["cname"]));
        $cdesc = $mysqli->real_escape_string(trim($_POST["cdesc"] ?? ""));
        if (!empty($cname)) {
            $stmt = $mysqli->prepare("INSERT INTO communities (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $cname, $cdesc);
            $stmt->execute();
        }
        header("Location: index.php"); exit;
    }
    if (isset($_POST["action"]) && $_POST["action"] === "add_comment") {
        $post_id = intval($_POST["post_id"]);
        $author = $mysqli->real_escape_string($_POST["author"] ?: "Vô danh");
        $content = $mysqli->real_escape_string($_POST["content"]);
        $stmt = $mysqli->prepare("INSERT INTO comments (post_id, author, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $post_id, $author, $content);
        $stmt->execute();
        $redirectCid = intval($_POST["current_cid"] ?? 0);
        $redirectTab = isset($_GET["tab"]) ? "&tab=" . $_GET["tab"] : "";
        header("Location: index.php" . ($redirectCid > 0 ? "?cid=$redirectCid$redirectTab" : ($redirectTab ? "?".substr($redirectTab,1) : ""))); exit;
    }
}
if (isset($_GET["delete_post"])) {
    $pid = intval($_GET["delete_post"]);
    $mysqli->query("DELETE FROM posts WHERE id = $pid");
    $redirectCid = intval($_GET["cid"] ?? 0);
    $redirectTab = isset($_GET["tab"]) ? "&tab=" . $_GET["tab"] : "";
    header("Location: index.php" . ($redirectCid > 0 ? "?cid=$redirectCid$redirectTab" : ($redirectTab ? "?".substr($redirectTab,1) : ""))); exit;
}
if (isset($_GET["delete_comment"])) {
    $cmid = intval($_GET["delete_comment"]);
    $mysqli->query("DELETE FROM comments WHERE id = $cmid");
    $redirectCid = intval($_GET["cid"] ?? 0);
    $redirectTab = isset($_GET["tab"]) ? "&tab=" . $_GET["tab"] : "";
    header("Location: index.php" . ($redirectCid > 0 ? "?cid=$redirectCid$redirectTab" : ($redirectTab ? "?".substr($redirectTab,1) : ""))); exit;
}
if (isset($_GET["like"])) {
    $pid = intval($_GET["like"]);
    $mysqli->query("UPDATE posts SET likes = likes + 1 WHERE id = $pid");
    $redirectCid = intval($_GET["cid"] ?? 0);
    $redirectTab = isset($_GET["tab"]) ? "&tab=" . $_GET["tab"] : "";
    header("Location: index.php" . ($redirectCid > 0 ? "?cid=$redirectCid$redirectTab" : ($redirectTab ? "?".substr($redirectTab,1) : ""))); exit;
}

$cid = isset($_GET["cid"]) ? intval($_GET["cid"]) : 0;
$tab = isset($_GET["tab"]) && $_GET["tab"] === "hot" ? "hot" : "latest";
$search = isset($_GET["search"]) ? $mysqli->real_escape_string(trim($_GET["search"])) : "";

$selected_community = null;
if ($cid > 0) {
    $cRes = $mysqli->query("SELECT * FROM communities WHERE id = $cid");
    if ($cRes && $cRes->num_rows > 0) { $selected_community = $cRes->fetch_assoc(); }
}

$total_posts = $mysqli->query("SELECT COUNT(*) as cnt FROM posts")->fetch_assoc()['cnt'] ?? 0;
$total_authors = $mysqli->query("SELECT COUNT(DISTINCT author) as cnt FROM posts")->fetch_assoc()['cnt'] ?? 0;
$total_communities = $mysqli->query("SELECT COUNT(*) as cnt FROM communities")->fetch_assoc()['cnt'] ?? 0;
$total_interactions = $mysqli->query("SELECT (SUM(likes) + (SELECT COUNT(*) FROM comments)) as total FROM posts")->fetch_assoc()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>TechForum Community</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar-custom { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); }
        .card { border: none; border-radius: 12px; }
        .btn-custom { border-radius: 8px; font-weight: 600; }
        .avatar-box { width: 42px; height: 42px; background: linear-gradient(45deg, #0d6efd, #0dcaf0); color: white; font-weight: bold; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .audio-player-box { background: #eef2f7; border-left: 4px solid #0d6efd; border-radius: 8px; padding: 12px; }
        .img-zoomable { cursor: pointer; transition: transform 0.2s ease, filter 0.2s ease; }
        .img-zoomable:hover { transform: scale(1.02); filter: brightness(0.95); }
        .hero-banner { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 25px; }
        .community-header-card { background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); color: white; border-radius: 12px; padding: 20px 25px; }
        .stat-card { background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(5px); border-radius: 12px; padding: 12px 20px; text-align: center; border: 1px solid rgba(255, 255, 255, 0.2); }
        .modal-giant { max-width: 90vw !important; width: 90vw !important; }
        .img-giant-preview { width: 75vw !important; height: auto !important; max-height: 85vh !important; object-fit: contain; margin: 0 auto; display: block; }
        .nav-pills .nav-link { font-weight: 600; border-radius: 20px; padding: 8px 20px; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4 shadow">
    <div class="container">
        <a class="navbar-brand fw-bold fs-4" href="index.php"><i class="fa-solid fa-comments me-2"></i>TechForum Community</a>
        <span class="badge bg-success px-3 py-2 rounded-pill"><i class="fa-solid fa-server me-1"></i>Docker LAMP Active</span>
    </div>
</nav>

<div class="container">
    <div class="hero-banner shadow">
        <div class="row align-items-center">
            <div class="col-lg-7 mb-3 mb-lg-0">
                <h3 class="fw-bold mb-2"><i class="fa-solid fa-hand-wave me-2"></i>Chào mừng đến với TechForum Community!</h3>
                <p class="mb-0 text-light opacity-90" style="font-size: 0.98rem;">Nơi kết nối đam mê công nghệ, chia sẻ âm nhạc chất lượng cao và thảo luận kiến thức lập trình trên nền tảng Docker LAMP Stack hiện đại.</p>
            </div>
            <div class="col-lg-5">
                <div class="row g-2">
                    <div class="col-4">
                        <div class="stat-card shadow-sm">
                            <i class="fa-solid fa-newspaper fs-4 mb-1"></i>
                            <h4 class="fw-bold mb-0"><?= $total_posts ?></h4>
                            <small style="font-size: 0.75rem;">Bài viết</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-card shadow-sm">
                            <i class="fa-solid fa-users fs-4 mb-1"></i>
                            <h4 class="fw-bold mb-0"><?= $total_authors ?></h4>
                            <small style="font-size: 0.75rem;">Thành viên</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-card shadow-sm">
                            <i class="fa-solid fa-layer-group fs-4 mb-1"></i>
                            <h4 class="fw-bold mb-0"><?= $total_communities ?></h4>
                            <small style="font-size: 0.75rem;">Chủ đề</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="index.php" class="row g-2 align-items-center">
                <?php if($cid > 0): ?><input type="hidden" name="cid" value="<?= $cid ?>"><?php endif; ?>
                <?php if($tab === 'hot'): ?><input type="hidden" name="tab" value="hot"><?php endif; ?>
                <div class="col-md-10">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Tìm kiếm theo Cộng đồng -> Tác giả -> Tiêu đề bài viết..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-custom w-100"><i class="fa-solid fa-search me-1"></i>Tìm</button>
                    <?php if(!empty($_GET['search'])): ?>
                        <a href="index.php<?= $cid > 0 ? "?cid=$cid" : "" ?>" class="btn btn-outline-secondary btn-custom" title="Xóa tìm kiếm"><i class="fa-solid fa-xmark"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-bold text-primary py-3"><i class="fa-solid fa-layer-group me-2"></i>Cộng Đồng</div>
                <div class="list-group list-group-flush px-2 pb-2">
                    <a href="index.php<?= $tab === 'hot' ? '?tab=hot' : '' ?>" class="list-group-item list-group-item-action rounded-3 mb-1 <?= ($cid === 0 && empty($search)) ? 'active' : '' ?>">
                        <i class="fa-solid fa-globe me-2"></i>Tất cả bài viết
                    </a>
                    <?php
                    $res = $mysqli->query("SELECT * FROM communities");
                    while($c = $res->fetch_assoc()) {
                        $isActive = ($cid === intval($c['id'])) ? 'active fw-bold' : '';
                        $tabParam = ($tab === 'hot') ? '&tab=hot' : '';
                        echo "<a href='index.php?cid=" . $c["id"] . "$tabParam' class='list-group-item list-group-item-action rounded-3 mb-1 $isActive'>
                                <i class='fa-solid fa-hashtag me-2 " . ($isActive ? 'text-white' : 'text-primary') . "'></i>" . htmlspecialchars($c["name"]) . "
                              </a>";
                    }
                    ?>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold text-primary py-3"><i class="fa-solid fa-folder-plus me-2"></i>Tạo Cộng Đồng Mới</div>
                <div class="card-body p-3">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="create_community">
                        <div class="mb-2"><input type="text" name="cname" class="form-control form-control-sm" placeholder="Tên cộng đồng..." required></div>
                        <div class="mb-3"><input type="text" name="cdesc" class="form-control form-control-sm" placeholder="Mô tả ngắn..."></div>
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100 fw-bold rounded-2"><i class="fa-solid fa-plus me-1"></i>Tạo Mới</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <?php if ($selected_community): ?>
                <div class="community-header-card shadow-sm mb-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="fw-bold mb-1"><i class="fa-solid fa-hashtag me-2"></i>Cộng đồng: <?= htmlspecialchars($selected_community['name']) ?></h3>
                        <p class="mb-0 text-white-50 fs-6"><?= htmlspecialchars($selected_community['description'] ?: 'Thảo luận và chia sẻ nội dung thuộc chủ đề này.') ?></p>
                    </div>
                    <a href="index.php<?= $tab === 'hot' ? '?tab=hot' : '' ?>" class="btn btn-light btn-sm fw-bold text-primary px-3 rounded-pill"><i class="fa-solid fa-arrow-left me-1"></i>Xem tất cả</a>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold text-primary py-3">
                    <i class="fa-solid fa-pen-to-square me-2"></i>Tạo bài viết <?php if ($selected_community): ?>cho cộng đồng <span class="badge bg-primary fs-6">#<?= htmlspecialchars($selected_community['name']) ?></span><?php else: ?>/ Thảo luận mới<?php endif; ?>
                </div>
                <div class="card-body">
                    <form action="index.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create_post">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary">Tác giả</label>
                                <input type="text" name="author" class="form-control" placeholder="Tên tác giả..." required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary">Chọn cộng đồng</label>
                                <select name="community_id" class="form-select">
                                    <?php
                                    $res = $mysqli->query("SELECT * FROM communities");
                                    while($c = $res->fetch_assoc()) {
                                        $selected = ($cid === intval($c['id'])) ? 'selected' : '';
                                        echo "<option value='" . $c["id"] . "' $selected>" . htmlspecialchars($c["name"]) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">Tiêu đề bài viết</label>
                            <input type="text" name="title" class="form-control" placeholder="Nhập tiêu đề..." required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">Nội dung thảo luận</label>
                            <textarea name="content" class="form-control" rows="3" placeholder="Chia sẻ cảm nhận, kiến thức..." required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary"><i class="fa-solid fa-paperclip me-1 text-primary"></i>Tệp đính kèm (Hình ảnh hoặc MP3 Audio)</label>
                            <input type="file" name="attachment" class="form-control" accept="audio/*,image/*">
                        </div>
                        <button type="submit" class="btn btn-primary btn-custom w-100 py-2"><i class="fa-solid fa-paper-plane me-2"></i>Đăng Bài Viết</button>
                    </form>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <ul class="nav nav-pills">
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'latest' ? 'active' : 'bg-white text-secondary' ?>" href="index.php?tab=latest<?= $cid > 0 ? "&cid=$cid" : "" ?>"><i class="fa-solid fa-clock me-1"></i>Mới nhất</a>
                    </li>
                    <li class="nav-item ms-2">
                        <a class="nav-link <?= $tab === 'hot' ? 'active bg-danger text-white' : 'bg-white text-secondary' ?>" href="index.php?tab=hot<?= $cid > 0 ? "&cid=$cid" : "" ?>"><i class="fa-solid fa-fire me-1"></i>Nổi bật</a>
                    </li>
                </ul>
            </div>

            <?php
            if ($tab === 'hot' && $total_interactions == 0 && empty($search)) {
                echo "<div class='card shadow-sm p-5 text-center my-3 border-0 rounded-4' style='background: #fff3cd; color: #664d03;'>
                        <div class='mb-3'><i class='fa-solid fa-seedling display-1 text-warning'></i></div>
                        <h3 class='fw-bold mb-2'>🌱 Vườn Không Nhà Trống!</h3>
                        <p class='fs-6 mb-3'>Chưa có bài viết nào nhận được tương tác (Thích hoặc Bình luận).</p>
                      </div>";
            } else {
                if (!empty($search)) {
                    $sql = "SELECT p.*, c.name as community_name, (SELECT COUNT(*) FROM comments cm WHERE cm.post_id = p.id) as total_comments FROM posts p LEFT JOIN communities c ON p.community_id = c.id WHERE c.name LIKE '%$search%' OR p.author LIKE '%$search%' OR p.title LIKE '%$search%' ORDER BY p.id DESC";
                } elseif ($tab === 'hot') {
                    $sql = "SELECT p.*, c.name as community_name, (p.likes + (SELECT COUNT(*) FROM comments cm WHERE cm.post_id = p.id)) as score, (SELECT COUNT(*) FROM comments cm WHERE cm.post_id = p.id) as total_comments FROM posts p LEFT JOIN communities c ON p.community_id = c.id " . ($cid > 0 ? "WHERE p.community_id = $cid " : "") . "ORDER BY score DESC, p.likes DESC, p.id DESC";
                } else {
                    $sql = "SELECT p.*, c.name as community_name, (SELECT COUNT(*) FROM comments cm WHERE cm.post_id = p.id) as total_comments FROM posts p LEFT JOIN communities c ON p.community_id = c.id " . ($cid > 0 ? "WHERE p.community_id = $cid " : "") . "ORDER BY p.id DESC";
                }
                $posts = $mysqli->query($sql);
                while($p = $posts->fetch_assoc()) {
                    $pid = $p["id"];
                    $cmCount = $p["total_comments"] ?? 0;
                    echo "<div class='card shadow-sm mb-4'>
                        <div class='card-body'>
                            <div class='d-flex justify-content-between align-items-center mb-3'>
                                <div class='d-flex align-items-center gap-2'>
                                    <div class='avatar-box'>" . strtoupper(substr($p["author"],0,1)) . "</div>
                                    <div>
                                        <h6 class='mb-0 fw-bold text-dark'>" . htmlspecialchars($p["author"]) . " <span class='badge bg-light text-primary border ms-1'>#" . htmlspecialchars($p["community_name"] ?? "Chung") . "</span></h6>
                                        <small class='text-muted'>" . $p["created_at"] . "</small>
                                    </div>
                                </div>
                                <a href='index.php?delete_post=" . $pid . "' class='btn btn-outline-danger btn-sm rounded-circle' onclick='return confirm(\"Xóa bài viết?\")'><i class='fa-solid fa-trash'></i></a>
                            </div>
                            <h4 class='card-title fw-bold text-primary mb-2'>" . htmlspecialchars($p["title"]) . "</h4>
                            <p class='card-text text-dark fs-6'>" . nl2br(htmlspecialchars($p["content"])) . "</p>";
                            if (!empty($p["file_path"])) {
                                $ext = strtolower(pathinfo($p["file_path"], PATHINFO_EXTENSION));
                                if (in_array($ext, ["mp3", "wav", "ogg"])) {
                                    echo "<div class='audio-player-box my-3'><audio controls class='w-100'><source src='" . htmlspecialchars($p["file_path"]) . "'></audio></div>";
                                } else {
                                    echo "<div class='my-3'><img src='" . htmlspecialchars($p["file_path"]) . "' class='img-fluid rounded-3 img-zoomable' style='max-height:350px;' onclick='openImageModal(this.src)'></div>";
                                }
                            }
                    echo "  <div class='d-flex align-items-center gap-3 my-3'>
                                <a href='index.php?like=" . $pid . "' class='btn btn-light btn-sm text-primary fw-bold px-3 py-1 rounded-pill'><i class='fa-solid fa-thumbs-up me-1'></i>Thích (" . $p["likes"] . ")</a>
                                <span class='text-secondary small fw-bold'><i class='fa-solid fa-comment me-1 text-info'></i>" . $cmCount . " Bình luận</span>
                            </div>
                            <div class='bg-light p-3 rounded-3 mt-3'>
                                <h6 class='fw-bold text-secondary mb-3'>Bình luận</h6>";
                                $cmRes = $mysqli->query("SELECT * FROM comments WHERE post_id = $pid ORDER BY id ASC");
                                while($cm = $cmRes->fetch_assoc()) {
                                    echo "<div class='border-bottom pb-2 mb-2 d-flex justify-content-between'><div><strong>" . htmlspecialchars($cm["author"]) . "</strong>: " . htmlspecialchars($cm["content"]) . "</div><a href='index.php?delete_comment=" . $cm["id"] . "' class='text-danger ms-2'><i class='fa-solid fa-xmark'></i></a></div>";
                                }
                    echo "      <form method='POST' class='d-flex gap-2 mt-3'>
                                    <input type='hidden' name='action' value='add_comment'>
                                    <input type='hidden' name='post_id' value='" . $pid . "'>
                                    <input type='text' name='author' class='form-control form-control-sm' style='width:25%;' placeholder='Tên...'>
                                    <input type='text' name='content' class='form-control form-control-sm' placeholder='Nhập bình luận...' required>
                                    <button type='submit' class='btn btn-primary btn-sm px-3'><i class='fa-solid fa-paper-plane'></i></button>
                                </form>
                            </div>
                        </div>
                    </div>";
                }
            }
            ?>
        </div>
    </div>
</div>

<div class="modal fade" id="imageViewerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-giant">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body text-center p-0 position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" style="z-index: 1060; background-color: rgba(0,0,0,0.8); padding: 12px; border-radius: 50%;"></button>
                <img id="modalPreviewImage" src="" class="rounded-3 shadow-lg img-giant-preview">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openImageModal(imageSrc) {
    document.getElementById('modalPreviewImage').src = imageSrc;
    var imageModal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
    imageModal.show();
}
</script>
</body>
</html>
