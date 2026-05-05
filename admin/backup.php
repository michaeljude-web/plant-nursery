<?php
require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../config/config.php';
require_admin_auth();

date_default_timezone_set('Asia/Manila');

$backupDir   = '/opt/lampp/htdocs/plant/database/';
$backupFiles = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '*.sql');
    if ($files) {
        rsort($files);
        foreach ($files as $f) {
            $backupFiles[] = [
                'name'    => basename($f),
                'size'    => round(filesize($f) / 1024, 2) . ' KB',
                'created' => date('M d, Y H:i:s', filemtime($f)),
            ];
        }
    }
}

$perPage     = 5;
$totalFiles  = count($backupFiles);
$totalPages  = max(1, ceil($totalFiles / $perPage));
$backupJson  = json_encode($backupFiles);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Database Backup – Plant Admin</title>
<link rel="stylesheet" href="/plant/assets/vendor/bootstrap-5/css/bootstrap.min.css">
<link rel="stylesheet" href="/plant/assets/vendor/fontawesome-7/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin/style.css">
<style>
  .backup-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
    padding: 2rem;
  }
  .backup-hero {
    background: linear-gradient(135deg, #1a7f4b 0%, #28a745 60%, #5cb85c 100%);
    border-radius: 12px;
    color: #fff;
    padding: 2rem 2.5rem;
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
  }
  .backup-hero::before {
    content: '';
    position: absolute;
    right: -40px; top: -40px;
    width: 200px; height: 200px;
    border-radius: 50%;
    background: rgba(255,255,255,.08);
  }
  .backup-hero::after {
    content: '';
    position: absolute;
    right: 60px; bottom: -60px;
    width: 160px; height: 160px;
    border-radius: 50%;
    background: rgba(255,255,255,.06);
  }
  .backup-hero .icon-wrap {
    width: 56px; height: 56px;
    background: rgba(255,255,255,.2);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
  }
  .backup-hero h5 { font-weight: 700; font-size: 1.25rem; margin-bottom: .25rem; }
  .backup-hero p  { opacity: .85; font-size: .875rem; margin: 0; }

  #btnBackup {
    background: linear-gradient(135deg, #1a7f4b, #28a745);
    border: none;
    border-radius: 10px;
    color: #fff;
    font-weight: 600;
    padding: .75rem 2rem;
    font-size: 1rem;
    transition: transform .15s, box-shadow .15s;
    box-shadow: 0 4px 14px rgba(40,167,69,.35);
  }
  #btnBackup:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(40,167,69,.45);
  }
  #btnBackup:disabled { opacity: .7; }
  #btnBackup .spinner-border { width: 1rem; height: 1rem; border-width: 2px; }

  #statusBox { display: none; border-radius: 10px; }

  .table-files thead th {
    background: #f8f9fa;
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #6c757d;
    border-bottom: 2px solid #dee2e6;
    padding: .75rem 1rem;
  }
  .table-files tbody td { padding: .75rem 1rem; vertical-align: middle; font-size: .875rem; }
  .table-files tbody tr:hover { background: #f8fffe; }

  .badge-sql {
    background: #e8f5e9;
    color: #2e7d32;
    font-size: .7rem;
    font-weight: 600;
    padding: .25em .6em;
    border-radius: 6px;
  }
  .btn-dl {
    background: #fff;
    border: 1.5px solid #28a745;
    color: #28a745;
    border-radius: 8px;
    font-size: .8rem;
    font-weight: 600;
    padding: .35rem .85rem;
    transition: background .15s, color .15s;
  }
  .btn-dl:hover { background: #28a745; color: #fff; }

  .empty-state { text-align: center; padding: 2.5rem 1rem; color: #adb5bd; }
  .empty-state i { font-size: 2.5rem; margin-bottom: .75rem; }

  .pagination .page-link {
    color: #28a745;
    border-radius: 8px !important;
    margin: 0 2px;
    font-size: .85rem;
    font-weight: 600;
  }
  .pagination .page-item.active .page-link {
    background: #28a745;
    border-color: #28a745;
    color: #fff;
  }
  .pagination .page-link:hover { background: #e8f5e9; color: #1a7f4b; }
  .pagination .page-item.disabled .page-link { color: #adb5bd; }
  .page-info { font-size: .8rem; color: #6c757d; }
</style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/admin/sidebar.php'; ?>

<div id="content">
  <div id="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-light d-md-none" id="toggler">
        <i class="fas fa-bars"></i>
      </button>
      <span class="fw-semibold text-dark">Database Backup</span>
    </div>
    <small class="text-muted"><?= date('D, M d Y') ?></small>
  </div>

  <div class="container-fluid px-4 py-3">

    <div class="backup-hero">
      <div class="icon-wrap"><i class="fas fa-database"></i></div>
      <h5>Plant Database Backup</h5>
      <p>Create a full SQL dump of the <strong>plant</strong> database</p>
    </div>

    <div class="backup-card mb-4">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <h6 class="fw-bold mb-1">Create New Backup</h6>
          <p class="text-muted mb-0" style="font-size:.875rem;">
            Exports all tables and data as a <code>.sql</code> file. Available for download instantly.
          </p>
        </div>
        <button id="btnBackup">
          <i class="fas fa-download me-2"></i>Backup Now
        </button>
      </div>
      <div id="statusBox" class="alert mt-3 mb-0"></div>
    </div>

    <div class="backup-card">
      <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h6 class="fw-bold mb-0">
          <i class="fas fa-history me-2 text-success"></i>Existing Backups
          <span class="badge bg-success ms-2" id="backupCount"><?= $totalFiles ?></span>
        </h6>
        <span class="page-info" id="pageInfo"></span>
      </div>

      <div id="emptyState" class="empty-state <?= $totalFiles > 0 ? 'd-none' : '' ?>">
        <i class="fas fa-folder-open d-block"></i>
        No backups yet. Click <strong>Backup Now</strong> to create the first one.
      </div>

      <div id="tableWrap" class="<?= $totalFiles === 0 ? 'd-none' : '' ?>">
        <div class="table-responsive">
          <table class="table table-files mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>Filename</th>
                <th>Size</th>
                <th>Created</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="backupTableBody"></tbody>
          </table>
        </div>

        <div class="d-flex align-items-center justify-content-between mt-3 flex-wrap gap-2">
          <span class="page-info" id="pageInfoBottom"></span>
          <nav>
            <ul class="pagination mb-0" id="pagination"></ul>
          </nav>
        </div>
      </div>

    </div>

  </div>
</div>

<script src="/plant/assets/vendor/bootstrap-5/js/bootstrap.bundle.min.js"></script>
<script>
const allFiles  = <?= $backupJson ?>;
const perPage   = 5;
let currentPage = 1;

function renderTable(page) {
  const tbody = document.getElementById('backupTableBody');
  const start = (page - 1) * perPage;
  const end   = start + perPage;
  const slice = allFiles.slice(start, end);
  const total = allFiles.length;

  tbody.innerHTML = '';
  slice.forEach((f, i) => {
    const num = start + i + 1;
    const tr  = document.createElement('tr');
    tr.innerHTML = `
      <td>${num}</td>
      <td><span class="badge-sql me-2">SQL</span>${f.name}</td>
      <td>${f.size}</td>
      <td>${f.created}</td>
      <td>
        <a href="/plant/database/${encodeURIComponent(f.name)}" class="btn-dl" download>
          <i class="fas fa-download me-1"></i>Download
        </a>
      </td>`;
    tbody.appendChild(tr);
  });

  const totalPages = Math.max(1, Math.ceil(total / perPage));
  const from       = total === 0 ? 0 : start + 1;
  const to         = Math.min(end, total);
  const infoText   = total === 0 ? '' : `Showing ${from}–${to} of ${total} backups`;

  document.getElementById('pageInfo').textContent       = infoText;
  document.getElementById('pageInfoBottom').textContent = infoText;

  renderPagination(page, totalPages);
  currentPage = page;
}

function renderPagination(page, totalPages) {
  const ul = document.getElementById('pagination');
  ul.innerHTML = '';

  const mkLi = (label, p, disabled, active) => {
    const li  = document.createElement('li');
    li.className = `page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}`;
    const a   = document.createElement('a');
    a.className   = 'page-link';
    a.href        = '#';
    a.innerHTML   = label;
    if (!disabled && !active) {
      a.addEventListener('click', e => { e.preventDefault(); renderTable(p); });
    }
    li.appendChild(a);
    return li;
  };

  ul.appendChild(mkLi('<i class="fas fa-chevron-left"></i>', page - 1, page === 1, false));

  for (let i = 1; i <= totalPages; i++) {
    ul.appendChild(mkLi(i, i, false, i === page));
  }

  ul.appendChild(mkLi('<i class="fas fa-chevron-right"></i>', page + 1, page === totalPages, false));
}

function addNewBackup(filename, size, path) {
  allFiles.unshift({ name: filename, size: size, created: new Date().toLocaleString('en-PH', {
    month: 'short', day: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
  })});

  const emptyState = document.getElementById('emptyState');
  const tableWrap  = document.getElementById('tableWrap');
  emptyState.classList.add('d-none');
  tableWrap.classList.remove('d-none');

  document.getElementById('backupCount').textContent = allFiles.length;
  renderTable(1);
}

if (allFiles.length > 0) renderTable(1);

document.getElementById('btnBackup').addEventListener('click', async function () {
  const btn       = this;
  const statusBox = document.getElementById('statusBox');

  btn.disabled  = true;
  btn.innerHTML = '<span class="spinner-border me-2"></span>Creating backup…';
  statusBox.style.display = 'none';
  statusBox.className     = 'alert mt-3 mb-0';

  try {
    const res  = await fetch('/plant/includes/admin/do_backup.php', {
      method : 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await res.json();

    if (data.success) {
      statusBox.classList.add('alert-success');
      statusBox.innerHTML = `
        <i class="fas fa-check-circle me-2"></i>
        <strong>Backup created!</strong>
        <br><small>
          File: <code>${data.filename}</code> &nbsp;|&nbsp;
          Size: ${data.size} &nbsp;|&nbsp;
          Tables: ${data.tables}
        </small>
        <div class="mt-2">
          <a href="${data.path}" class="btn btn-sm btn-success" download>
            <i class="fas fa-download me-1"></i>Download Now
          </a>
        </div>`;
      addNewBackup(data.filename, data.size, data.path);
    } else {
      statusBox.classList.add('alert-danger');
      statusBox.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i>
        <strong>Error:</strong> ${data.message}`;
    }
  } catch (err) {
    statusBox.classList.add('alert-danger');
    statusBox.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i>
      <strong>Network error:</strong> ${err.message}`;
  }

  statusBox.style.display = 'block';
  btn.disabled  = false;
  btn.innerHTML = '<i class="fas fa-download me-2"></i>Backup Now';
});
</script>
</body>
</html>