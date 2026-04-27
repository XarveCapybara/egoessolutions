<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'employee') {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/eg_employee_suspension_guard.php';

$name = $_SESSION['display_name'] ?? 'Employee';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$memos = [];
$error = null;

function eg_normalize_memo_letter(?string $notes): string
{
    $text = trim((string) $notes);
    if ($text === '') {
        return '';
    }

    // Normalize line endings first so duplicate detection is consistent.
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    // Convert escaped newline sequences into actual line breaks.
    $text = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $text);

    // Remove accidental duplicated blocks joined by separators.
    $parts = preg_split('/\n\s*---\s*\n/u', $text) ?: [$text];

    // Also split repeated A-01 letter bodies even without separators.
    $subjectPattern = '/(?=^Subject:\s*Formal Notice:\s*Late Login\/Arrival\s*\(Code A-01\))/mi';
    $expandedParts = [];
    foreach ($parts as $part) {
        $subParts = preg_split($subjectPattern, (string) $part) ?: [(string) $part];
        $hasMultipleSubjects = preg_match_all('/^Subject:\s*Formal Notice:\s*Late Login\/Arrival\s*\(Code A-01\)/mi', (string) $part) > 1;
        foreach ($subParts as $sp) {
            $spTrim = trim((string) $sp);
            if ($spTrim === '') {
                continue;
            }
            // Keep non-sub-split content intact unless we truly detected repeated subjects.
            if (!$hasMultipleSubjects && stripos($spTrim, 'Subject: Formal Notice: Late Login/Arrival (Code A-01)') !== 0) {
                $expandedParts[] = trim((string) $part);
                break;
            }
            $expandedParts[] = $spTrim;
        }
    }
    $parts = !empty($expandedParts) ? $expandedParts : $parts;
    $seen = [];
    $unique = [];
    foreach ($parts as $part) {
        $p = trim((string) $part);
        if ($p === '') {
            continue;
        }
        // Collapse repeated whitespace/newlines for stable matching.
        $key = preg_replace('/[ \t]+/u', ' ', $p);
        $key = preg_replace('/\n{2,}/u', "\n\n", (string) $key);
        $key = trim((string) $key);
        if ($key === '') {
            continue;
        }

        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $unique[] = $p;
        }
    }
    if (empty($unique)) {
        return $text;
    }
    // Show a single clean letter block instead of repeated sections.
    return $unique[0];
}

if ($userId > 0) {
    try {
        $hasMemoTable = $pdo->query("SHOW TABLES LIKE 'employee_memos'")->rowCount() > 0;
        if ($hasMemoTable) {
            $stmt = $pdo->prepare(
                'SELECT violation_code, violation_name, offense_number, consequence, consequence_type, suspension_days, suspension_start, suspension_end, memo_notes, status, created_at
                 FROM employee_memos
                 WHERE user_id = ?
                 ORDER BY created_at DESC, id DESC'
            );
            $stmt->execute([$userId]);
            $memos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($memos as &$memo) {
                $memo['memo_notes'] = eg_normalize_memo_letter((string) ($memo['memo_notes'] ?? ''));
            }
            unset($memo);
        }
    } catch (Throwable $e) {
        $error = 'Unable to load memorandum records.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-GOES Solutions</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous"
    />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="../assets/css/style.css" />
  </head>
  <body class="bg-light">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container-fluid">
      <div class="row">
        <?php include __DIR__ . '/../includes/sidebar_employee.php'; ?>

        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="mb-3 fw-bold">Memorandum</h3>
          <p class="text-muted mb-4">Violations, offense records, and disciplinary consequences issued to your account.</p>

          <?php if ($error !== null): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>

          <div class="eg-panel">
            <?php if (empty($memos)): ?>
              <p class="text-muted mb-0">No memorandums issued.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Date</th>
                      <th>Violation</th>
                      <th>Offense</th>
                      <th>Consequence</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($memos as $idx => $memo): ?>
                      <?php
                      $status = strtolower((string) ($memo['status'] ?? 'resolved'));
                      $badgeClass = $status === 'active' ? 'bg-danger' : 'bg-secondary';
                      $memoModalId = 'memoLetterModal' . (int) $idx;
                      ?>
                      <tr>
                        <td><?= htmlspecialchars(date('M d, Y', strtotime((string) ($memo['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                          <button
                            type="button"
                            class="btn btn-link p-0 text-start text-decoration-none"
                            data-bs-toggle="modal"
                            data-bs-target="#<?= htmlspecialchars($memoModalId, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="fw-semibold"><?= htmlspecialchars((string) ($memo['violation_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($memo['violation_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                          </button>
                        </td>
                        <td><?= (int) ($memo['offense_number'] ?? 0) ?></td>
                        <td>
                          <div><?= htmlspecialchars((string) ($memo['consequence'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                          <?php if (($memo['consequence_type'] ?? '') === 'suspension'): ?>
                            <div class="small text-muted">
                              <?= htmlspecialchars((string) ($memo['suspension_start'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                              to
                              <?= htmlspecialchars((string) ($memo['suspension_end'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                              (<?= (int) ($memo['suspension_days'] ?? 0) ?> day(s))
                            </div>
                          <?php endif; ?>
                          <?php if (!empty($memo['memo_notes'])): ?>
                            <div class="small text-muted"><?= htmlspecialchars((string) $memo['memo_notes'], ENT_QUOTES, 'UTF-8') ?></div>
                          <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?></span></td>
                      </tr>
                      <div class="modal fade" id="<?= htmlspecialchars($memoModalId, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                          <div class="modal-content">
                            <div class="modal-header">
                              <h5 class="modal-title">
                                <?= htmlspecialchars((string) ($memo['violation_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                - <?= htmlspecialchars((string) ($memo['violation_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                              </h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                              <p class="mb-2 small text-muted">
                                Issued: <?= htmlspecialchars(date('M d, Y', strtotime((string) ($memo['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?>
                                | Offense #<?= (int) ($memo['offense_number'] ?? 0) ?>
                              </p>
                              <p class="mb-2"><strong>Consequence:</strong> <?= htmlspecialchars((string) ($memo['consequence'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                              <hr />
                              <h6 class="mb-2">Letter</h6>
                              <?php if (!empty($memo['memo_notes'])): ?>
                                <div class="small" style="white-space: pre-wrap;"><?= htmlspecialchars((string) $memo['memo_notes'], ENT_QUOTES, 'UTF-8') ?></div>
                              <?php else: ?>
                                <p class="text-muted mb-0">No letter content available for this memorandum.</p>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </main>
      </div>
    </div>

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </body>
</html>
