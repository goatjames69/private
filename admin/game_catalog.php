<?php
require_once '../config.php';
requireStaffOrAdmin();
if (!isAdmin()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$gamesDir = __DIR__ . '/../gameslogo';
if (!file_exists($gamesDir)) mkdir($gamesDir, 0755, true);

$success = '';
$error = '';

// Remove game
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_game'])) {
    $id = trim($_POST['game_id'] ?? '');
    $games = getGamesConfig();
    $games = array_values(array_filter($games, function($g) use ($id) { return ($g['id'] ?? '') !== $id; }));
    saveGamesConfig($games);
    $success = 'Game removed.';
}

// Add or edit game
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_game']) || isset($_POST['edit_game']))) {
    $name = trim($_POST['name'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $editId = trim($_POST['edit_id'] ?? '');
    if ($name === '') {
        $error = 'Game name is required.';
    } else {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '', str_replace(' ', '', $name)));
        if ($slug === '') $slug = 'game' . time();
        $logoFilename = '';
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['logo'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($file['type'], $allowed)) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
                $logoFilename = $slug . '_' . time() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $gamesDir . '/' . $logoFilename)) {
                    $logoFilename = $logoFilename;
                }
            }
        }
        $games = getGamesConfig();
        if ($editId !== '') {
            foreach ($games as &$g) {
                if (($g['id'] ?? '') === $editId) {
                    $g['name'] = $name;
                    $g['slug'] = $slug;
                    if ($logoFilename !== '') $g['logo'] = $logoFilename;
                    $g['link'] = $link;
                    break;
                }
            }
            unset($g);
            $success = 'Game updated.';
        } else {
            $games[] = [
                'id' => uniqid('g', true),
                'name' => $name,
                'slug' => $slug,
                'logo' => $logoFilename ?: '',
                'link' => $link,
            ];
            $success = 'Game added.';
        }
        saveGamesConfig($games);
    }
}

$games = getGamesConfig();

$adminPageTitle = 'Game Catalog';
$adminCurrentPage = 'game_catalog';
$adminPageSubtitle = 'Add, edit, or remove games. Set game link for users.';
if (!isset($pendingCounts)) $pendingCounts = [];
require __DIR__ . '/_header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <h3 class="admin-section-title"><i class="fas fa-plus-circle"></i> Add New Game</h3>
    <form method="POST" action="" enctype="multipart/form-data" style="display: grid; gap: 16px; max-width: 480px;">
        <div class="form-group">
            <label>Game Name *</label>
            <input type="text" name="name" class="form-control" required placeholder="e.g. Orion">
        </div>
        <div class="form-group">
            <label>Logo (image)</label>
            <input type="file" name="logo" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
        </div>
        <div class="form-group">
            <label>Game Link (URL)</label>
            <input type="url" name="link" class="form-control" placeholder="https://... (optional; users open this instead of internal page)">
        </div>
        <input type="hidden" name="add_game" value="1">
        <button type="submit" class="btn"><i class="fas fa-plus"></i> Add Game</button>
    </form>
</div>

<div class="card">
    <h3 class="admin-section-title"><i class="fas fa-gamepad"></i> Current Games</h3>
    <?php if (empty($games)): ?>
        <p style="color: var(--text-muted);">No games yet. Add one above.</p>
    <?php else: ?>
        <div class="table-wrap" style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Logo</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Link</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($games as $g):
                        $logoPath = getGameLogo($g);
                    ?>
                        <tr>
                            <td>
                                <?php if ($logoPath): ?>
                                    <img src="<?= htmlspecialchars($logoPath) ?>" alt="" style="width: 48px; height: 48px; object-fit: contain; border-radius: 8px; background: var(--bg-secondary);">
                                <?php else: ?>
                                    <span style="width: 48px; height: 48px; display: inline-flex; align-items: center; justify-content: center; background: var(--bg-secondary); border-radius: 8px; color: var(--text-muted);"><i class="fas fa-image"></i></span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($g['name'] ?? '') ?></strong></td>
                            <td><code style="font-size: 12px;"><?= htmlspecialchars($g['slug'] ?? '') ?></code></td>
                            <td>
                                <?php if (!empty($g['link'])): ?>
                                    <a href="<?= htmlspecialchars($g['link']) ?>" target="_blank" rel="noopener" style="color: var(--accent-primary); font-size: 13px;"><?= htmlspecialchars(strlen($g['link']) > 40 ? substr($g['link'], 0, 40) . '…' : $g['link']) ?></a>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-edit-game" data-id="<?= htmlspecialchars($g['id'] ?? '') ?>" data-name="<?= htmlspecialchars($g['name'] ?? '') ?>" data-link="<?= htmlspecialchars($g['link'] ?? '') ?>"><i class="fas fa-edit"></i> Edit</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this game from the catalog?');">
                                    <input type="hidden" name="game_id" value="<?= htmlspecialchars($g['id'] ?? '') ?>">
                                    <input type="hidden" name="remove_game" value="1">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Edit modal -->
<div id="editModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="max-width: 440px; width: 100%;">
        <h3 class="admin-section-title"><i class="fas fa-edit"></i> Edit Game</h3>
        <form method="POST" action="" enctype="multipart/form-data" id="editForm">
            <input type="hidden" name="edit_id" id="editId">
            <input type="hidden" name="edit_game" value="1">
            <div class="form-group">
                <label>Game Name *</label>
                <input type="text" name="name" id="editName" class="form-control" required>
            </div>
            <div class="form-group">
                <label>New Logo (leave empty to keep current)</label>
                <input type="file" name="logo" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
            </div>
            <div class="form-group">
                <label>Game Link (URL)</label>
                <input type="url" name="link" id="editLink" class="form-control" placeholder="https://...">
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn"><i class="fas fa-save"></i> Save</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.btn-edit-game').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('editId').value = this.getAttribute('data-id') || '';
        document.getElementById('editName').value = this.getAttribute('data-name') || '';
        document.getElementById('editLink').value = this.getAttribute('data-link') || '';
        document.getElementById('editModal').style.display = 'flex';
    });
});
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php require __DIR__ . '/_footer.php'; ?>
