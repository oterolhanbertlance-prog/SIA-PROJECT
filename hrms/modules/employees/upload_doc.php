<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: list.php'); exit; }

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? SITE_URL . 'dashboard.php'));
    exit;
}

$empId   = (int)($_POST['employee_id'] ?? 0);
$docName = sanitize($_POST['document_name'] ?? '');
$cu      = currentUser();

// Access control:
//  • Admin / HR can upload for any employee.
//  • An employee may only upload documents for their own profile.
if (!hasRole('admin', 'hr')) {
    if (!hasRole('employee') || (int)$cu['emp_id'] !== $empId) {
        header('Location: ' . SITE_URL . 'dashboard.php?error=unauthorized');
        exit;
    }
}

if (!$empId || !$docName) {
    header("Location: profile.php?id={$empId}&error=Please+provide+a+document+name");
    exit;
}

if (empty($_FILES['document']['name'])) {
    header("Location: profile.php?id={$empId}&error=Please+select+a+file+to+upload");
    exit;
}

$allowedTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

$path = uploadFile($_FILES['document'], 'documents', $allowedTypes);
if ($path) {
    $db = getDB();
    $db->prepare(
        "INSERT INTO documents (employee_id, document_name, file_path, file_size, uploaded_by)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([
        $empId,
        $docName,
        basename($path),
        $_FILES['document']['size'],
        $cu['id'],
    ]);
    header("Location: profile.php?id={$empId}&success=Document+uploaded+successfully#documents");
    exit;
}

header("Location: profile.php?id={$empId}&error=Upload+failed.+Allowed:+PDF,+JPG,+PNG,+DOC,+DOCX+(max+5+MB)");
exit;
